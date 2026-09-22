import type { FastifyInstance } from "fastify";
import { requireInternalSecret } from "../auth.js";
import type { Config } from "../config.js";

/**
 * Demo protected route for M4, just to prove the internal-secret auth
 * works end to end. Real endpoints (POST /sessions, etc.) replace this
 * from M5 onward.
 */
export async function pingRoute(app: FastifyInstance, config: Config) {
  app.get(
    "/internal/ping",
    { onRequest: requireInternalSecret(config) },
    async () => {
      return { pong: true };
    }
  );
}
