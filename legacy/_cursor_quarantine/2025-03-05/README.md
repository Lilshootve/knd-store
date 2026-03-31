# Quarantine 2025-03-05

Files moved here during project purge. **Do not delete** until at least one release cycle has passed.

## debug_db.php, debug_db2.php
- **Why**: Debug scripts for database testing, not used at runtime.
- **Referenced by**: Nothing in production.
- **Restore**: Copy back to project root if needed for debugging.

## test-*.php (test-debug, test-direct, test-final, etc.)
- **Why**: Ad-hoc PHP test scripts, not part of the application.
- **Referenced by**: Nothing.
- **Restore**: Copy back if needed for local testing.

## _out/
- **Why**: Output directory, possibly ComfyUI or build artifacts.
- **Referenced by**: Nothing in codebase.
- **Restore**: N/A - was untracked.

## Kept (not quarantined)
- **run_labs_worker.bat**: Useful launcher for Windows worker.
- **docs/WORKFLOWS.md**: Documentation.
- **config/worker_secrets.local.example.php**, **workers/worker_config.local.example.php**: Restored from git (templates for setup).
