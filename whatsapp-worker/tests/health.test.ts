import { describe, expect, it } from "vitest";
import { buildApp } from "../src/app.js";
import type { Config } from "../src/config.js";

const testConfig: Config = {
  HOST: "127.0.0.1",
  PORT: 3001,
  INTERNAL_API_SECRET: "test-secret-at-least-16-chars",
};

describe("GET /health", () => {
  it("responds 200 with status ok, no secret required", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({ method: "GET", url: "/health" });

    expect(response.statusCode).toBe(200);
    expect(response.json()).toEqual({ status: "ok" });

    await app.close();
  });
});
