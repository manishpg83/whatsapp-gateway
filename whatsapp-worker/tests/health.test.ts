import { describe, expect, it } from "vitest";
import { buildApp } from "../src/app.js";
import { testConfig } from "./testConfig.js";

describe("GET /health", () => {
  it("responds 200 with status ok, no secret required", async () => {
    const app = await buildApp(testConfig);

    const response = await app.inject({ method: "GET", url: "/health" });

    expect(response.statusCode).toBe(200);
    expect(response.json()).toEqual({ status: "ok" });

    await app.close();
  });
});
