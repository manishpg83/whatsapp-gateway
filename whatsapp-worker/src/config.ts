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
  // Laravel's base URL, used once at boot to ask "which instances should
  // currently be connected?" so they can be reconnected automatically
  // (see reconnectAll.ts) instead of staying stuck until someone clicks
  // "Reconnect" by hand.
  LARAVEL_BASE_URL: z.string().url().default("http://127.0.0.1:8000"),
  // Baileys auth files live here, one sub-folder per instance_id. Must stay
  // outside the repo and outside C:\xampp\htdocs (see CLAUDE.md §4/§9) —
  // Apache serves htdocs, so anything under it could become web-reachable.
  SESSION_STORAGE_PATH: z.string().default("C:\\whatsapp-secrets"),
  // Received media (images, voice notes, documents, ...) is saved here, one
  // sub-folder per instance_id. Same rule: outside the repo and htdocs.
  // Laravel reads the same folder (WHATSAPP_MEDIA_PATH in backend/.env), so
  // both must point to the same place — they run on the same machine.
  MEDIA_STORAGE_PATH: z.string().default("C:\\whatsapp-media"),
  // Bigger incoming files are not downloaded (recorded as "too large").
  MAX_MEDIA_MB: z.coerce.number().positive().default(100),
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
