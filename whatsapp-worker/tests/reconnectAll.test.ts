import { beforeEach, describe, expect, it, vi } from "vitest";
import { testConfig } from "./testConfig.js";

// Mocked so this test never opens a real Baileys socket — it only checks
// that reconnectActiveSessions() calls Laravel correctly and forwards
// each returned instance_id to startSession().
vi.mock("../src/whatsapp/sessionManager.js", () => ({
  startSession: vi.fn(),
}));

import { startSession } from "../src/whatsapp/sessionManager.js";
import { reconnectActiveSessions } from "../src/whatsapp/reconnectAll.js";

const fakeLogger = {
  info: vi.fn(),
  error: vi.fn(),
  warn: vi.fn(),
  debug: vi.fn(),
  trace: vi.fn(),
  fatal: vi.fn(),
  silent: vi.fn(),
  child: () => fakeLogger,
  level: "info",
} as unknown as import("fastify").FastifyBaseLogger;

describe("reconnectActiveSessions", () => {
  beforeEach(() => {
    vi.mocked(startSession).mockClear();
  });

  it("fetches active instance ids from Laravel with the internal secret and reconnects each one", async () => {
    const fetchMock = vi
      .spyOn(global, "fetch")
      .mockResolvedValue(new Response(JSON.stringify({ instance_ids: ["id-1", "id-2"] }), { status: 200 }));

    await reconnectActiveSessions(testConfig, fakeLogger);

    expect(fetchMock).toHaveBeenCalledWith(
      `${testConfig.LARAVEL_BASE_URL}/internal/worker/sessions`,
      expect.objectContaining({
        headers: { "X-Internal-Secret": testConfig.INTERNAL_API_SECRET },
      })
    );
    expect(startSession).toHaveBeenCalledTimes(2);
    expect(startSession).toHaveBeenNthCalledWith(1, "id-1", testConfig, fakeLogger);
    expect(startSession).toHaveBeenNthCalledWith(2, "id-2", testConfig, fakeLogger);

    fetchMock.mockRestore();
  });

  it("does nothing (and does not throw) if Laravel is unreachable", async () => {
    const fetchMock = vi.spyOn(global, "fetch").mockRejectedValue(new Error("Connection refused"));

    await expect(reconnectActiveSessions(testConfig, fakeLogger)).resolves.toBeUndefined();
    expect(startSession).not.toHaveBeenCalled();

    fetchMock.mockRestore();
  });

  it("does nothing if Laravel responds with an error status", async () => {
    const fetchMock = vi.spyOn(global, "fetch").mockResolvedValue(new Response("", { status: 500 }));

    await reconnectActiveSessions(testConfig, fakeLogger);

    expect(startSession).not.toHaveBeenCalled();

    fetchMock.mockRestore();
  });
});
