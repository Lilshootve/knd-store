#!/bin/bash
# Test concurrency: send 5 jobs, verify only MAX_CONCURRENCY run at once

BASE_URL="${1:-http://localhost:8000}"
IMAGE_URL="${2:-https://raw.githubusercontent.com/TencentARC/InstantMesh/master/examples/hatsune_miku.png}"
CALLBACK_URL="${3:-http://host.docker.internal:80/api/triposr/callback.php}"
SECRET="${4:-}"

echo "Sending 5 jobs to $BASE_URL..."
for i in 1 2 3 4 5; do
  JOB_ID="concurrent-test-$i-$$"
  resp=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/generate" \
    -H "Content-Type: application/json" \
    -d "{
      \"job_id\": \"$JOB_ID\",
      \"image_url\": \"$IMAGE_URL\",
      \"callback_url\": \"$CALLBACK_URL\",
      \"secret\": \"$SECRET\"
    }")
  code=$(echo "$resp" | tail -1)
  body=$(echo "$resp" | sed '$d')
  echo "Job $i ($JOB_ID): HTTP $code - $body"
done
echo "Done. With MAX_CONCURRENCY=2, only 2 should process in parallel."
