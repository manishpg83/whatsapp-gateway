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
      // true = the worker will reconnect by itself shortly (network drop);
      // false = it has stopped trying and the user must act.
      auto_retry?: boolean;
      last_disconnect_reason?: string | null;
    }
  | {
      event: "message.received";
      instance_id: string;
      from: string; // phone number digits — or a LID's digits when from_is_lid
      from_is_lid: boolean;
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
    }
  | {
      // The owner wrote to a customer from their own phone (not via us).
      event: "message.sent_from_phone";
      instance_id: string;
      to: string; // phone number digits — or a LID's digits when to_is_lid
      to_is_lid: boolean;
      whatsapp_message_id: string;
    }
  | {
      // A message we sent was delivered (✓✓) or read (blue ✓✓).
      event: "message.status";
      instance_id: string;
      whatsapp_message_id: string;
      status: "delivered" | "read";
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
