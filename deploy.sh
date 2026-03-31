#!/bin/bash
# KND Store - Deployment script
# Run this on the server (e.g. via SSH) when deployment fails due to divergent branches.
# Usage: ./deploy.sh [branch]
# Default branch: main

set -e
BRANCH="${1:-main}"

echo "=== KND Store deploy ($BRANCH) ==="

# Configure pull strategy to avoid "divergent branches" error
git config pull.rebase false 2>/dev/null || true

# Fetch latest from origin
git fetch origin

# Reset to match remote exactly (discards any local changes on server)
git reset --hard "origin/$BRANCH"

echo "=== Deploy complete ==="
