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
  from: string;
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

function build(
  msg: WAMessage,
  remoteJid: string,
  type: IncomingType,
  text: string,
  mediaInfo: IncomingMedia | null
): ParsedIncomingMessage {
  return {
    from: remoteJid.split(/[:@]/)[0],
    type,
    text,
    media: mediaInfo,
    whatsappMessageId: msg.key.id!,
    timestamp: new Date(Number(msg.messageTimestamp ?? 0) * 1000).toISOString(),
  };
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
