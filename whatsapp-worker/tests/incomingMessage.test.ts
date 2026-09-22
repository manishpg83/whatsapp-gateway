import { describe, expect, it } from "vitest";
import type { WAMessage } from "baileys";
import { parseIncomingMessage } from "../src/whatsapp/incomingMessage.js";

// A minimal WAMessage builder — only the fields parseIncomingMessage()
// actually reads, cast to WAMessage since real messages carry many more
// (irrelevant) fields.
function buildMessage(overrides: {
  fromMe?: boolean;
  remoteJid?: string;
  id?: string | null;
  conversation?: string;
  extendedText?: string;
  messageTimestamp?: number;
}): WAMessage {
  return {
    key: {
      fromMe: overrides.fromMe ?? false,
      remoteJid: overrides.remoteJid ?? "919999999999@s.whatsapp.net",
      id: overrides.id === undefined ? "WA-ID-1" : overrides.id,
    },
    message:
      overrides.conversation !== undefined
        ? { conversation: overrides.conversation }
        : overrides.extendedText !== undefined
          ? { extendedTextMessage: { text: overrides.extendedText } }
          : undefined,
    messageTimestamp: overrides.messageTimestamp ?? 1700000000,
  } as unknown as WAMessage;
}

describe("parseIncomingMessage", () => {
  it("parses a plain text message", () => {
    const result = parseIncomingMessage(
      buildMessage({ remoteJid: "919999999999@s.whatsapp.net", conversation: "Hello" })
    );

    expect(result).toEqual({
      from: "919999999999",
      text: "Hello",
      whatsappMessageId: "WA-ID-1",
      timestamp: new Date(1700000000 * 1000).toISOString(),
    });
  });

  it("parses an extended text message (e.g. a reply)", () => {
    const result = parseIncomingMessage(buildMessage({ extendedText: "A reply" }));

    expect(result?.text).toBe("A reply");
  });

  it("strips the device-id suffix from the sender", () => {
    const result = parseIncomingMessage(buildMessage({ remoteJid: "919999999999:9@s.whatsapp.net", conversation: "hi" }));

    expect(result?.from).toBe("919999999999");
  });

  it("ignores messages we sent ourselves", () => {
    expect(parseIncomingMessage(buildMessage({ fromMe: true, conversation: "hi" }))).toBeNull();
  });

  it("ignores group messages", () => {
    expect(
      parseIncomingMessage(buildMessage({ remoteJid: "123456-group@g.us", conversation: "hi" }))
    ).toBeNull();
  });

  it("ignores the status/broadcast feed", () => {
    expect(
      parseIncomingMessage(buildMessage({ remoteJid: "status@broadcast", conversation: "hi" }))
    ).toBeNull();
  });

  it("ignores non-text content (no conversation or extendedTextMessage)", () => {
    expect(parseIncomingMessage(buildMessage({}))).toBeNull();
  });

  it("ignores a message with no id", () => {
    expect(parseIncomingMessage(buildMessage({ id: null, conversation: "hi" }))).toBeNull();
  });
});
