import path from "node:path";
import { Readable } from "node:stream";
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
  const stream = Readable.from([]);
  const media = (mimeType: string, fileName: string | null = null) => ({
    path: `${INSTANCE}/out-a.bin`,
    mimeType,
    fileName,
    absolutePath: "/media/out-a.bin",
    stream,
  });

  it("builds a text message", () => {
    expect(buildOutgoingContent("text", "Hello", null)).toEqual({ text: "Hello" });
  });

  it("builds an image with a caption, streamed from the decrypted file", () => {
    expect(buildOutgoingContent("image", "Look", media("image/jpeg"))).toEqual({
      image: { stream },
      caption: "Look",
      mimetype: "image/jpeg",
    });
  });

  it("leaves the caption out when it is empty", () => {
    expect(buildOutgoingContent("video", "", media("video/mp4"))).toEqual({
      video: { stream },
      caption: undefined,
      mimetype: "video/mp4",
    });
  });

  it("sends a voice note as Ogg/Opus with ptt set", () => {
    expect(buildOutgoingContent("voice", "", media("audio/ogg"))).toEqual({
      audio: { stream },
      mimetype: "audio/ogg; codecs=opus",
      ptt: true,
    });
  });

  it("sends an audio file without ptt", () => {
    expect(buildOutgoingContent("audio", "", media("audio/mpeg"))).toEqual({
      audio: { stream },
      mimetype: "audio/mpeg",
    });
  });

  it("sends a document with its file name and caption", () => {
    expect(buildOutgoingContent("document", "Your invoice", media("application/pdf", "Invoice-42.pdf"))).toEqual({
      document: { stream },
      mimetype: "application/pdf",
      fileName: "Invoice-42.pdf",
      caption: "Your invoice",
    });
  });
});
