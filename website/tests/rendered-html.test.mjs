import assert from "node:assert/strict";
import test from "node:test";

async function render(path = "/") {
  const workerUrl = new URL("../dist/server/index.js", import.meta.url);
  workerUrl.searchParams.set("test", `${process.pid}-${Date.now()}-${path}`);
  const { default: worker } = await import(workerUrl.href);

  return worker.fetch(
    new Request(`http://localhost${path}`, {
      headers: { accept: "text/html" },
    }),
    {
      ASSETS: {
        fetch: async () => new Response("Not found", { status: 404 }),
      },
    },
    {
      waitUntil() {},
      passThroughOnException() {},
    },
  );
}

test("server-renders the complete marketing homepage", async () => {
  const response = await render();
  assert.equal(response.status, 200);
  assert.match(response.headers.get("content-type") ?? "", /^text\/html\b/i);

  const html = await response.text();
  assert.match(html, /<title>WhatstheUp \| Official WhatsApp Campaigns<\/title>/i);
  assert.match(html, /Official Cloud API/i);
  assert.match(html, /From Meta connection to measurable delivery/i);
  assert.match(html, /Official infrastructure\. No shortcuts\./i);
  assert.match(html, /href="\/privacy"/i);
  assert.match(html, /href="\/terms"/i);
  assert.match(html, /href="\/data-deletion"/i);
  assert.match(html, /href="\/contact"/i);
  assert.match(html, /\/og\.png/i);
  assert.doesNotMatch(html, /codex-preview|react-loading-skeleton|Your site is taking shape/i);
});

const publicPages = [
  ["/privacy", "Privacy Policy", "WhatstheUp handles account"],
  ["/terms", "Terms of Service", "official platform capabilities"],
  ["/data-deletion", "Data Deletion Instructions", "request deletion"],
  ["/contact", "Let’s talk about your first campaign", "hello@whatstheup.in"],
];

for (const [path, title, content] of publicPages) {
  test(`server-renders ${path}`, async () => {
    const response = await render(path);
    assert.equal(response.status, 200);
    assert.match(response.headers.get("content-type") ?? "", /^text\/html\b/i);

    const html = await response.text();
    assert.match(html, new RegExp(title, "i"));
    assert.match(html, new RegExp(content, "i"));
    assert.match(html, /href="\/"/i);
  });
}
