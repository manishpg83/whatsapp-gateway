import type { FastifyInstance } from "fastify";

/**
 * Public liveness check. No secret required, so process managers / uptime
 * checks can hit this without knowing INTERNAL_API_SECRET.
 */
export async function healthRoute(app: FastifyInstance) {
  app.get("/health", async () => {
    return { status: "ok" };
  });
}
