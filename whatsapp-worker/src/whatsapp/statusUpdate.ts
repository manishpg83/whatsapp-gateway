import type { WAMessageUpdate } from "baileys";

export type ParsedStatusUpdate = {
  whatsappMessageId: string;
  status: "delivered" | "read";
};

// WhatsApp's message receipt codes (proto.WebMessageInfo.Status):
// 0 ERROR, 1 PENDING, 2 SERVER_ACK (sent), 3 DELIVERY_ACK (✓✓),
// 4 READ (blue ✓✓), 5 PLAYED (voice note / video played — counts as read).
const DELIVERY_ACK = 3;
const READ = 4;
const PLAYED = 5;

/**
 * Turns one entry of Baileys' messages.update event into "this message we
 * sent was delivered / read", or null for anything else (other people's
 * messages, groups, edits, statuses we don't track). A pure function so it
 * can be unit tested without a WhatsApp connection.
 */
export function parseStatusUpdate(update: WAMessageUpdate): ParsedStatusUpdate | null {
  const { key } = update;
  const status = update.update?.status;

  // Only receipts for messages WE sent, in 1:1 chats.
  if (!key.fromMe || !key.id || typeof status !== "number") {
    return null;
  }

  const remoteJid = key.remoteJid ?? "";
  if (remoteJid.endsWith("@g.us") || remoteJid === "status@broadcast") {
    return null;
  }

  if (status === READ || status === PLAYED) {
    return { whatsappMessageId: key.id, status: "read" };
  }

  if (status === DELIVERY_ACK) {
    return { whatsappMessageId: key.id, status: "delivered" };
  }

  return null;
}
