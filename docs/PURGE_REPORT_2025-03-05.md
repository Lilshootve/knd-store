# Project Purge Report – 2025-03-05

## Summary

- **Kept**: All runtime-critical files, API endpoints, worker, workflows
- **Quarantined**: Debug scripts, test files, output directory
- **Restored**: Worker config templates (example files)
- **Deleted**: None (quarantine used instead)

---

## Kept (Runtime-Critical)

| Path | Purpose |
|------|---------|
| `api/labs/queue/lease.php` | Worker leases job |
| `api/labs/queue/complete.php` | Worker marks job done |
| `api/labs/queue/fail.php` | Worker marks job failed |
| `api/labs/generate.php` | Text2img, upscale, character – creates jobs |
| `api/labs/image.php` | Image proxy for results |
| `api/labs/status.php` | Polling status |
| `api/labs/tmp_image.php` | Worker fetches input image |
| `api/labs_upscale_create.php` | Upscale (Hostinger stores, worker fetches) |
| `api/labs/upscale_create.php` | Alternate upscale (Hostinger uploads to ComfyUI) |
| `workers/labs_worker.php` | HTTP worker entrypoint |
| `workflows/knd-workflow-api.json` | Text2img workflow |
| `workflows/upscale_api.json` | Upscale workflow |
| `KND_MASTER_WORKFLOW_*.json` | Fallback workflows |
| `config/worker_secrets.local.example.php` | Template (restored) |
| `workers/worker_config.local.example.php` | Template (restored) |
| `run_labs_worker.bat` | Windows worker launcher |
| `docs/WORKFLOWS.md` | Workflow docs |

---

## Quarantined (`_cursor_quarantine/2025-03-05/`)

| File/Dir | Reason |
|----------|--------|
| `debug_db.php` | Debug script, unreferenced |
| `debug_db2.php` | Debug script, unreferenced |
| `test-debug.php` | Ad-hoc test |
| `test-direct.php` | Ad-hoc test |
| `test-final.php` | Ad-hoc test |
| `test-functions-file.php` | Ad-hoc test |
| `test-index.php` | Ad-hoc test |
| `test-line-by-line.php` | Ad-hoc test |
| `test-parse.php` | Ad-hoc test |
| `test-require.php` | Ad-hoc test |
| `test-simple.php` | Ad-hoc test |
| `test-syntax.php` | Ad-hoc test |
| `_out/` | Output directory, untracked |

---

## Deleted

None. All removals were via quarantine.

---

## Modified

| File | Change |
|------|--------|
| `.gitignore` | Added `logs/*.log` |
| `logs/php-error.log` | Untracked (was modified, now ignored) |

---

## Smoke Tests

See `docs/SMOKE_TESTS.md` for:

- PHP lint
- Worker single-run
- Curl tests for lease, complete, fail

---

## Architecture (unchanged)

```
Hostinger: PHP web + /api/* + MySQL
     ↑
     | lease, complete, fail (X-KND-WORKER-TOKEN)
     ↓
Worker (Windows PC): workers/labs_worker.php
     ↓
ComfyUI (same PC): https://comfy.kndstore.com
```
