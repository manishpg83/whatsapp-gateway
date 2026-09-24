import path from "node:path";
import fs from "node:fs/promises";
import { createWriteStream } from "node:fs";
import { Transform } from "node:stream";
import { pipeline } from "node:stream/promises";
import type { Readable } from "node:stream";
import type { IncomingMedia } from "./incomingMessage.js";

/**
 * What happened to a message's media file:
 * - stored:    saved to disk; `path` is relative to MEDIA_STORAGE_PATH
 * - too_large: bigger than MAX_MEDIA_MB, deliberately not downloaded
 * - failed:    WhatsApp's download failed (e.g. media expired)
 */
export type MediaResult =
  | { status: "stored"; path: string; size: number }
  | { status: "too_large" | "failed"; path: null; size: number | null };

export class MediaTooLargeError extends Error {
  constructor() {
    super("Media file exceeds the size limit");
    this.name = "MediaTooLargeError";
  }
}

// File extension by MIME type. Documents use their own file name's
// extension first (see mediaFileName) — this is the fallback.
const EXTENSIONS: Record<string, string> = {
  "image/jpeg": "jpg",
  "image/png": "png",
  "image/gif": "gif",
  "image/webp": "webp",
  "video/mp4": "mp4",
  "video/3gpp": "3gp",
  "audio/ogg": "ogg",
  "audio/mpeg": "mp3",
  "audio/mp4": "m4a",
  "audio/aac": "aac",
  "audio/amr": "amr",
  "application/pdf": "pdf",
  "text/plain": "txt",
  "application/zip": "zip",
  "application/msword": "doc",
  "application/vnd.openxmlformats-officedocument.wordprocessingml.document": "docx",
  "application/vnd.ms-excel": "xls",
  "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": "xlsx",
  "application/vnd.ms-powerpoint": "ppt",
  "application/vnd.openxmlformats-officedocument.presentationml.presentation": "pptx",
};

/**
 * The name the file is saved under: WhatsApp's message id (unique per
 * instance) plus an extension. Never the sender's own file name — that's
 * untrusted input (could contain "../" or odd characters); the original
 * name is kept separately as metadata for display/download only.
 */
export function mediaFileName(whatsappMessageId: string, media: IncomingMedia): string {
  const safeId = whatsappMessageId.replace(/[^A-Za-z0-9_-]/g, "") || "media";

  const fromName = media.fileName ? path.extname(media.fileName).slice(1).toLowerCase() : "";
  const extension = /^[a-z0-9]{1,10}$/.test(fromName) ? fromName : (EXTENSIONS[media.mimeType] ?? "bin");

  return `${safeId}.${extension}`;
}

/**
 * Streams a download to MEDIA_STORAGE_PATH/<instanceId>/<file>, aborting
 * (and deleting the partial file) if it grows past maxBytes — the size
 * WhatsApp declares up front can't be fully trusted.
 */
export async function saveMediaStream(
  stream: Readable,
  storageRoot: string,
  instanceId: string,
  fileName: string,
  maxBytes: number
): Promise<{ path: string; size: number }> {
  const dir = path.join(storageRoot, instanceId);
  const fullPath = path.join(dir, fileName);
  let size = 0;

  await fs.mkdir(dir, { recursive: true });

  const limiter = new Transform({
    transform(chunk: Buffer, _encoding, callback) {
      size += chunk.length;
      callback(size > maxBytes ? new MediaTooLargeError() : null, chunk);
    },
  });

  try {
    await pipeline(stream, limiter, createWriteStream(fullPath));
  } catch (err) {
    await fs.rm(fullPath, { force: true }).catch(() => {});
    throw err;
  }

  // Forward slashes, so Laravel gets the same relative path on any OS.
  return { path: `${instanceId}/${fileName}`, size };
}
