import { randomUUID } from "node:crypto";
import { describe, expect, it } from "vitest";
import { buildApp } from "../src/app.js";
import { testConfig } from "./testConfig.js";

// These tests only exercise the auth/validation layer of the sessions
// routes. A request that passes both checks would make the worker open a
// real Baileys connection to WhatsApp's servers — that's verified
// manually with a live QR scan, not here.

describe("POST /sessions", () => {
  it("rejects a request with no secret", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({
      method: "POST",
      url: "/sessions",
      payload: { instance_id: randomUUID() },
    });

    expect(response.statusCode).toBe(401);

    await app.close();
  });

  it("rejects a request with the wrong secret", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({
      method: "POST",
      url: "/sessions",
      headers: { "x-internal-secret": "wrong-secret-value" },
      payload: { instance_id: randomUUID() },
    });

    expect(response.statusCode).toBe(401);

    await app.close();
  });

  it("rejects a request with the correct secret but no instance_id", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({
      method: "POST",
      url: "/sessions",
      headers: { "x-internal-secret": testConfig.INTERNAL_API_SECRET },
      payload: {},
    });

    expect(response.statusCode).toBe(400);

    await app.close();
  });
});

describe("DELETE /sessions/:instanceId", () => {
  it("rejects a request with no secret", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({ method: "DELETE", url: `/sessions/${randomUUID()}` });

    expect(response.statusCode).toBe(401);

    await app.close();
  });

  it("accepts a request for an unknown session id (nothing to stop)", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({
      method: "DELETE",
      url: `/sessions/${randomUUID()}`,
      headers: { "x-internal-secret": testConfig.INTERNAL_API_SECRET },
    });

    expect(response.statusCode).toBe(200);
    expect(response.json()).toEqual({ stopped: true });

    await app.close();
  });
});
