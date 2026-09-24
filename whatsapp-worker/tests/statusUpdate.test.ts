import { describe, expect, it } from "vitest";
import type { WAMessageUpdate } from "baileys";
import { parseStatusUpdate } from "../src/whatsapp/statusUpdate.js";

function buildUpdate(overrides: { fromMe?: boolean; remoteJid?: string; id?: string | null; status?: number | null }): WAMessageUpdate {
  return {
    key: {
      fromMe: overrides.fromMe ?? true,
      remoteJid: overrides.remoteJid ?? "919999999999@s.whatsapp.net",
      id: overrides.id === undefined ? "WA-ID-1" : overrides.id,
    },
    update: overrides.status === null ? {} : { status: overrides.status ?? 3 },
  } as unknown as WAMessageUpdate;
}

describe("parseStatusUpdate", () => {
  it("reports DELIVERY_ACK (3) as delivered", () => {
    expect(parseStatusUpdate(buildUpdate({ status: 3 }))).toEqual({ whatsappMessageId: "WA-ID-1", status: "delivered" });
  });

  it("reports READ (4) and PLAYED (5) as read", () => {
    expect(parseStatusUpdate(buildUpdate({ status: 4 }))?.status).toBe("read");
    expect(parseStatusUpdate(buildUpdate({ status: 5 }))?.status).toBe("read");
  });

  it("ignores SERVER_ACK / PENDING / ERROR — Laravel already knows it was sent or failed", () => {
    for (const status of [0, 1, 2]) {
      expect(parseStatusUpdate(buildUpdate({ status }))).toBeNull();
    }
  });

  it("ignores receipts for messages we did not send", () => {
    expect(parseStatusUpdate(buildUpdate({ fromMe: false, status: 4 }))).toBeNull();
  });

  it("ignores groups and the status feed", () => {
    expect(parseStatusUpdate(buildUpdate({ remoteJid: "123-group@g.us", status: 4 }))).toBeNull();
    expect(parseStatusUpdate(buildUpdate({ remoteJid: "status@broadcast", status: 4 }))).toBeNull();
  });

  it("ignores updates without a status (e.g. edits) or without an id", () => {
    expect(parseStatusUpdate(buildUpdate({ status: null }))).toBeNull();
    expect(parseStatusUpdate(buildUpdate({ id: null, status: 4 }))).toBeNull();
  });
});
