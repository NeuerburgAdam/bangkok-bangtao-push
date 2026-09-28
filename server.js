// Push sender for the Bangkok → Bang Tao trip app.
// Phones register a subscription and upload their reminder list; a scheduled job hits /tick
// every ~10 minutes and this service sends whatever is due.
const http = require("http");
const webpush = require("web-push");
const { createClient } = require("redis");

const { VAPID_PUBLIC, VAPID_PRIVATE, VAPID_SUBJECT = "mailto:trip@example.com", TICK_KEY, REDIS_URL, PORT = 10000 } = process.env;
const ORIGINS = ["https://neuerburgadam.github.io", "http://localhost:8787"];
webpush.setVapidDetails(VAPID_SUBJECT, VAPID_PUBLIC, VAPID_PRIVATE);

const db = createClient({ url: REDIS_URL });
db.on("error", e => console.error("redis", e.message));
const ready = db.connect();

const ID_RE = /^[a-f0-9-]{20,64}$/i;
const MAX_ITEMS = 400;
const STALE_MS = 2 * 60 * 60 * 1000; // never send reminders more than 2h late

function send(res, code, body, origin) {
  const h = { "Content-Type": "application/json" };
  if (ORIGINS.includes(origin)) Object.assign(h, { "Access-Control-Allow-Origin": origin, "Access-Control-Allow-Headers": "content-type", "Access-Control-Allow-Methods": "GET,POST,OPTIONS", Vary: "Origin" });
  res.writeHead(code, h); res.end(JSON.stringify(body));
}
function readJSON(req) {
  return new Promise((resolve, reject) => {
    let s = ""; req.on("data", c => { s += c; if (s.length > 256e3) { reject(new Error("too large")); req.destroy(); } });
    req.on("end", () => { try { resolve(JSON.parse(s || "{}")); } catch (e) { reject(e); } });
  });
}
async function push(id, payload) {
  const sub = await db.get(`sub:${id}`); if (!sub) return "no-sub";
  try { await webpush.sendNotification(JSON.parse(sub), JSON.stringify(payload), { TTL: 3600 }); return "sent"; }
  catch (e) { if (e.statusCode === 404 || e.statusCode === 410) { await db.del(`sub:${id}`); await db.sRem("devices", id); return "expired"; } throw e; }
}

http.createServer(async (req, res) => {
  const origin = req.headers.origin, url = new URL(req.url, "http://x");
  try {
    await ready;
    if (req.method === "OPTIONS") return send(res, 204, {}, origin);
    if (req.method === "GET" && url.pathname === "/health") return send(res, 200, { ok: true }, origin);
    if (req.method === "GET" && url.pathname === "/vapid") return send(res, 200, { key: VAPID_PUBLIC }, origin);

    if (req.method === "POST" && url.pathname === "/subscribe") {
      const { id, sub } = await readJSON(req);
      if (!ID_RE.test(id || "") || !sub?.endpoint) return send(res, 400, { error: "bad request" }, origin);
      await db.set(`sub:${id}`, JSON.stringify(sub)); await db.sAdd("devices", id);
      return send(res, 200, { ok: true }, origin);
    }
    if (req.method === "POST" && url.pathname === "/reminders") {
      const { id, items } = await readJSON(req);
      if (!ID_RE.test(id || "") || !Array.isArray(items)) return send(res, 400, { error: "bad request" }, origin);
      const clean = items.slice(0, MAX_ITEMS).filter(r => r && r.key && Date.parse(r.at))
        .map(r => ({ key: String(r.key).slice(0, 80), at: new Date(r.at).toISOString(), title: String(r.title || "").slice(0, 80), body: String(r.body || "").slice(0, 240), url: String(r.url || "./").slice(0, 120) }));
      const sent = new Set(await db.sMembers(`sent:${id}`));
      await db.set(`rem:${id}`, JSON.stringify(clean));
      return send(res, 200, { ok: true, stored: clean.length, pending: clean.filter(r => !sent.has(r.key) && Date.parse(r.at) > Date.now()).length }, origin);
    }
    if (req.method === "POST" && url.pathname === "/test") {
      const { id } = await readJSON(req);
      if (!ID_RE.test(id || "")) return send(res, 400, { error: "bad request" }, origin);
      const r = await push(id, { title: "Notifications are on", body: "You'll get trip reminders here, even with the app closed.", url: "./#today", tag: "test" });
      return send(res, 200, { result: r }, origin);
    }
    if (req.method === "POST" && url.pathname === "/tick") {
      if (!TICK_KEY || req.headers["x-tick-key"] !== TICK_KEY) return send(res, 403, { error: "forbidden" }, origin);
      const now = Date.now(); let sentCount = 0;
      for (const id of await db.sMembers("devices")) {
        const items = JSON.parse((await db.get(`rem:${id}`)) || "[]");
        const done = new Set(await db.sMembers(`sent:${id}`));
        for (const r of items) {
          const t = Date.parse(r.at);
          if (done.has(r.key) || t > now + 5 * 60 * 1000) continue; // allow 5 min early (the job runs every ~10)
          await db.sAdd(`sent:${id}`, r.key);
          if (now - t > STALE_MS) continue;
          try { if ((await push(id, { title: r.title, body: r.body, url: r.url, tag: r.key })) === "sent") sentCount++; } catch (e) { console.error("push", e.statusCode || e.message); }
        }
      }
      return send(res, 200, { ok: true, sent: sentCount }, origin);
    }
    send(res, 404, { error: "not found" }, origin);
  } catch (e) { console.error(e); send(res, 500, { error: "server error" }, origin); }
}).listen(PORT, () => console.log("push sender on", PORT));
