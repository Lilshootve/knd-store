# KND Labs Tool Pages – Performance Audit Report

**Date:** 2026-03-04  
**Scope:** text-to-image, upscale, consistency, character-lab, texture-lab  
**Method:** Instrumentation (labs_perf), code review, network/blocking analysis  
**Status:** Audit only – no behavior changes applied

---

## 1. Entry Points & Instrumentation

| Page | Entry File | Lab File | Perf Log Output |
|------|------------|----------|-----------------|
| Text-to-Image | `labs-text-to-image.php` | `labs/text-to-image.php` | HTML comment `<!-- LABS_PERF ... -->` + `logs/labs-perf.log` |
| Upscale | `labs-upscale.php` | `labs/upscale.php` | Same |
| Consistency | `labs-consistency.php` | `labs/consistency.php` | Same |
| Character Lab | `labs-character-lab.php` | `labs/character-lab.php` | Same |
| Texture Lab | `labs-texture-lab.php` | `labs/texture-lab.php` | Same |

**Instrumented checkpoints:**
- `init_after_session` – after session.php
- `init_after_config` – after config.php
- `init_after_auth` – after auth.php
- `init_after_includes` – after header, footer, support_credits, ai
- `init_after_require_login` – after auth check
- `init_after_db_connect` – after getDBConnection()
- `init_after_credits` – after release/expire points + get_available_points
- `{tool}_after_init` – after _init.php
- `{tool}_after_comfyui` – after comfyui.php (where applicable)
- `{tool}_after_history_fetch` – after comfyui_get_user_jobs / ai_get_jobs_by_type
- `{tool}_after_preload` – after preloadFromJob (consistency only)
- `{tool}_before_header` – before generateHeader()

---

## 2. Frontend Network Requests on Initial Load

### Blocking / Render-blocking

| Resource | Type | Blocking? | Notes |
|----------|------|-----------|-------|
| Bootstrap CSS | CDN | Yes | Render-blocking |
| Font Awesome | CDN | Yes | Render-blocking |
| style.css | Local | Yes | Render-blocking |
| knd-ui.css | Local | Yes | Render-blocking |
| mobile-optimization.css | Local | Yes | Render-blocking |
| ai-tools.css | Local | Yes | Render-blocking |
| knd-labs.css | Local | Yes | Render-blocking |

### Deferred (non-blocking for first paint)

| Resource | Type | When |
|----------|------|------|
| knd-toast.js | Script | defer |
| jQuery | CDN | defer |
| Bootstrap JS | CDN | defer |
| particles.min.js | CDN | defer |
| main.js | Local | defer |
| cookies-consent.js | Local | defer |
| mobile-optimization.js | Local | defer |
| scroll-smooth.js | Local | defer |
| knd-confetti.js | Local | defer |
| kndlabs.js | Local | Inline at end (no defer) |
| navigation-extend.js | Local | Inline at end |

### API / XHR on load (after DOM ready)

| API | When | Blocks? |
|-----|------|--------|
| `/api/labs/pricing.php` | KNDLabs.init() first action | Yes – form bindings wait for fetch (or catch) |
| `/api/labs/image.php?job_id=X` | For each history thumb/card | No – img src loads async |
| `/api/labs/image.php?job_id=X` (upscale) | If `?source_job_id=X` in URL | Yes – inline script fetches before enabling submit |

### Image requests (inline in HTML)

- Nav logo: `/assets/images/logo.png`
- Recent jobs: N × `/api/labs/image.php?job_id={id}` (up to 8–12 thumbnails)
- knd-showcase-card: same image URLs per card

---

## 3. Blocking Factors for First Render

### PHP (TTFB / back-end)

1. **Session** – session_start(), cookie read
2. **Config** – requires, constants
3. **Auth** – require_login() (fast if logged in)
4. **Support credits** – `release_available_points_if_due` (1 SELECT + optional UPDATE), `expire_points_if_due` (1 UPDATE), `get_available_points` (2 SELECTs) – **5 DB round-trips**
5. **DB connect** – PDO connection
6. **History fetch** – `comfyui_get_user_jobs` (1 SELECT, up to 12–20 rows)
7. **Consistency preload** – extra SELECT when `reference_job_id` in URL
8. **generateHeader** – builds HTML (fast)

### Frontend

1. **API_PRICING fetch** – KNDLabs.init() waits for `/api/labs/pricing.php` before binding form; pricing is hardcoded in API
2. **Upscale source preload** – when `?source_job_id=X`, fetches image before enabling submit
3. **CSS waterfall** – 7 stylesheets (2 CDN + 5 local) before first paint

---

## 4. Data Suitable for Lazy-Loading (after first render)

| Data | Current | Lazy-load candidate | Effort |
|------|---------|---------------------|--------|
| History jobs (sidebar) | Fetched in PHP, rendered in HTML | Load via API after DOM ready | Medium |
| Recent creations (cards) | Same | Same | Medium |
| History thumbnails | img src in HTML (parallel) | IntersectionObserver + placeholder | Medium |
| Queue status | Shown only after submit | N/A | - |
| Model / config sync | Not present | N/A | - |
| Pricing | Fetched on init | Cache in localStorage or inline in page | Low |
| Balance | In HTML from PHP | Could refresh async, but needed for submit | Low |

---

## 5. Cacheable Model / Config Data

| Data | Source | Cacheable? | Notes |
|------|--------|------------|-------|
| Pricing | `/api/labs/pricing.php` | Yes | Hardcoded in API; no DB |
| Model list (text2img) | Hardcoded in text-to-image.php | Yes | Static select options |
| Samplers, sizes | Hardcoded in PHP | Yes | Static |
| ComfyUI base URL | DB / config | Yes | Changes rarely; cache per session |

**Pricing:** Response is static JSON. Safe to:
- Inline in page as `window.KND_PRICING`
- Or cache in `localStorage` with TTL (e.g. 1h)

---

## 6. Findings Summary

### Backend (PHP)

- **Credits logic:** 5 DB queries on every Labs page load (`release`, `expire`, `get_available_points` x2 + joins).
- **History:** 1 query per page; consistency adds an extra query when preloading reference job.
- **Support credits:** Designed for correctness; could be optimized with batched/cached balance.

### Frontend

- **Pricing fetch:** Blocks form binding; data is static.
- **Thumbnails:** N image requests (8–12) for history; not render-blocking but add load.
- **Scripts:** kndlabs.js is not deferred; runs after inline script and can delay interaction.
- **CSS:** 7 stylesheets; no critical-CSS extraction.

---

## 7. Low-Risk Optimizations (proposed, not applied)

### 7.1 Very low risk

1. **Inline pricing** – Add `window.KND_PRICING = {...}` in page, skip API_PRICING fetch on first load.
2. **Cache pricing** – If keeping fetch: cache in `localStorage` with TTL; use stale-while-revalidate.
3. **Defer kndlabs.js** – Add `defer` and move init to `DOMContentLoaded` if needed.

### 7.2 Low risk

4. **Lazy-load history panel** – Render placeholder; fetch history via API after first paint.
5. **Lazy-load recent creations** – Same pattern.
6. **Lazy-load thumbnails** – Use `loading="lazy"` on img, or IntersectionObserver for below-fold thumbs.

### 7.3 Medium risk

7. **Batch credits logic** – Combine `release`, `expire`, `get_available_points` into fewer queries.
8. **Cache balance** – Session cache for balance with short TTL (e.g. 60s).
9. **Critical CSS** – Extract above-the-fold rules; load rest async.

---

## 8. Modified Files (instrumentation only)

- `includes/labs_perf.php` – new
- `labs/_init.php` – perf checkpoints
- `labs/text-to-image.php` – perf checkpoints + output
- `labs/consistency.php` – perf checkpoints + output
- `labs/upscale.php` – perf checkpoints + output
- `labs/character-lab.php` – perf checkpoints + output
- `labs/texture-lab.php` – perf checkpoints + output

---

## 9. How to Use Perf Data

1. Load a Labs tool page (e.g. `/labs/text-to-image`).
2. View source and search for `LABS_PERF`.
3. Or inspect `logs/labs-perf.log` (create `logs/` if missing and ensure it’s writable).

Example:

```
<!-- LABS_PERF total=142.3ms | init_after_session: 8.2ms (Δ8.2ms) | init_after_config: 12.1ms (Δ3.9ms) | ... -->
```
