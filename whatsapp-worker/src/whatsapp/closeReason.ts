import { DisconnectReason } from "baileys";

// How long to wait before each automatic reconnect attempt after a
// network-type drop. After the last one we give up and leave it to the
// user's "Reconnect" button (~9 minutes of trying in total).
export const RETRY_DELAYS_MS = [5_000, 10_000, 30_000, 60_000, 120_000, 300_000];

export type CloseDecision =
  // Expected once right after a fresh QR pairing — reconnect at once, silently.
  | { action: "restart" }
  // Temporary problem (network, WhatsApp server hiccup) — try again later.
  | { action: "retry"; delayMs: number; reason: string }
  // Nothing more to do automatically; report this status and stop.
  | { action: "stop"; status: "disconnected" | "logged_out"; reason: string };

/**
 * Decides what to do when a Baileys socket closes.
 *
 * @param statusCode  Baileys' DisconnectReason code (undefined if none).
 * @param paired      Whether this instance has ever finished pairing
 *                    (has saved credentials) — an unpaired socket closing
 *                    means the QR was never scanned.
 * @param attempt     How many automatic retries have already happened
 *                    in a row for this instance.
 */
export function decideOnClose(statusCode: number | undefined, paired: boolean, attempt: number): CloseDecision {
  if (statusCode === DisconnectReason.restartRequired) {
    return { action: "restart" };
  }

  if (statusCode === DisconnectReason.loggedOut) {
    return {
      action: "stop",
      status: "logged_out",
      reason: "This device was logged out from WhatsApp (for example, removed from Linked devices on the phone). Reconnect and scan a new QR code.",
    };
  }

  if (!paired) {
    // Nobody scanned the QR in time (WhatsApp stops issuing new ones), or
    // the connection dropped mid-pairing. Either way, a fresh QR is needed.
    return {
      action: "stop",
      status: "disconnected",
      reason: "The QR code expired before it was scanned. Click Reconnect to get a new one.",
    };
  }

  if (statusCode === DisconnectReason.connectionReplaced) {
    // Another connection took over this same session; retrying would just
    // make the two fight over it.
    return {
      action: "stop",
      status: "disconnected",
      reason: "This session was opened somewhere else, so this connection was closed. Click Reconnect to take it back.",
    };
  }

  if (statusCode === DisconnectReason.forbidden || statusCode === DisconnectReason.multideviceMismatch) {
    // WhatsApp refused the connection — not something to retry automatically.
    return {
      action: "stop",
      status: "disconnected",
      reason: "WhatsApp refused the connection. Check the phone's WhatsApp, then click Reconnect.",
    };
  }

  if (attempt >= RETRY_DELAYS_MS.length) {
    return {
      action: "stop",
      status: "disconnected",
      reason: "The connection was lost and automatic reconnecting gave up. Click Reconnect to try again.",
    };
  }

  return {
    action: "retry",
    delayMs: RETRY_DELAYS_MS[attempt]!,
    reason: `Connection lost. Reconnecting automatically (attempt ${attempt + 1} of ${RETRY_DELAYS_MS.length})…`,
  };
}
