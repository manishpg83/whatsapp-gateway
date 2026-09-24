import path from "node:path";
import { describe, expect, it } from "vitest";
import { buildOutgoingContent, resolveMediaPath } from "../src/whatsapp/outgoingMessage.js";

const INSTANCE = "0f8e6a52-1c5d-4b7e-9a3f-2d6c8b1e4a90";
const ROOT = path.join("C:", "whatsapp-media");

describe("resolveMediaPath", () => {
  it("accepts a plain file inside the instance's own folder", () => {
    expect(resolveMediaPath(ROOT, INSTANCE, `${INSTANCE}/out-abc_1.jpg`)).toBe(path.join(ROOT, INSTANCE, "out-abc_1.jpg"));
  });

  it("rejects another instance's folder, traversal and sub-folders", () => {
    for (const bad of [
      "11111111-1111-1111-1111-111111111111/out-a.jpg",
      `${INSTANCE}/../secret.jpg`,
      `${INSTANCE}/sub/out-a.jpg`,
      "../../.env",
      `${INSTANCE}/out-a`,
    ]) {
      expect(resolveMediaPath(ROOT, INSTANCE, bad), bad).toBeNull();
    }
  });
});

describe("buildOutgoingContent", () => {
  const media = (mimeType: string, fileName: string | null = null) => ({
    path: `${INSTANCE}/out-a.bin`,
    mimeType,
    fileName,
    absolutePath: "/media/out-a.bin",
  });

  it("builds a text message", () => {
    expect(buildOutgoingContent("text", "Hello", null)).toEqual({ text: "Hello" });
  });

  it("builds an image with a caption, streamed from the local file", () => {
    expect(buildOutgoingContent("image", "Look", media("image/jpeg"))).toEqual({
      image: { url: "/media/out-a.bin" },
      caption: "Look",
      mimetype: "image/jpeg",
    });
  });

  it("leaves the caption out when it is empty", () => {
    expect(buildOutgoingContent("video", "", media("video/mp4"))).toEqual({
      video: { url: "/media/out-a.bin" },
      caption: undefined,
      mimetype: "video/mp4",
    });
  });

  it("sends a voice note as Ogg/Opus with ptt set", () => {
    expect(buildOutgoingContent("voice", "", media("audio/ogg"))).toEqual({
      audio: { url: "/media/out-a.bin" },
      mimetype: "audio/ogg; codecs=opus",
      ptt: true,
    });
  });

  it("sends an audio file without ptt", () => {
    expect(buildOutgoingContent("audio", "", media("audio/mpeg"))).toEqual({
      audio: { url: "/media/out-a.bin" },
      mimetype: "audio/mpeg",
    });
  });

  it("sends a document with its file name and caption", () => {
    expect(buildOutgoingContent("document", "Your invoice", media("application/pdf", "Invoice-42.pdf"))).toEqual({
      document: { url: "/media/out-a.bin" },
      mimetype: "application/pdf",
      fileName: "Invoice-42.pdf",
      caption: "Your invoice",
    });
  });
});
