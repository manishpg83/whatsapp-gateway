import type { FastifyBaseLogger } from "fastify";
import type { Config } from "../config.js";
import { startSession } from "./sessionManager.js";

/**
 * Called once at boot. Asks Laravel which instances SHOULD currently have
 * a live WhatsApp session (connected, or mid-connect/QR) and reconnects
 * each one using its already-saved credentials — no QR needed, no user
 * action needed. Without this, every worker restart (including tsx watch
 * auto-reloading on every file save in dev) leaves the database saying
 * "connected" while the worker has no memory of it at all, until someone
 * notices and manually clicks Disconnect then Reconnect.
 *
 * Best-effort: if Laravel is unreachable at boot, the worker still starts
 * up and serves new connections fine — this just means existing ones stay
 * stuck until the next restart or a manual Reconnect, same as today.
 */
export async function reconnectActiveSessions(config: Config, logger: FastifyBaseLogger): Promise<void> {
  let instanceIds: string[];

  try {
    const response = await fetch(`${config.LARAVEL_BASE_URL}/internal/worker/sessions`, {
      headers: { "X-Internal-Secret": config.INTERNAL_API_SECRET },
    });

    if (!response.ok) {
      logger.error({ status: response.status }, "Failed to fetch active sessions from Laravel at boot");
      return;
    }

    const data = (await response.json()) as { instance_ids: string[] };
    instanceIds = data.instance_ids;
  } catch (err) {
    logger.error({ err }, "Could not reach Laravel to fetch active sessions at boot");
    return;
  }

  logger.info({ count: instanceIds.length }, "Reconnecting active sessions from Laravel");

  for (const instanceId of instanceIds) {
    await startSession(instanceId, config, logger);
  }
}
