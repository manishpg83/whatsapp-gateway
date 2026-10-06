import { getContentType, normalizeMessageContent } from "baileys";
import type { WAMessage, proto } from "baileys";

export type IncomingType =
  | "text"
  | "image"
  | "video"
  | "audio"
  | "voice"
  | "document"
  | "sticker"
  | "location"
  | "contact"
  | "unsupported";

/** Media that should be downloaded and stored for this message. */
export type IncomingMedia = {
  mimeType: string;
  fileName: string | null; // only documents carry a real file name
  size: number | null; // as declared by WhatsApp, in bytes
};

export type ParsedIncomingMessage = {
  // The sender's phone number (digits). When WhatsApp hides it behind a
  // LID (a private id) and gives no phone number, this is the LID's digits
  // instead and fromIsLid is true — never reply to it as a phone number.
  from: string;
  fromIsLid: boolean;
  type: IncomingType;
  // Message text, the media caption, or a readable summary (location,
  // contact, unsupported). May be "" — e.g. a photo with no caption.
  text: string;
  media: IncomingMedia | null;
  whatsappMessageId: string;
  timestamp: string;
};

// Not user content — protocol/system noise (edits, deletes, reactions,
// encryption key exchange, poll votes). Silently skipped, as before.
const IGNORED_CONTENT_TYPES = new Set<string>([
  "protocolMessage",
  "reactionMessage",
  "encReactionMessage",
  "senderKeyDistributionMessage",
  "messageContextInfo",
  "pollUpdateMessage",
  "keepInChatMessage",
  "pinInChatMessage",
]);

/**
 * Decides whether a message from Baileys' messages.upsert event is one we
 * forward to Laravel, and extracts the bits we need. A pure function (no
 * socket, no network) so the rules can be unit tested directly; actually
 * downloading media happens in media.ts / sessionManager.ts.
 *
 * Returns null for anything out of scope: messages we sent ourselves,
 * group chats, the status/broadcast feed, and protocol noise. Every other
 * kind of message is forwarded — anything we can't handle becomes
 * "unsupported" instead of silently disappearing.
 */
export function parseIncomingMessage(msg: WAMessage): ParsedIncomingMessage | null {
  if (msg.key.fromMe || !msg.message || !msg.key.id) {
    return null;
  }

  const remoteJid = msg.key.remoteJid ?? "";

  if (remoteJid.endsWith("@g.us") || remoteJid === "status@broadcast") {
    return null;
  }

  // View-once photos/videos: WhatsApp doesn't show these on linked
  // devices either, and storing a copy would defeat the sender's intent.
  if (isViewOnce(msg.message)) {
    return build(msg, remoteJid, "unsupported", "[View-once message — open it on the phone]", null);
  }

  // Unwraps ephemeral ("disappearing") and document-with-caption wrappers.
  const content = normalizeMessageContent(msg.message);
  const contentType = getContentType(content);

  if (!content || !contentType || IGNORED_CONTENT_TYPES.has(contentType)) {
    return null;
  }

  switch (contentType) {
    case "conversation":
      return build(msg, remoteJid, "text", content.conversation ?? "", null);

    case "extendedTextMessage":
      return build(msg, remoteJid, "text", content.extendedTextMessage?.text ?? "", null);

    case "imageMessage": {
      const m = content.imageMessage!;
      return build(msg, remoteJid, "image", m.caption ?? "", media(m.mimetype, null, m.fileLength, "image/jpeg"));
    }

    case "videoMessage": {
      const m = content.videoMessage!;
      return build(msg, remoteJid, "video", m.caption ?? "", media(m.mimetype, null, m.fileLength, "video/mp4"));
    }

    case "audioMessage": {
      const m = content.audioMessage!;
      // ptt ("push to talk") = a recorded voice note, not a sent audio file.
      return build(msg, remoteJid, m.ptt ? "voice" : "audio", "", media(m.mimetype, null, m.fileLength, "audio/ogg"));
    }

    case "documentMessage": {
      const m = content.documentMessage!;
      return build(
        msg,
        remoteJid,
        "document",
        m.caption ?? "",
        media(m.mimetype, m.fileName ?? m.title ?? null, m.fileLength, "application/octet-stream")
      );
    }

    case "stickerMessage": {
      const m = content.stickerMessage!;
      return build(msg, remoteJid, "sticker", "", media(m.mimetype, null, m.fileLength, "image/webp"));
    }

    case "locationMessage":
    case "liveLocationMessage": {
      const m = (content.locationMessage ?? content.liveLocationMessage)!;
      return build(msg, remoteJid, "location", describeLocation(m), null);
    }

    case "contactMessage":
      return build(msg, remoteJid, "contact", describeContact(content.contactMessage!), null);

    case "contactsArrayMessage": {
      const contacts = content.contactsArrayMessage?.contacts ?? [];
      return build(msg, remoteJid, "contact", contacts.map(describeContact).join("\n\n"), null);
    }

    default:
      return build(msg, remoteJid, "unsupported", `[Unsupported message type: ${contentType}]`, null);
  }
}

export type ParsedOwnMessage = {
  // The customer the owner wrote to (phone digits — or a LID's digits when toIsLid).
  to: string;
  toIsLid: boolean;
  whatsappMessageId: string;
};

/**
 * A message the OWNER typed on their phone (or WhatsApp Web) to a
 * customer — so Laravel can pause the chatbot for that chat. Only call this
 * for "notify" upserts: messages this worker sends itself arrive as
 * "append" and must never count as the owner replying.
 *
 * Returns null for anything else: incoming messages, groups, the status
 * feed, and protocol noise (reactions, edits, deletes...).
 */
export function parseOwnMessage(msg: WAMessage): ParsedOwnMessage | null {
  if (!msg.key.fromMe || !msg.message || !msg.key.id) {
    return null;
  }

  const remoteJid = msg.key.remoteJid ?? "";

  if (!remoteJid || remoteJid.endsWith("@g.us") || remoteJid === "status@broadcast") {
    return null;
  }

  const content = normalizeMessageContent(msg.message);
  const contentType = getContentType(content);

  if (!content || !contentType || IGNORED_CONTENT_TYPES.has(contentType)) {
    return null;
  }

  const recipient = senderOf(remoteJid, msg.key.remoteJidAlt);

  return { to: recipient.from, toIsLid: recipient.fromIsLid, whatsappMessageId: msg.key.id };
}

function build(
  msg: WAMessage,
  remoteJid: string,
  type: IncomingType,
  text: string,
  mediaInfo: IncomingMedia | null
): ParsedIncomingMessage {
  const sender = senderOf(remoteJid, msg.key.remoteJidAlt);

  return {
    from: sender.from,
    fromIsLid: sender.fromIsLid,
    type,
    text,
    media: mediaInfo,
    whatsappMessageId: msg.key.id!,
    timestamp: new Date(Number(msg.messageTimestamp ?? 0) * 1000).toISOString(),
  };
}

/**
 * "919999999999:9@s.whatsapp.net" -> "919999999999". For a LID chat
 * ("123…@lid"), Baileys 7 usually also gives the phone-number JID in
 * key.remoteJidAlt — use that when it's there.
 */
export function senderOf(remoteJid: string, remoteJidAlt?: string | null): { from: string; fromIsLid: boolean } {
  if (!remoteJid.endsWith("@lid")) {
    return { from: jidDigits(remoteJid), fromIsLid: false };
  }

  if (remoteJidAlt?.endsWith("@s.whatsapp.net")) {
    return { from: jidDigits(remoteJidAlt), fromIsLid: false };
  }

  return { from: jidDigits(remoteJid), fromIsLid: true };
}

export function jidDigits(jid: string): string {
  return jid.split(/[:@]/)[0];
}

function media(
  mimeType: string | null | undefined,
  fileName: string | null,
  fileLength: number | Long | null | undefined,
  fallbackMime: string
): IncomingMedia {
  return {
    // e.g. "audio/ogg; codecs=opus" -> "audio/ogg"
    mimeType: (mimeType ?? fallbackMime).split(";")[0].trim().toLowerCase(),
    fileName,
    size: fileLength === null || fileLength === undefined ? null : Number(fileLength),
  };
}

// Baileys' Long type (protobuf 64-bit ints) — only ever converted with Number().
type Long = { toNumber(): number };

function isViewOnce(message: proto.IMessage): boolean {
  return Boolean(
    message.viewOnceMessage ||
      message.viewOnceMessageV2 ||
      message.viewOnceMessageV2Extension ||
      message.imageMessage?.viewOnce ||
      message.videoMessage?.viewOnce
  );
}

function describeLocation(m: proto.Message.ILocationMessage | proto.Message.ILiveLocationMessage): string {
  const lat = m.degreesLatitude ?? 0;
  const lng = m.degreesLongitude ?? 0;
  const label = "name" in m && m.name ? `${m.name}${"address" in m && m.address ? `, ${m.address}` : ""}` : null;

  return [
    `Location: ${label ?? `${lat}, ${lng}`}`,
    `https://maps.google.com/?q=${lat},${lng}`,
  ].join("\n");
}

function describeContact(m: proto.Message.IContactMessage): string {
  // Pull the phone number(s) out of the vCard (lines like "TEL;waid=...:+91 98...").
  const phones = (m.vcard ?? "")
    .split(/\r?\n/)
    .filter((line) => line.toUpperCase().startsWith("TEL"))
    .map((line) => line.substring(line.lastIndexOf(":") + 1).trim())
    .filter(Boolean);

  return `Contact: ${m.displayName ?? "Unknown"}${phones.length ? ` (${phones.join(", ")})` : ""}`;
}
