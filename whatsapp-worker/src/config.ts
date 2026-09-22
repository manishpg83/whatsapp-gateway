import { z } from "zod";

/**
 * All environment variables the worker needs, validated once at startup.
 * If something required is missing or wrong, we fail fast with a clear
 * error instead of crashing later in a confusing place.
 */
const envSchema = z.object({
  PORT: z.coerce.number().int().positive().default(3001),
  HOST: z.string().default("127.0.0.1"),
  // Shared secret with Laravel. No default on purpose: the worker must
  // refuse to start rather than run with a guessable/empty secret.
  INTERNAL_API_SECRET: z.string().min(16, "INTERNAL_API_SECRET must be at least 16 characters"),
  // Where Laravel's internal webhook lives, for QR/connection callbacks.
  LARAVEL_CALLBACK_URL: z.string().url().default("http://127.0.0.1:8000/internal/worker/events"),
  // Baileys auth files live here, one sub-folder per instance_id. Must stay
  // outside the repo and outside C:\xampp\htdocs (see CLAUDE.md §4/§9) —
  // Apache serves htdocs, so anything under it could become web-reachable.
  SESSION_STORAGE_PATH: z.string().default("C:\\whatsapp-secrets"),
});

export type Config = z.infer<typeof envSchema>;

export function loadConfig(env: NodeJS.ProcessEnv = process.env): Config {
  const result = envSchema.safeParse(env);

  if (!result.success) {
    console.error("Invalid worker configuration:");
    console.error(result.error.format());
    throw new Error("Worker failed to start: invalid environment configuration.");
  }

  return result.data;
}
