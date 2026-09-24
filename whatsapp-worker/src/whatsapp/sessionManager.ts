import path from "node:path";
import fs from "node:fs/promises";
import makeWASocket, { DisconnectReason, downloadMediaMessage, useMultiFileAuthState } from "baileys";
import type { AnyMessageContent, WAMessage } from "baileys";
import QRCode from "qrcode";
import type { FastifyBaseLogger } from "fastify";
import type { Config } from "../config.js";
import { notifyLaravel } from "./callbacks.js";
import { parseIncomingMessage } from "./incomingMessage.js";
import type { IncomingMedia } from "./incomingMessage.js";
import { MediaTooLargeError, mediaFileName, saveMediaStream } from "./media.js";
import type { MediaResult } from "./media.js";

type Session = {
  socket: ReturnType<typeof makeWASocket>;
};

// One entry per running instance. This is in-memory on purpose for the
// MVP: if the worker process restarts, sessions are gone — see
// reconnectActiveSessions() in reconnectAll.ts, called once at boot, for
// how they come back automatically without the user having to click
// "Reconnect" by hand.
const sessions = new Map<string, Session>();

// Instance ids currently in the middle of connect(), i.e. past the
// startSession() "already running?" check but before sessions.set() has
// happened (that gap involves an await). Without this, two near-
// simultaneous startSession() calls for the same instance — easy to
// trigger with an impatient double-click on "Reconnect" — could both
// pass the check and open two sockets for the same instance/auth files.
const startingInstanceIds = new Set<string>();

/**
 * Starts (or no-ops if already running/starting) a Baileys session for
 * one instance. QR codes and connection state changes are reported back
 * to Laravel via notifyLaravel() as they happen.
 */
export async function startSession(
  instanceId: string,
  config: Config,
  logger: FastifyBaseLogger
): Promise<void> {
  if (sessions.has(instanceId) || startingInstanceIds.has(instanceId)) {
    return;
  }

  startingInstanceIds.add(instanceId);

  try {
    await connect(instanceId, config, logger);
  } finally {
    startingInstanceIds.delete(instanceId);
  }
}

/**
 * Deletes an instance's saved Baileys credentials. Called whenever a
 * session is genuinely logged out (explicit Disconnect, or WhatsApp
 * reporting loggedOut for any other reason, e.g. unlinked from the
 * phone) — without this, a later "Reconnect" would silently keep retrying
 * with dead credentials and never produce a fresh QR code.
 */
async function clearAuthState(instanceId: string, config: Config): Promise<void> {
  const authDir = path.join(config.SESSION_STORAGE_PATH, instanceId);

  await fs.rm(authDir, { recursive: true, force: true }).catch(() => {
    // Nothing to clear, or a permissions hiccup — either way, not fatal.
  });
}

/**
 * Opens one Baileys socket. Split out from startSession() because right
 * after a first-time QR pairing, WhatsApp always closes the connection
 * once with a "restart required" reason (515) — that's expected protocol
 * behaviour, not a real disconnect, and the fix is simply to call this
 * again with the now-saved credentials. See the "close" handling below.
 */
async function connect(instanceId: string, config: Config, logger: FastifyBaseLogger): Promise<void> {
  const authDir = path.join(config.SESSION_STORAGE_PATH, instanceId);
  const { state, saveCreds } = await useMultiFileAuthState(authDir);

  const socket = makeWASocket({
    auth: state,
    logger: logger.child({ instanceId }),
  });

  sessions.set(instanceId, { socket });

  socket.ev.on("creds.update", saveCreds);

  socket.ev.on("connection.update", async (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      const qrCode = await QRCode.toDataURL(qr);
      await notifyLaravel(
        config,
        { event: "qr.updated", instance_id: instanceId, qr_code: qrCode },
        logger
      );
    }

    if (connection === "open") {
      const phoneNumber = socket.user?.phoneNumber ?? socket.user?.id?.split(/[:@]/)[0] ?? null;
      await notifyLaravel(
        config,
        {
          event: "connection.updated",
          instance_id: instanceId,
          status: "connected",
          phone_number: phoneNumber,
        },
        logger
      );
    }

    if (connection === "close") {
      sessions.delete(instanceId);

      // Baileys wraps the disconnect reason in a Boom error.
      const error = lastDisconnect?.error as { output?: { statusCode?: number } } | undefined;
      const statusCode = error?.output?.statusCode;
      const loggedOut = statusCode === DisconnectReason.loggedOut;
      const restartRequired = statusCode === DisconnectReason.restartRequired;

      if (restartRequired) {
        // Expected: happens exactly once, right after a fresh QR pairing.
        // Reconnect silently with the (now-saved) credentials — this is
        // not a real disconnect, so Laravel/the user never need to know.
        logger.info({ instanceId }, "Restart required after pairing, reconnecting automatically");
        setTimeout(() => {
          void connect(instanceId, config, logger);
        }, 1000);
        return;
      }

      // loggedOut means the user unlinked the device from their phone —
      // no point reconnecting automatically, and the saved credentials
      // are now dead, so clear them (a later "Reconnect" then correctly
      // starts a fresh pairing instead of failing forever). Anything else
      // (lost network, etc.), Laravel offers a "Reconnect" button and the
      // existing credentials are still good.
      if (loggedOut) {
        await clearAuthState(instanceId, config);
      }

      await notifyLaravel(
        config,
        {
          event: "connection.updated",
          instance_id: instanceId,
          status: loggedOut ? "logged_out" : "disconnected",
          last_disconnect_reason: lastDisconnect?.error?.message ?? null,
        },
        logger
      );
    }
  });

  socket.ev.on("messages.upsert", async ({ messages, type }) => {
    logger.info({ instanceId, type, count: messages.length }, "messages.upsert received");

    // "notify" = a genuinely new, real-time message. "append" (history
    // sync on first link, etc.) is replayed old messages — not something
    // to forward as if it just arrived.
    if (type !== "notify") {
      return;
    }

    for (const msg of messages) {
      const parsed = parseIncomingMessage(msg);

      if (!parsed) {
        logger.info(
          { instanceId, remoteJid: msg.key.remoteJid, fromMe: msg.key.fromMe },
          "Skipped a message.upsert entry (not a forwardable incoming message)"
        );
        continue;
      }

      // Download the file first, so Laravel gets told where it is.
      const mediaResult = parsed.media
        ? await downloadMedia(msg, parsed.media, parsed.whatsappMessageId, instanceId, socket, config, logger)
        : null;

      logger.info({ instanceId, from: parsed.from, type: parsed.type }, "Forwarding incoming message to Laravel");

      await notifyLaravel(
        config,
        {
          event: "message.received",
          instance_id: instanceId,
          from: parsed.from,
          type: parsed.type,
          message: parsed.text,
          whatsapp_message_id: parsed.whatsappMessageId,
          timestamp: parsed.timestamp,
          media:
            parsed.media && mediaResult
              ? {
                  status: mediaResult.status,
                  path: mediaResult.path,
                  mime_type: parsed.media.mimeType,
                  file_name: parsed.media.fileName,
                  size: mediaResult.size,
                }
              : null,
        },
        logger
      );
    }
  });
}

/**
 * Downloads one incoming message's media to MEDIA_STORAGE_PATH. Never
 * throws: a failed or oversized download still forwards the message to
 * Laravel (with the media marked failed / too_large), so the customer
 * always sees that *something* arrived.
 */
async function downloadMedia(
  msg: WAMessage,
  media: IncomingMedia,
  whatsappMessageId: string,
  instanceId: string,
  socket: ReturnType<typeof makeWASocket>,
  config: Config,
  logger: FastifyBaseLogger
): Promise<MediaResult> {
  const maxBytes = config.MAX_MEDIA_MB * 1024 * 1024;

  // WhatsApp tells us the size up front — skip obvious giants without downloading.
  if (media.size !== null && media.size > maxBytes) {
    logger.warn({ instanceId, size: media.size }, "Incoming media too large, not downloaded");
    return { status: "too_large", path: null, size: media.size };
  }

  try {
    const stream = await downloadMediaMessage(
      msg,
      "stream",
      {},
      // Lets Baileys ask the sender's phone to re-upload expired media.
      { logger, reuploadRequest: socket.updateMediaMessage }
    );

    const saved = await saveMediaStream(
      stream,
      config.MEDIA_STORAGE_PATH,
      instanceId,
      mediaFileName(whatsappMessageId, media),
      maxBytes
    );

    return { status: "stored", path: saved.path, size: saved.size };
  } catch (err) {
    if (err instanceof MediaTooLargeError) {
      logger.warn({ instanceId }, "Incoming media grew past the size limit while downloading");
      return { status: "too_large", path: null, size: media.size };
    }

    logger.error({ instanceId, err }, "Failed to download incoming media");
    return { status: "failed", path: null, size: media.size };
  }
}

/**
 * Thrown when a send is requested for an instance with no live socket in
 * this worker process — e.g. the worker restarted since the instance last
 * connected. The caller (Laravel) turns this into a "not connected" error
 * instead of a generic 500.
 */
export class SessionNotActiveError extends Error {
  constructor(instanceId: string) {
    super(`No active WhatsApp session for instance ${instanceId}`);
    this.name = "SessionNotActiveError";
  }
}

/**
 * Sends a text or media message and returns WhatsApp's own message id.
 * `content` comes from buildOutgoingContent() (outgoingMessage.ts).
 */
export async function sendMessage(instanceId: string, to: string, content: AnyMessageContent): Promise<string> {
  const session = sessions.get(instanceId);

  if (!session) {
    throw new SessionNotActiveError(instanceId);
  }

  // Individual chats only for M7 (no group JIDs) — matches the scope in
  // CLAUDE.md §0.
  const jid = `${to}@s.whatsapp.net`;
  const result = await session.socket.sendMessage(jid, content);
  const messageId = result?.key?.id;

  if (!messageId) {
    throw new Error("Baileys did not return a message id");
  }

  return messageId;
}

/**
 * Stops a running session (logs the device out on WhatsApp's side) and
 * forgets it. Always clears the saved credentials, whether or not a live
 * socket existed for this instance in this worker process — the intent
 * of "disconnect" is always "this instance is no longer linked", so a
 * later "Reconnect" should start a fresh pairing either way.
 */
export async function stopSession(instanceId: string, config: Config): Promise<void> {
  const session = sessions.get(instanceId);

  if (session) {
    sessions.delete(instanceId);

    await session.socket.logout().catch(() => {
      // Already disconnected on WhatsApp's side — nothing more to do.
    });
  }

  await clearAuthState(instanceId, config);
}
