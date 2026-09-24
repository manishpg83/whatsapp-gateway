import type { Config } from "../config.js";
import type { IncomingType } from "./incomingMessage.js";
import type { MediaResult } from "./media.js";

export type WorkerEvent =
  | { event: "qr.updated"; instance_id: string; qr_code: string }
  | {
      event: "connection.updated";
      instance_id: string;
      status: "connected" | "disconnected" | "logged_out";
      phone_number?: string | null;
      last_disconnect_reason?: string | null;
    }
  | {
      event: "message.received";
      instance_id: string;
      from: string;
      type: IncomingType;
      message: string; // text, caption or summary — may be ""
      whatsapp_message_id: string;
      timestamp: string;
      media: {
        status: MediaResult["status"];
        path: string | null; // relative to the shared media folder
        mime_type: string;
        file_name: string | null;
        size: number | null;
      } | null;
    };

type EventLogger = {
  error: (obj: unknown, msg?: string) => void;
};

/**
 * Tells Laravel about a QR refresh or a connection state change. Laravel
 * owns the whatsapp_sessions row; the worker only ever reports what
 * happened. This is fire-and-forget from the caller's point of view, but
 * we log failures loudly — a missed callback leaves the dashboard stuck
 * showing a stale status.
 */
export async function notifyLaravel(
  config: Config,
  event: WorkerEvent,
  logger: EventLogger
): Promise<void> {
  try {
    const response = await fetch(config.LARAVEL_CALLBACK_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Internal-Secret": config.INTERNAL_API_SECRET,
      },
      body: JSON.stringify(event),
    });

    if (!response.ok) {
      logger.error(
        { status: response.status, event },
        "Laravel rejected a worker event callback"
      );
    }
  } catch (err) {
    logger.error({ err, event }, "Failed to reach Laravel with a worker event callback");
  }
}
