import { afterEach, beforeEach, describe, expect, it } from "vitest";
import os from "node:os";
import path from "node:path";
import fs from "node:fs/promises";
import { Readable } from "node:stream";
import { pipeline } from "node:stream/promises";
import {
  createDecryptStream,
  createEncryptStream,
  isEncryptedFile,
  openMediaStream,
  parseMediaKey,
} from "../src/whatsapp/mediaCrypto.js";

const KEY = Buffer.alloc(32);

async function readAll(stream: Readable): Promise<Buffer> {
  const chunks: Buffer[] = [];
  for await (const chunk of stream) chunks.push(chunk as Buffer);
  return Buffer.concat(chunks);
}

// Feeds the data in odd-sized pieces, like a real network download would.
function pieces(data: Buffer, size = 10000): Readable {
  const parts: Buffer[] = [];
  for (let i = 0; i < data.length; i += size) parts.push(data.subarray(i, i + size));
  return Readable.from(parts);
}

const encrypt = (data: Buffer, key = KEY) => readAll(pieces(data).pipe(createEncryptStream(key)));
const decrypt = (data: Buffer, key = KEY) => readAll(pieces(data, 7777).pipe(createDecryptStream(key)));

describe("parseMediaKey", () => {
  it("accepts base64: + 32 bytes only", () => {
    expect(parseMediaKey("base64:" + Buffer.alloc(32, 1).toString("base64"))?.length).toBe(32);
    expect(parseMediaKey("base64:" + Buffer.alloc(16).toString("base64"))).toBeNull();
    expect(parseMediaKey("not-a-key")).toBeNull();
    expect(parseMediaKey("")).toBeNull();
  });
});

describe("media encryption", () => {
  it("round-trips files of every size, around the 64 KB chunk boundary too", async () => {
    for (const size of [0, 1, 65535, 65536, 65537, 200000]) {
      const data = Buffer.alloc(size, 7);
      expect((await decrypt(await encrypt(data))).equals(data), `size ${size}`).toBe(true);
    }
  });

  it("detects a changed byte", async () => {
    const encrypted = await encrypt(Buffer.from("secret photo"));
    encrypted[20] ^= 1;

    await expect(decrypt(encrypted)).rejects.toThrow();
  });

  it("detects a file cut off at a chunk boundary", async () => {
    const encrypted = await encrypt(Buffer.alloc(65536 * 2));
    const cut = encrypted.subarray(0, 6 + 2 * (12 + 65536 + 16));

    await expect(decrypt(cut)).rejects.toThrow();
  });

  it("can't be decrypted with a different key", async () => {
    const encrypted = await encrypt(Buffer.from("secret"));

    await expect(decrypt(encrypted, Buffer.alloc(32, 9))).rejects.toThrow(/could not be decrypted/);
  });
});

describe("openMediaStream", () => {
  let dir: string;

  beforeEach(async () => {
    dir = await fs.mkdtemp(path.join(os.tmpdir(), "wa-crypto-test-"));
  });

  afterEach(async () => {
    await fs.rm(dir, { recursive: true, force: true });
  });

  it("decrypts an encrypted file", async () => {
    const file = path.join(dir, "enc.jpg");
    await pipeline(Readable.from([Buffer.from("picture bytes")]), createEncryptStream(KEY), (await fs.open(file, "w")).createWriteStream());

    expect(await isEncryptedFile(file)).toBe(true);
    expect((await readAll(await openMediaStream(file, KEY))).toString()).toBe("picture bytes");
  });

  it("reads an old unencrypted file as it is", async () => {
    const file = path.join(dir, "old.jpg");
    await fs.writeFile(file, "old plain bytes");

    expect(await isEncryptedFile(file)).toBe(false);
    expect((await readAll(await openMediaStream(file, KEY))).toString()).toBe("old plain bytes");
  });
});
