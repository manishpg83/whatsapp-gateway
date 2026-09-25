import fs from "node:fs/promises";
import type { FastifyInstance } from "fastify";
import { z } from "zod";
import { requireInternalSecret } from "../auth.js";
import type { Config } from "../config.js";
import { OUTGOING_TYPES, buildOutgoingContent, resolveMediaPath } from "../whatsapp/outgoingMessage.js";
import { SessionNotActiveError, disconnectSession, sendMessage, startSession, stopSession } from "../whatsapp/sessionManager.js";

const startBodySchema = z.object({
  instance_id: z.string().uuid(),
});

const sendMessageBodySchema = z
  .object({
    to: z.string().regex(/^\d{7,15}$/, "to must be digits only (7-15 of them)"),
    type: z.enum(OUTGOING_TYPES).default("text"),
    // The text, or the caption for media (may be empty for media).
    message: z.string().max(4096).default(""),
    media: z
      .object({
        path: z.string().max(255),
        mime_type: z.string().max(255),
        file_name: z.string().max(255).nullable().optional(),
      })
      .nullable()
      .optional(),
  })
  .refine((body) => (body.type === "text" ? body.message.length > 0 : Boolean(body.media)), {
    message: "Text messages need a message; media messages need media",
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

  // Close the connection but keep the credentials (Reconnect = no QR).
  app.post(
    "/sessions/:instanceId/disconnect",
    { onRequest: requireInternalSecret(config) },
    async (request, reply) => {
      const { instanceId } = request.params as { instanceId: string };

      await disconnectSession(instanceId);

      return reply.code(200).send({ disconnected: true });
    }
  );

  // Log out completely and delete the credentials (Reconnect = new QR).
  app.delete(
    "/sessions/:instanceId",
    { onRequest: requireInternalSecret(config) },
    async (request, reply) => {
      const { instanceId } = request.params as { instanceId: string };

      await stopSession(instanceId, config);

      return reply.code(200).send({ stopped: true });
    }
  );

  app.post(
    "/sessions/:instanceId/messages",
    { onRequest: requireInternalSecret(config) },
    async (request, reply) => {
      const { instanceId } = request.params as { instanceId: string };
      const parsed = sendMessageBodySchema.safeParse(request.body);

      if (!parsed.success) {
        return reply.code(400).send({ error: parsed.error.issues[0]?.message ?? "Invalid body" });
      }

      const { to, type, message, media } = parsed.data;
      let content;

      if (type === "text" || !media) {
        content = buildOutgoingContent("text", message, null);
      } else {
        const absolutePath = resolveMediaPath(config.MEDIA_STORAGE_PATH, instanceId, media.path);

        if (!absolutePath) {
          return reply.code(400).send({ error: "Invalid media path" });
        }

        try {
          await fs.access(absolutePath);
        } catch {
          return reply.code(400).send({ error: "Media file not found" });
        }

        content = buildOutgoingContent(type, message, {
          path: media.path,
          mimeType: media.mime_type,
          fileName: media.file_name ?? null,
          absolutePath,
        });
      }

      try {
        const messageId = await sendMessage(instanceId, to, content);
        return reply.code(200).send({ message_id: messageId });
      } catch (err) {
        if (err instanceof SessionNotActiveError) {
          return reply.code(409).send({ error: err.message });
        }

        request.log.error({ err, instanceId }, "Failed to send message");
        return reply.code(502).send({ error: "Failed to send message" });
      }
    }
  );
}
