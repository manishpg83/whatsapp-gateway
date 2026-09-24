import path from "node:path";
import type { AnyMessageContent } from "baileys";

export const OUTGOING_TYPES = ["text", "image", "video", "audio", "voice", "document"] as const;
export type OutgoingType = (typeof OUTGOING_TYPES)[number];

export type OutgoingMedia = {
  path: string; // relative to MEDIA_STORAGE_PATH, e.g. "<instance_id>/out-<uuid>.jpg"
  mimeType: string;
  fileName: string | null;
};

/**
 * Only accept a media path inside this instance's own folder, with a plain
 * file name — Laravel already checked it, but never trust a path blindly
 * (no "../", no other instance's files). Returns the absolute path, or
 * null if the path is not acceptable.
 */
export function resolveMediaPath(storageRoot: string, instanceId: string, relativePath: string): string | null {
  const pattern = new RegExp(`^${instanceId.replace(/[^A-Za-z0-9-]/g, "")}/[A-Za-z0-9_-]+\\.[a-z0-9]{1,10}$`);

  if (!pattern.test(relativePath)) {
    return null;
  }

  return path.join(storageRoot, ...relativePath.split("/"));
}

/**
 * Builds what Baileys' sendMessage() expects for each type. Media is
 * passed as a local file path ({ url }) — Baileys streams it from disk, so
 * large files are never loaded fully into memory. A pure function so it
 * can be unit tested without a WhatsApp connection.
 */
export function buildOutgoingContent(
  type: OutgoingType,
  text: string,
  media: (OutgoingMedia & { absolutePath: string }) | null
): AnyMessageContent {
  if (type === "text" || !media) {
    return { text };
  }

  const file = { url: media.absolutePath };
  const caption = text !== "" ? text : undefined;

  switch (type) {
    case "image":
      return { image: file, caption, mimetype: media.mimeType };
    case "video":
      return { video: file, caption, mimetype: media.mimeType };
    case "audio":
      return { audio: file, mimetype: media.mimeType };
    case "voice":
      // A playable voice note must be Ogg/Opus with ptt ("push to talk") set.
      return { audio: file, mimetype: "audio/ogg; codecs=opus", ptt: true };
    case "document":
      return {
        document: file,
        mimetype: media.mimeType,
        fileName: media.fileName ?? path.basename(media.absolutePath),
        caption,
      };
  }
}
