import { createCipheriv, createDecipheriv, randomBytes } from "node:crypto";
import { createReadStream } from "node:fs";
import fs from "node:fs/promises";
import { Transform } from "node:stream";
import type { Readable } from "node:stream";

/**
 * Encrypted media files (privacy, CLAUDE.md §17). Laravel uses the SAME
 * format (backend/app/Services/MediaCrypto.php) with the same
 * MEDIA_ENCRYPTION_KEY, so either side can read what the other wrote.
 * Change one, change both.
 *
 * File format:
 *   "IMENC1"                                    6-byte marker
 *   then chunks of: nonce (12) | ciphertext | tag (16)
 * Each chunk is up to 64 KB of the original file, encrypted with
 * AES-256-GCM. The chunk's number and a "last chunk" flag are
 * authenticated with it, so chunks can't be reordered, dropped or cut off
 * unnoticed. The last chunk is always shorter than 64 KB (it may be
 * empty) — that's how a reader knows it is the last one.
 *
 * Files without the marker are old, unencrypted ones and are read as they are.
 */
export const MARKER = Buffer.from("IMENC1");
const CHUNK = 65536;
const NONCE = 12;
const TAG = 16;
const RECORD = NONCE + CHUNK + TAG; // one full chunk on disk

/** "base64:..." (32 random bytes) -> the key, or null if it isn't valid. */
export function parseMediaKey(value: string): Buffer | null {
  if (!value.startsWith("base64:")) {
    return null;
  }

  const key = Buffer.from(value.slice(7), "base64");

  return key.length === 32 ? key : null;
}

function aad(index: number, last: boolean): Buffer {
  const data = Buffer.alloc(5);
  data.writeUInt32BE(index, 0);
  data[4] = last ? 1 : 0;

  return data;
}

function encryptChunk(key: Buffer, plain: Buffer, index: number, last: boolean): Buffer {
  const nonce = randomBytes(NONCE);
  const cipher = createCipheriv("aes-256-gcm", key, nonce, { authTagLength: TAG });
  cipher.setAAD(aad(index, last));

  return Buffer.concat([nonce, cipher.update(plain), cipher.final(), cipher.getAuthTag()]);
}

function decryptChunk(key: Buffer, record: Buffer, index: number, last: boolean): Buffer {
  if (record.length < NONCE + TAG) {
    throw new Error("The encrypted media file is damaged");
  }

  const decipher = createDecipheriv("aes-256-gcm", key, record.subarray(0, NONCE), { authTagLength: TAG });
  decipher.setAAD(aad(index, last));
  decipher.setAuthTag(record.subarray(record.length - TAG));

  try {
    return Buffer.concat([decipher.update(record.subarray(NONCE, record.length - TAG)), decipher.final()]);
  } catch {
    throw new Error("The media file could not be decrypted (damaged, or a different MEDIA_ENCRYPTION_KEY)");
  }
}

/** Plain bytes in, encrypted file bytes out. */
export function createEncryptStream(key: Buffer): Transform {
  let buffer = Buffer.alloc(0);
  let index = 0;
  let markerWritten = false;

  const writeMarker = (stream: Transform) => {
    if (!markerWritten) {
      stream.push(MARKER);
      markerWritten = true;
    }
  };

  return new Transform({
    transform(chunk: Buffer, _encoding, callback) {
      writeMarker(this);
      buffer = Buffer.concat([buffer, chunk]);

      // A full 64 KB chunk is never the last one (see the format above).
      while (buffer.length >= CHUNK) {
        this.push(encryptChunk(key, buffer.subarray(0, CHUNK), index++, false));
        buffer = buffer.subarray(CHUNK);
      }

      callback();
    },
    flush(callback) {
      writeMarker(this); // an empty file still gets the marker
      this.push(encryptChunk(key, buffer, index, true));
      callback();
    },
  });
}

/** Encrypted file bytes in, plain bytes out. Errors if anything is off. */
export function createDecryptStream(key: Buffer): Transform {
  let buffer = Buffer.alloc(0);
  let markerChecked = false;
  let index = 0;

  return new Transform({
    transform(chunk: Buffer, _encoding, callback) {
      buffer = Buffer.concat([buffer, chunk]);

      try {
        if (!markerChecked) {
          if (buffer.length < MARKER.length) {
            return callback();
          }

          if (!buffer.subarray(0, MARKER.length).equals(MARKER)) {
            throw new Error("Not an encrypted media file");
          }

          buffer = buffer.subarray(MARKER.length);
          markerChecked = true;
        }

        // Only a full record is known not to be the last one, but it could
        // be followed by nothing — so keep it until more data arrives.
        while (buffer.length > RECORD) {
          this.push(decryptChunk(key, buffer.subarray(0, RECORD), index++, false));
          buffer = buffer.subarray(RECORD);
        }

        callback();
      } catch (err) {
        callback(err as Error);
      }
    },
    flush(callback) {
      try {
        if (!markerChecked || buffer.length >= RECORD) {
          // No marker, or the file ends on a full chunk: the last chunk is missing.
          throw new Error("The encrypted media file is damaged");
        }

        this.push(decryptChunk(key, buffer, index, true));
        callback();
      } catch (err) {
        callback(err as Error);
      }
    },
  });
}

export async function isEncryptedFile(filePath: string): Promise<boolean> {
  const handle = await fs.open(filePath, "r");

  try {
    const start = Buffer.alloc(MARKER.length);
    const { bytesRead } = await handle.read(start, 0, MARKER.length, 0);

    return bytesRead === MARKER.length && start.equals(MARKER);
  } finally {
    await handle.close();
  }
}

/**
 * The original file's bytes as a stream — decrypted if the file is
 * encrypted, as they are if it's an old unencrypted file.
 */
export async function openMediaStream(filePath: string, key: Buffer): Promise<Readable> {
  if (!(await isEncryptedFile(filePath))) {
    return createReadStream(filePath);
  }

  const source = createReadStream(filePath);
  const decrypt = createDecryptStream(key);
  source.on("error", (err) => decrypt.destroy(err));
  decrypt.on("close", () => source.destroy());

  return source.pipe(decrypt);
}
