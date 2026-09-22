import type { FastifyInstance } from "fastify";
import { z } from "zod";
import { requireInternalSecret } from "../auth.js";
import type { Config } from "../config.js";
import { startSession, stopSession } from "../whatsapp/sessionManager.js";

const startBodySchema = z.object({
  instance_id: z.string().uuid(),
});

/**
 * Laravel calls these to start/stop a WhatsApp session for one instance.
 * Both routes require the shared internal secret. Laravel is the source
 * of truth for whether the instance_id is real and owned by the right
 * user — the worker just does what it's told and reports back via
 * notifyLaravel().
 */
export async function sessionsRoute(app: FastifyInstance, config: Config) {
  app.post(
    "/sessions",
    { onRequest: requireInternalSecret(config) },
    async (request, reply) => {
      const parsed = startBodySchema.safeParse(request.body);

      if (!parsed.success) {
        return reply.code(400).send({ error: "instance_id (UUID) is required" });
      }

      await startSession(parsed.data.instance_id, config, request.log);

      return reply.code(202).send({ started: true });
    }
  );

  app.delete(
    "/sessions/:instanceId",
    { onRequest: requireInternalSecret(config) },
    async (request, reply) => {
      const { instanceId } = request.params as { instanceId: string };

      await stopSession(instanceId);

      return reply.code(200).send({ stopped: true });
    }
  );
}
