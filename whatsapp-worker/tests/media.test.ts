import { afterEach, beforeEach, describe, expect, it } from "vitest";
import os from "node:os";
import path from "node:path";
import fs from "node:fs/promises";
import { Readable } from "node:stream";
import { MediaTooLargeError, mediaFileName, saveMediaStream } from "../src/whatsapp/media.js";
import { MARKER, openMediaStream } from "../src/whatsapp/mediaCrypto.js";

const KEY = Buffer.alloc(32);

async function readAll(stream: Readable): Promise<Buffer> {
  const chunks: Buffer[] = [];
  for await (const chunk of stream) chunks.push(chunk as Buffer);
  return Buffer.concat(chunks);
}

describe("mediaFileName", () => {
  it("uses the message id plus an extension from the mime type", () => {
    expect(mediaFileName("3EB0ABC", { mimeType: "image/jpeg", fileName: null, size: null })).toBe("3EB0ABC.jpg");
    expect(mediaFileName("3EB0ABC", { mimeType: "audio/ogg", fileName: null, size: null })).toBe("3EB0ABC.ogg");
  });

  it("uses a document's own extension, but never its name", () => {
    expect(mediaFileName("ID1", { mimeType: "application/octet-stream", fileName: "Report Q3.XLSX", size: null })).toBe("ID1.xlsx");
  });

  it("strips dangerous characters from the id and falls back to .bin", () => {
    expect(mediaFileName("../../evil", { mimeType: "application/x-unknown", fileName: null, size: null })).toBe("evil.bin");
  });

  it("ignores a weird extension on the sender's file name", () => {
    expect(mediaFileName("ID1", { mimeType: "application/pdf", fileName: "x.p/../df", size: null })).toBe("ID1.pdf");
  });
});

describe("saveMediaStream", () => {
  let root: string;

  beforeEach(async () => {
    root = await fs.mkdtemp(path.join(os.tmpdir(), "wa-media-test-"));
  });

  afterEach(async () => {
    await fs.rm(root, { recursive: true, force: true });
  });

  it("saves the stream encrypted to <root>/<instanceId>/<file> and returns a forward-slash relative path", async () => {
    const result = await saveMediaStream(Readable.from([Buffer.from("hello "), Buffer.from("world")]), root, "inst-1", "ID1.txt", 1000, KEY);
    const fullPath = path.join(root, "inst-1", "ID1.txt");
    const onDisk = await fs.readFile(fullPath);

    expect(result).toEqual({ path: "inst-1/ID1.txt", size: 11 });
    expect(onDisk.subarray(0, MARKER.length).equals(MARKER)).toBe(true);
    expect(onDisk.includes("hello world")).toBe(false);
    expect((await readAll(await openMediaStream(fullPath, KEY))).toString()).toBe("hello world");
  });

  it("aborts and deletes the partial file when the stream exceeds the limit", async () => {
    const big = Readable.from([Buffer.alloc(600), Buffer.alloc(600)]);

    await expect(saveMediaStream(big, root, "inst-1", "ID2.bin", 1000, KEY)).rejects.toBeInstanceOf(MediaTooLargeError);
    await expect(fs.access(path.join(root, "inst-1", "ID2.bin"))).rejects.toThrow();
  });
});
