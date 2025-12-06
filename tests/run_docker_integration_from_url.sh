#!/usr/bin/env bash
set -euo pipefail

IMAGE=hrnco/chromium-pdf-api:local-test
CONTAINER=chromium-pdf-api-test-url
OUT_PDF=tests/out-from-url.pdf

# Default URL to convert. You can pass a URL as the first argument.
URL=${1:-https://example.com}

cleanup() {
  echo "Cleaning up..."
  docker stop "$CONTAINER" >/dev/null 2>&1 || true
  docker rm "$CONTAINER" >/dev/null 2>&1 || true
  docker rmi "$IMAGE" >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo "Building Docker image ${IMAGE}..."
docker build -t "$IMAGE" .

echo "Removing any existing container named ${CONTAINER}..."
docker rm -f "$CONTAINER" >/dev/null 2>&1 || true

echo "Running container ${CONTAINER}..."
docker run -d --name "$CONTAINER" -p 8080:80 "$IMAGE"

echo "Waiting for HTTP server to become ready (timeout 60s)..."
code="000"
for _ in $(seq 1 60); do
  code=$(curl -sS -o /dev/null -w '%{http_code}' http://localhost:8080/ || echo "000")
  if [ "$code" = "200" ]; then
    echo "Server ready."
    break
  fi
  sleep 1
done

if [ "$code" != "200" ]; then
  echo "Server did not become ready. Container logs:"
  docker logs "$CONTAINER" || true
  exit 1
fi

echo "Posting URL ${URL} to /api/ and saving to ${OUT_PDF}..."
# Send as multipart form field named 'url' (server expects either 'html' or 'url')
curl -sS -X POST http://localhost:8080/api/ --form "url=${URL}" --output "$OUT_PDF"
curl_exit=$?
if [ $curl_exit -ne 0 ]; then
  echo "curl failed with code $curl_exit"
  exit $curl_exit
fi

if [ ! -f "$OUT_PDF" ]; then
  echo "Output PDF missing"
  exit 1
fi

size=$(stat -c%s "$OUT_PDF" || echo 0)
if [ "$size" -le 1024 ]; then
  echo "Generated PDF too small: ${size} bytes"
  exit 1
fi

echo "Integration test passed: ${OUT_PDF} (${size} bytes)"
exit 0
