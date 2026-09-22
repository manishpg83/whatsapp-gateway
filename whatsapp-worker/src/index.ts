import { loadConfig } from "./config.js";
import { buildApp } from "./app.js";
import { reconnectActiveSessions } from "./whatsapp/reconnectAll.js";

const config = loadConfig();
const app = await buildApp(config);

try {
  await app.listen({ host: config.HOST, port: config.PORT });
} catch (err) {
  app.log.error(err);
  process.exit(1);
}

// Fire-and-forget: don't hold up startup on this, and a failure here
// (e.g. Laravel not running yet) shouldn't crash the worker.
void reconnectActiveSessions(config, app.log);
