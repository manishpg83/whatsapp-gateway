import Fastify, { type FastifyInstance } from "fastify";
import type { Config } from "./config.js";
import { healthRoute } from "./routes/health.js";
import { pingRoute } from "./routes/ping.js";

/**
 * Builds (but does not start) the Fastify instance. Kept separate from
 * index.ts so tests can import it and send fake requests without binding
 * a real port.
 */
export async function buildApp(config: Config): Promise<FastifyInstance> {
  const app = Fastify({
    logger: {
      transport:
        process.env.NODE_ENV === "production"
          ? undefined
          : { target: "pino-pretty" },
    },
  });

  await app.register(healthRoute);
  await app.register((instance) => pingRoute(instance, config));

  return app;
}
