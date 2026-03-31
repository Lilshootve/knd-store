#!/bin/bash
# Test POST /generate - requires JOB_ID and IMAGE_URL from a real KND submission

BASE_URL="${1:-http://localhost:8000}"
JOB_ID="${2:-test-$(uuidgen 2>/dev/null || echo $RANDOM)}"
IMAGE_URL="${3:-https://raw.githubusercontent.com/TencentARC/InstantMesh/master/examples/hatsune_miku.png}"
CALLBACK_URL="${4:-http://host.docker.internal:80/api/triposr/callback.php}"
SECRET="${5:-}"

echo "POST $BASE_URL/generate"
echo "job_id=$JOB_ID"
echo "image_url=$IMAGE_URL"
echo "callback_url=$CALLBACK_URL"
echo ""

curl -v -X POST "$BASE_URL/generate" \
  -H "Content-Type: application/json" \
  -d "{
    \"job_id\": \"$JOB_ID\",
    \"image_url\": \"$IMAGE_URL\",
    \"callback_url\": \"$CALLBACK_URL\",
    \"secret\": \"$SECRET\"
  }"
echo ""
