import { describe, expect, it } from "vitest";
import type { WAMessage } from "baileys";
import { parseIncomingMessage, parseOwnMessage } from "../src/whatsapp/incomingMessage.js";

// A minimal WAMessage builder — only the fields parseIncomingMessage()
// actually reads, cast to WAMessage since real messages carry many more
// (irrelevant) fields.
function buildMessage(overrides: {
  fromMe?: boolean;
  remoteJid?: string;
  remoteJidAlt?: string;
  id?: string | null;
  message?: Record<string, unknown> | null;
  messageTimestamp?: number;
  pushName?: string;
}): WAMessage {
  return {
    key: {
      fromMe: overrides.fromMe ?? false,
      remoteJid: overrides.remoteJid ?? "919999999999@s.whatsapp.net",
      remoteJidAlt: overrides.remoteJidAlt,
      id: overrides.id === undefined ? "WA-ID-1" : overrides.id,
    },
    message: overrides.message === undefined ? { conversation: "Hello" } : overrides.message,
    messageTimestamp: overrides.messageTimestamp ?? 1700000000,
    pushName: overrides.pushName,
  } as unknown as WAMessage;
}

describe("parseIncomingMessage — filtering", () => {
  it("ignores messages we sent ourselves", () => {
    expect(parseIncomingMessage(buildMessage({ fromMe: true }))).toBeNull();
  });

  it("ignores group messages", () => {
    expect(parseIncomingMessage(buildMessage({ remoteJid: "123456-group@g.us" }))).toBeNull();
  });

  it("ignores the status/broadcast feed", () => {
    expect(parseIncomingMessage(buildMessage({ remoteJid: "status@broadcast" }))).toBeNull();
  });

  it("ignores a message with no content", () => {
    expect(parseIncomingMessage(buildMessage({ message: null }))).toBeNull();
  });

  it("ignores a message with no id", () => {
    expect(parseIncomingMessage(buildMessage({ id: null }))).toBeNull();
  });

  it("ignores protocol noise like reactions and deletes", () => {
    expect(parseIncomingMessage(buildMessage({ message: { reactionMessage: { text: "👍" } } }))).toBeNull();
    expect(parseIncomingMessage(buildMessage({ message: { protocolMessage: { type: 0 } } }))).toBeNull();
  });
});

describe("parseIncomingMessage — text", () => {
  it("parses a plain text message", () => {
    expect(parseIncomingMessage(buildMessage({ message: { conversation: "Hello" } }))).toEqual({
      from: "919999999999",
      fromIsLid: false,
      name: null,
      type: "text",
      text: "Hello",
      media: null,
      whatsappMessageId: "WA-ID-1",
      timestamp: new Date(1700000000 * 1000).toISOString(),
    });
  });

  it("includes the sender's WhatsApp profile name", () => {
    expect(parseIncomingMessage(buildMessage({ pushName: "  Riya Shah " }))?.name).toBe("Riya Shah");
    expect(parseIncomingMessage(buildMessage({ pushName: "" }))?.name).toBeNull();
  });

  it("parses an extended text message (e.g. a reply)", () => {
    const result = parseIncomingMessage(buildMessage({ message: { extendedTextMessage: { text: "A reply" } } }));

    expect(result?.type).toBe("text");
    expect(result?.text).toBe("A reply");
  });

  it("strips the device-id suffix from the sender", () => {
    expect(parseIncomingMessage(buildMessage({ remoteJid: "919999999999:9@s.whatsapp.net" }))?.from).toBe("919999999999");
  });

  it("unwraps disappearing (ephemeral) messages", () => {
    const result = parseIncomingMessage(
      buildMessage({ message: { ephemeralMessage: { message: { conversation: "Secret-ish" } } } })
    );

    expect(result?.text).toBe("Secret-ish");
  });
});

describe("parseIncomingMessage — media", () => {
  it("parses an image with its caption, mime type and size", () => {
    const result = parseIncomingMessage(
      buildMessage({ message: { imageMessage: { caption: "Look", mimetype: "image/jpeg", fileLength: 12345 } } })
    );

    expect(result?.type).toBe("image");
    expect(result?.text).toBe("Look");
    expect(result?.media).toEqual({ mimeType: "image/jpeg", fileName: null, size: 12345 });
  });

  it("parses an image with no caption as empty text", () => {
    const result = parseIncomingMessage(buildMessage({ message: { imageMessage: { mimetype: "image/png" } } }));

    expect(result?.text).toBe("");
    expect(result?.media?.size).toBeNull();
  });

  it("parses a video", () => {
    const result = parseIncomingMessage(buildMessage({ message: { videoMessage: { mimetype: "video/mp4", caption: "Clip" } } }));

    expect(result?.type).toBe("video");
    expect(result?.text).toBe("Clip");
  });

  it("tells a voice note apart from an audio file, and strips mime parameters", () => {
    const voice = parseIncomingMessage(
      buildMessage({ message: { audioMessage: { ptt: true, mimetype: "audio/ogg; codecs=opus" } } })
    );
    const audio = parseIncomingMessage(buildMessage({ message: { audioMessage: { ptt: false, mimetype: "audio/mpeg" } } }));

    expect(voice?.type).toBe("voice");
    expect(voice?.media?.mimeType).toBe("audio/ogg");
    expect(audio?.type).toBe("audio");
  });

  it("parses a document with its original file name", () => {
    const result = parseIncomingMessage(
      buildMessage({ message: { documentMessage: { mimetype: "application/pdf", fileName: "Invoice 42.pdf", fileLength: 999 } } })
    );

    expect(result?.type).toBe("document");
    expect(result?.media).toEqual({ mimeType: "application/pdf", fileName: "Invoice 42.pdf", size: 999 });
  });

  it("unwraps a document sent with a caption", () => {
    const result = parseIncomingMessage(
      buildMessage({
        message: {
          documentWithCaptionMessage: {
            message: { documentMessage: { mimetype: "application/pdf", fileName: "a.pdf", caption: "Here you go" } },
          },
        },
      })
    );

    expect(result?.type).toBe("document");
    expect(result?.text).toBe("Here you go");
  });

  it("parses a sticker", () => {
    const result = parseIncomingMessage(buildMessage({ message: { stickerMessage: { mimetype: "image/webp" } } }));

    expect(result?.type).toBe("sticker");
    expect(result?.media?.mimeType).toBe("image/webp");
  });

  it("does not download view-once media", () => {
    const result = parseIncomingMessage(
      buildMessage({ message: { viewOnceMessageV2: { message: { imageMessage: { mimetype: "image/jpeg" } } } } })
    );

    expect(result?.type).toBe("unsupported");
    expect(result?.media).toBeNull();
    expect(result?.text).toContain("View-once");
  });
});

describe("parseIncomingMessage — other types", () => {
  it("turns a location into readable text with a map link", () => {
    const result = parseIncomingMessage(
      buildMessage({ message: { locationMessage: { degreesLatitude: 19.076, degreesLongitude: 72.8777, name: "Office" } } })
    );

    expect(result?.type).toBe("location");
    expect(result?.text).toContain("Location: Office");
    expect(result?.text).toContain("https://maps.google.com/?q=19.076,72.8777");
    expect(result?.media).toBeNull();
  });

  it("turns a contact card into a name and phone number", () => {
    const vcard = "BEGIN:VCARD\nVERSION:3.0\nFN:Jane Doe\nTEL;type=CELL;waid=919876543210:+91 98765 43210\nEND:VCARD";
    const result = parseIncomingMessage(buildMessage({ message: { contactMessage: { displayName: "Jane Doe", vcard } } }));

    expect(result?.type).toBe("contact");
    expect(result?.text).toBe("Contact: Jane Doe (+91 98765 43210)");
  });

  it("keeps unknown message types as 'unsupported' instead of dropping them", () => {
    const result = parseIncomingMessage(buildMessage({ message: { pollCreationMessage: { name: "Lunch?" } } }));

    expect(result?.type).toBe("unsupported");
    expect(result?.text).toBe("[Unsupported message type: pollCreationMessage]");
  });
});

describe("parseIncomingMessage — LID senders", () => {
  it("uses the phone number Baileys gives alongside a LID", () => {
    const result = parseIncomingMessage(
      buildMessage({ remoteJid: "248600000000055@lid", remoteJidAlt: "919999999999:3@s.whatsapp.net" })
    );

    expect(result?.from).toBe("919999999999");
    expect(result?.fromIsLid).toBe(false);
  });

  it("flags a LID sender when no phone number is known", () => {
    const result = parseIncomingMessage(buildMessage({ remoteJid: "248600000000055@lid" }));

    expect(result?.from).toBe("248600000000055");
    expect(result?.fromIsLid).toBe(true);
  });

  it("ignores remoteJidAlt for a normal phone-number chat", () => {
    const result = parseIncomingMessage(
      buildMessage({ remoteJid: "919999999999@s.whatsapp.net", remoteJidAlt: "248600000000055@lid" })
    );

    expect(result?.from).toBe("919999999999");
    expect(result?.fromIsLid).toBe(false);
  });
});

describe("parseOwnMessage — the owner replying from their phone", () => {
  it("reports who the owner wrote to, and what", () => {
    expect(parseOwnMessage(buildMessage({ fromMe: true, message: { conversation: "I'll call you" } }))).toEqual({
      to: "919999999999",
      toIsLid: false,
      type: "text",
      text: "I'll call you",
      media: null,
      whatsappMessageId: "WA-ID-1",
      timestamp: new Date(1700000000 * 1000).toISOString(),
    });
  });

  it("includes a photo the owner sent, so it can be downloaded", () => {
    const result = parseOwnMessage(
      buildMessage({ fromMe: true, message: { imageMessage: { caption: "Our menu", mimetype: "image/jpeg", fileLength: 2048 } } })
    );

    expect(result?.type).toBe("image");
    expect(result?.text).toBe("Our menu");
    expect(result?.media).toEqual({ mimeType: "image/jpeg", fileName: null, size: 2048 });
  });

  it("uses the phone number behind a LID chat when given", () => {
    const result = parseOwnMessage(
      buildMessage({ fromMe: true, remoteJid: "248600000000055@lid", remoteJidAlt: "919999999999@s.whatsapp.net" })
    );

    expect(result?.to).toBe("919999999999");
    expect(result?.toIsLid).toBe(false);
  });

  it("ignores incoming messages, groups, status and protocol noise", () => {
    expect(parseOwnMessage(buildMessage({ fromMe: false }))).toBeNull();
    expect(parseOwnMessage(buildMessage({ fromMe: true, remoteJid: "123-group@g.us" }))).toBeNull();
    expect(parseOwnMessage(buildMessage({ fromMe: true, remoteJid: "status@broadcast" }))).toBeNull();
    expect(parseOwnMessage(buildMessage({ fromMe: true, message: { reactionMessage: { text: "👍" } } }))).toBeNull();
  });
});
