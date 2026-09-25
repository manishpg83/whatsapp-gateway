import { describe, expect, it } from "vitest";
import { DisconnectReason } from "baileys";
import { RETRY_DELAYS_MS, decideOnClose } from "../src/whatsapp/closeReason.js";

describe("decideOnClose", () => {
  it("restarts silently after a fresh pairing", () => {
    expect(decideOnClose(DisconnectReason.restartRequired, true, 0)).toEqual({ action: "restart" });
  });

  it("stops as logged_out when the device was unlinked", () => {
    const decision = decideOnClose(DisconnectReason.loggedOut, true, 0);
    expect(decision).toMatchObject({ action: "stop", status: "logged_out" });
  });

  it("reports an expired QR when the session was never paired", () => {
    const decision = decideOnClose(DisconnectReason.timedOut, false, 0);
    expect(decision).toMatchObject({ action: "stop", status: "disconnected" });
    expect(decision.action === "stop" && decision.reason).toContain("QR code expired");
  });

  it("retries a network drop on a paired session, waiting longer each time", () => {
    expect(decideOnClose(DisconnectReason.connectionLost, true, 0)).toMatchObject({ action: "retry", delayMs: RETRY_DELAYS_MS[0] });
    expect(decideOnClose(DisconnectReason.connectionClosed, true, 2)).toMatchObject({ action: "retry", delayMs: RETRY_DELAYS_MS[2] });
    expect(decideOnClose(undefined, true, 1)).toMatchObject({ action: "retry", delayMs: RETRY_DELAYS_MS[1] });
  });

  it("gives up after the last retry", () => {
    const decision = decideOnClose(DisconnectReason.connectionLost, true, RETRY_DELAYS_MS.length);
    expect(decision).toMatchObject({ action: "stop", status: "disconnected" });
  });

  it("does not retry when the session was replaced or refused", () => {
    expect(decideOnClose(DisconnectReason.connectionReplaced, true, 0)).toMatchObject({ action: "stop" });
    expect(decideOnClose(DisconnectReason.forbidden, true, 0)).toMatchObject({ action: "stop" });
  });
});
