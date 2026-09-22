import { createHash, timingSafeEqual } from "node:crypto";
import type { FastifyReply, FastifyRequest } from "fastify";
import type { Config } from "./config.js";

const HEADER_NAME = "x-internal-secret";

/**
 * Compares two strings in constant time, regardless of length.
 *
 * We hash both values to a fixed-length digest first: timingSafeEqual()
 * throws if given buffers of different lengths, and comparing raw
 * variable-length strings directly would leak the secret's length. Hashing
 * first avoids both problems.
 */
function secretsMatch(a: string, b: string): boolean {
  const hashA = createHash("sha256").update(a).digest();
  const hashB = createHash("sha256").update(b).digest();
  return timingSafeEqual(hashA, hashB);
}

/**
 * Fastify onRequest hook. Rejects any request that doesn't carry the
 * correct X-Internal-Secret header. Attach this to routes that Laravel
 * calls internally — never to /health.
 */
export function requireInternalSecret(config: Config) {
  return async (request: FastifyRequest, reply: FastifyReply) => {
    const provided = request.headers[HEADER_NAME];

    if (typeof provided !== "string" || !secretsMatch(provided, config.INTERNAL_API_SECRET)) {
      reply.code(401).send({ error: "Unauthorized" });
    }
  };
}
