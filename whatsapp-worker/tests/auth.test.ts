import { describe, expect, it } from "vitest";
import { buildApp } from "../src/app.js";
import type { Config } from "../src/config.js";

const testConfig: Config = {
  HOST: "127.0.0.1",
  PORT: 3001,
  INTERNAL_API_SECRET: "test-secret-at-least-16-chars",
};

describe("GET /internal/ping (secret-protected)", () => {
  it("rejects a request with no secret header", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({ method: "GET", url: "/internal/ping" });

    expect(response.statusCode).toBe(401);

    await app.close();
  });

  it("rejects a request with the wrong secret", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({
      method: "GET",
      url: "/internal/ping",
      headers: { "x-internal-secret": "wrong-secret-value" },
    });

    expect(response.statusCode).toBe(401);

    await app.close();
  });

  it("accepts a request with the correct secret", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({
      method: "GET",
      url: "/internal/ping",
      headers: { "x-internal-secret": testConfig.INTERNAL_API_SECRET },
    });

    expect(response.statusCode).toBe(200);
    expect(response.json()).toEqual({ pong: true });

    await app.close();
  });
});
