import type { Config } from "../src/config.js";

// Shared fixture so every test file uses the same fake config, instead of
// each one hand-rolling its own copy.
export const testConfig: Config = {
  HOST: "127.0.0.1",
  PORT: 3001,
  INTERNAL_API_SECRET: "test-secret-at-least-16-chars",
  LARAVEL_CALLBACK_URL: "http://127.0.0.1:8000/internal/worker/events",
  LARAVEL_BASE_URL: "http://127.0.0.1:8000",
  SESSION_STORAGE_PATH: "./tests/tmp-whatsapp-secrets",
  MEDIA_STORAGE_PATH: "./tests/tmp-whatsapp-media",
  MAX_MEDIA_MB: 100,
};
