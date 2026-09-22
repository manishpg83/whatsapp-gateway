import path from "node:path";
import makeWASocket, { DisconnectReason, useMultiFileAuthState } from "baileys";
import QRCode from "qrcode";
import type { FastifyBaseLogger } from "fastify";
import type { Config } from "../config.js";
import { notifyLaravel } from "./callbacks.js";

type Session = {
  socket: ReturnType<typeof makeWASocket>;
};

// One entry per running instance. This is in-memory on purpose for the
// MVP: if the worker process restarts, sessions are gone and Laravel's
// "Reconnect" button starts a fresh one. Fine for now; revisit if we ever
// need the worker itself to survive restarts without user action.
const sessions = new Map<string, Session>();

/**
 * Starts (or no-ops if already running) a Baileys session for one
 * instance. QR codes and connection state changes are reported back to
 * Laravel via notifyLaravel() as they happen.
 */
export async function startSession(
  instanceId: string,
  config: Config,
  logger: FastifyBaseLogger
): Promise<void> {
  if (sessions.has(instanceId)) {
    return;
  }

  await connect(instanceId, config, logger);
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
      // no point reconnecting automatically. Anything else (lost network,
      // etc.), Laravel offers a "Reconnect" button instead of guessing.
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
}

/**
 * Stops a running session (logs the device out on WhatsApp's side) and
 * forgets it. A no-op if nothing is running for that instance — that's
 * normal, e.g. the worker restarted since it last ran.
 */
export async function stopSession(instanceId: string): Promise<void> {
  const session = sessions.get(instanceId);

  if (!session) {
    return;
  }

  sessions.delete(instanceId);

  await session.socket.logout().catch(() => {
    // Already disconnected on WhatsApp's side — nothing more to do.
  });
}
