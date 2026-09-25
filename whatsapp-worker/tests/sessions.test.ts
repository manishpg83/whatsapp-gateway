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

describe("POST /sessions/:instanceId/disconnect", () => {
  it("rejects a request with no secret", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({ method: "POST", url: `/sessions/${randomUUID()}/disconnect` });

    expect(response.statusCode).toBe(401);

    await app.close();
  });

  it("accepts a request for an unknown session id (nothing to disconnect)", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({
      method: "POST",
      url: `/sessions/${randomUUID()}/disconnect`,
      headers: { "x-internal-secret": testConfig.INTERNAL_API_SECRET },
    });

    expect(response.statusCode).toBe(200);
    expect(response.json()).toEqual({ disconnected: true });

    await app.close();
  });
});

describe("POST /sessions/:instanceId/check-numbers", () => {
  const url = () => `/sessions/${randomUUID()}/check-numbers`;
  const headers = { "x-internal-secret": testConfig.INTERNAL_API_SECRET };

  it("rejects a request with no secret", async () => {
    const app = await buildApp(testConfig);
    const response = await app.inject({ method: "POST", url: url(), payload: { numbers: ["919999999999"] } });
    expect(response.statusCode).toBe(401);
    await app.close();
  });

  it("rejects non-numeric, empty, or too many numbers", async () => {
    const app = await buildApp(testConfig);

    for (const numbers of [["+91 99999"], [], Array(21).fill("919999999999")]) {
      const response = await app.inject({ method: "POST", url: url(), headers, payload: { numbers } });
      expect(response.statusCode).toBe(400);
    }

    await app.close();
  });

  it("returns 409 when the instance has no active session", async () => {
    const app = await buildApp(testConfig);
    const response = await app.inject({ method: "POST", url: url(), headers, payload: { numbers: ["919999999999"] } });
    expect(response.statusCode).toBe(409);
    await app.close();
  });
});

describe("POST /sessions/:instanceId/messages", () => {
  // A request with no active session for that instance never reaches
  // Baileys/the network at all (it 409s before calling sendMessage), so
  // — unlike POST /sessions — this whole route is safe to exercise here.

  it("rejects a request with no secret", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({
      method: "POST",
      url: `/sessions/${randomUUID()}/messages`,
      payload: { to: "919999999999", message: "hi" },
    });

    expect(response.statusCode).toBe(401);

    await app.close();
  });

  it("rejects a request with a non-numeric to", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({
      method: "POST",
      url: `/sessions/${randomUUID()}/messages`,
      headers: { "x-internal-secret": testConfig.INTERNAL_API_SECRET },
      payload: { to: "not-a-number", message: "hi" },
    });

    expect(response.statusCode).toBe(400);

    await app.close();
  });

  it("rejects a request with an empty message", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({
      method: "POST",
      url: `/sessions/${randomUUID()}/messages`,
      headers: { "x-internal-secret": testConfig.INTERNAL_API_SECRET },
      payload: { to: "919999999999", message: "" },
    });

    expect(response.statusCode).toBe(400);

    await app.close();
  });

  it("rejects a media message with no media", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({
      method: "POST",
      url: `/sessions/${randomUUID()}/messages`,
      headers: { "x-internal-secret": testConfig.INTERNAL_API_SECRET },
      payload: { to: "919999999999", type: "image", message: "caption" },
    });

    expect(response.statusCode).toBe(400);

    await app.close();
  });

  it("rejects a media path outside the instance's own folder", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({
      method: "POST",
      url: `/sessions/${randomUUID()}/messages`,
      headers: { "x-internal-secret": testConfig.INTERNAL_API_SECRET },
      payload: {
        to: "919999999999",
        type: "image",
        message: "",
        media: { path: "../../.env", mime_type: "image/jpeg" },
      },
    });

    expect(response.statusCode).toBe(400);
    expect(response.json()).toEqual({ error: "Invalid media path" });

    await app.close();
  });

  it("rejects a media file that doesn't exist", async () => {
    const app = await buildApp(testConfig);
    const instanceId = randomUUID();

    const response = await app.inject({
      method: "POST",
      url: `/sessions/${instanceId}/messages`,
      headers: { "x-internal-secret": testConfig.INTERNAL_API_SECRET },
      payload: {
        to: "919999999999",
        type: "document",
        message: "",
        media: { path: `${instanceId}/out-missing.pdf`, mime_type: "application/pdf" },
      },
    });

    expect(response.statusCode).toBe(400);
    expect(response.json()).toEqual({ error: "Media file not found" });

    await app.close();
  });

  it("returns 409 when the instance has no active session", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({
      method: "POST",
      url: `/sessions/${randomUUID()}/messages`,
      headers: { "x-internal-secret": testConfig.INTERNAL_API_SECRET },
      payload: { to: "919999999999", message: "hi" },
    });

    expect(response.statusCode).toBe(409);

    await app.close();
  });
});
