# chromium-pdf-api
A lightweight Dockerized **sidecar** for HTML→PDF using headless Google Chrome/Chromium.

**Primary use-case:** run this as an internal **support container** in DEV/TEST/PROD so your application images stay clean—no Chrome/Puppeteer installed in the app image.

- Exposes a minimal `POST /api/` that accepts a **URL reachable from inside your Docker network** (service-to-service, staging over VPN, etc.) and returns a PDF
- Designed for sidecar usage (keep it private: no public ports unless you’re testing)
- Language-agnostic: any app can call it over HTTP (PHP, Node, Python, Java, …)
- International text ready (Noto/Thai/CJK/Emoji fonts preinstalled)

---

## API

**Endpoint:** `POST /api/`  
**Request body (choose one):**
- `application/json` → `{"url":"https://example.com"}`
- `application/x-www-form-urlencoded` or `multipart/form-data` → `url=https://example.com`

**Response:**
- `200 OK` → `application/pdf` (inline)
- `400 Bad Request` → invalid or missing `url`
- `405 Method Not Allowed` → use `POST`
- `500 Internal Server Error` → Chromium failed to render

---

## Quick start (Docker)

Build and run locally:

```bash
# from the repo root (with Dockerfile present)
docker build -t chromium-pdf-api .
docker run --rm -p 8080:80 chromium-pdf-api

# Generate a PDF (JSON body)
curl -X POST http://localhost:8080/api/ \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com"}' \
  --output out.pdf

# Or form data
curl -X POST http://localhost:8080/api/ \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "url=https://example.com" \
  --output out.pdf
```

> **TIP:** For heavy pages/SPAs, Chrome can use more shared memory:
> ```bash
> docker run --rm --shm-size=512m -p 8080:80 chromium-pdf-api
> ```

---

## Sidecar mode (private service, no public port)

Run as an **internal** container only accessible to your app:

```yaml
# docker-compose.yml
services:
  pdf:
    image: hrnco/chromium-pdf-api
    # no ports -> stays private inside the compose network

  app:
    image: curlimages/curl:8.11.1
    depends_on:
      - pdf
    command: >
      sh -c '
      sleep 2 &&
      curl -X POST http://pdf/api/ -H "Content-Type: application/json"
      -d "{\"url\":\"https://example.com\"}" --output /tmp/out.pdf &&
      ls -lh /tmp/out.pdf &&
      tail -c 64 /tmp/out.pdf || true
      '
```

Run:
```bash
docker compose up --build
```

Your `app` container can call `http://pdf/api/` (service name = hostname). The PDF never leaves the private network unless you explicitly expose it.

---

## Controlling page breaks

Use standard print CSS in your HTML:

```css
@media print {
  h1 { break-before: page; }      /* start a new page before each h1 */
  .section { break-after: page; } /* new page after section */
  .block { break-inside: avoid; } /* keep block together */
}
```

---

## Notes & Tips

- Default Chrome **header/footer is disabled**.
- Fonts include **Noto + Thai TLWG + Emoji**, so non-Latin scripts render correctly.
- **Timeouts / slow pages:** consider server-side prerendering or ensuring content is visible without client-side auth/JS blocking.
- **Security:** expose the service only inside your Docker network if it’s meant as a private sidecar.

> **TIP:** If you ever need custom headers/footers, precise paper size, margins, or HTML templates for header/footer, switch to the DevTools printing API (e.g., via Puppeteer) in a future version.

---

## Project structure (reference)

```
.
├─ Dockerfile
├─ public/
│  ├─ index.php          # hello page
│  └─ api/
│     └─ index.php       # POST /api/ entrypoint (returns PDF)
└─ src/
   └─ ChromePdf.php      # tiny wrapper for headless Chrome CLI
```

---

## Troubleshooting

- **Blank/garbled characters** → ensure the image includes fonts you need (this image already installs Noto/Thai/CJK/Emoji).
- **Large/JS-heavy pages fail** → try `--shm-size=512m` and make sure the page is reachable from inside the container (DNS, network).
- **HTTP 500** → the error text returned is from Chrome; check container logs for details.
