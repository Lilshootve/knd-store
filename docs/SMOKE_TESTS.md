# KND Labs - Smoke Tests

Run these after cleanup or deployment to verify the system works.

## 1. PHP Lint (all PHP files)

```powershell
Get-ChildItem -Path . -Filter *.php -Recurse | Where-Object { $_.FullName -notmatch '\\vendor\\|\\_cursor_quarantine\\' } | ForEach-Object {
  $r = php -l $_.FullName 2>&1
  if ($LASTEXITCODE -ne 0) { Write-Host "FAIL: $($_.FullName)"; $r }
}
```

Or for a single file: `php -l path/to/file.php`

## 2. Worker (single iteration, no loop)

```powershell
cd e:\repo\kndstore
php workers/labs_worker.php
```

Expected: Either "No jobs in queue" or processes one job. Exits after one run (no --loop).

## 3. API Endpoints (curl)

Set your worker token. Replace `YOUR_TOKEN` with the value from `config/worker_secrets.local.php` / `workers/worker_config.local.php`.

### Lease (get a job)

```powershell
curl -X POST "https://kndstore.com/api/labs/queue/lease.php" -H "Content-Type: application/x-www-form-urlencoded" -H "X-KND-WORKER-TOKEN: YOUR_TOKEN" -d "worker_id=test-01"
```

Expected: `{"ok":true,"job":...}` or `{"ok":true,"job":null}` if queue empty.

### Complete (mark job done)

```powershell
curl -X POST "https://kndstore.com/api/labs/queue/complete.php" -H "Content-Type: application/x-www-form-urlencoded" -H "X-KND-WORKER-TOKEN: YOUR_TOKEN" -d "job_id=999&image_url=/api/labs/image.php?job_id=999&comfy_prompt_id=test"
```

Expected: `{"ok":false,"error":"Job not found..."}` (404) is OK - we're testing the endpoint responds. Real job_id would return 200.

### Fail (mark job failed)

```powershell
curl -X POST "https://kndstore.com/api/labs/queue/fail.php" -H "Content-Type: application/x-www-form-urlencoded" -H "X-KND-WORKER-TOKEN: YOUR_TOKEN" -d "job_id=999&error_message=smoke_test"
```

Expected: 404 or 200. No 500.

### Unauthorized (wrong token)

```powershell
curl -X POST "https://kndstore.com/api/labs/queue/lease.php" -H "X-KND-WORKER-TOKEN: wrong" -d "worker_id=test"
```

Expected: 401 Unauthorized.
