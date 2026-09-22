import type { WAMessage } from "baileys";

export type ParsedIncomingMessage = {
  from: string;
  text: string;
  whatsappMessageId: string;
  timestamp: string;
};

/**
 * Decides whether a message from Baileys' messages.upsert event is one we
 * forward to Laravel, and extracts the bits we need. Pulled out as a pure
 * function (no socket, no network) so the filtering rules — the part most
 * likely to have an off-by-one bug — can be unit tested directly, unlike
 * the rest of sessionManager.ts which needs a real WhatsApp connection.
 *
 * Returns null for anything out of scope: messages we sent ourselves,
 * group chats, the status/broadcast feed, or non-text content (images,
 * stickers, etc. — CLAUDE.md §0 scopes this to text messages only).
 */
export function parseIncomingMessage(msg: WAMessage): ParsedIncomingMessage | null {
  if (msg.key.fromMe || !msg.message || !msg.key.id) {
    return null;
  }

  const remoteJid = msg.key.remoteJid ?? "";

  if (remoteJid.endsWith("@g.us") || remoteJid === "status@broadcast") {
    return null;
  }

  const text = msg.message.conversation ?? msg.message.extendedTextMessage?.text ?? null;

  if (!text) {
    return null;
  }

  return {
    from: remoteJid.split(/[:@]/)[0],
    text,
    whatsappMessageId: msg.key.id,
    timestamp: new Date(Number(msg.messageTimestamp ?? 0) * 1000).toISOString(),
  };
}
