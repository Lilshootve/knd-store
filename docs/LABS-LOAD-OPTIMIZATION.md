# KND Labs Load Optimization – Deliver Summary

**Date:** 2026-03-04  
**Scope:** labs-text-to-image, labs-upscale  
**Goal:** Faster initial load, minimal first render, fix LCP, defer non-critical content

---

## What Was Optimized

### 1. LCP image (img.me-2)
- **Location:** `includes/header.php` – navbar logo
- **Changes:** Added `width="100"` `height="100"` `decoding="async"` `fetchpriority="high"`
- **Reason:** Logo was causing poor LCP in Chrome; explicit dimensions avoid layout shifts and `fetchpriority="high"` improves above-the-fold loading.

### 2. Backend: history removed from initial render
- **Text-to-image & upscale:** No DB history fetch in PHP
- **Before:** `comfyui_get_user_jobs()` on every load
- **After:** History is lazy-loaded via API after first paint

### 3. Inline pricing
- Labs pages inject `window.KND_PRICING = {...}` before `kndlabs.js`
- **Effect:** Skips `/api/labs/pricing.php` fetch; form binding no longer waits for the pricing API

### 4. Non-critical JS
- `mobile-optimization.js` and `scroll-smooth.js` already use `defer` (unchanged)
- `labs-lazy-history.js` added with `defer` for history loading

### 5. Lazy-loaded sections (after first paint)
- **History sidebar** – placeholder on load, content from `/api/labs/jobs.php?tool=X&limit=N` via `requestIdleCallback`
- **Recent Creations grid** – same pattern
- **Thumbnails** – rendered with `loading="lazy"` and `decoding="async"`

### 6. API extension
- **`/api/labs/jobs.php`:** Added `tool` filter param for `?tool=text2img` / `?tool=upscale`

---

## What Was Deferred / Lazy-Loaded

| Section             | Before          | After                          |
|---------------------|-----------------|--------------------------------|
| History sidebar     | PHP + HTML      | Placeholder → API + JS         |
| Recent Creations    | PHP + HTML      | Placeholder → API + JS         |
| History thumbnails  | Immediate `img` | `loading="lazy"` via API       |
| Queue status        | Hidden by default | Unchanged                    |
| Secondary metrics   | N/A             | Unchanged                      |

---

## LCP Image Fix Details

**Element:** `<img src="/assets/images/logo.png" alt="KND Store" class="me-2">` in navbar  
**File:** `includes/header.php`  
**Updated to:**  
`<img src="/assets/images/logo.png" alt="KND Store" width="100" height="100" decoding="async" fetchpriority="high" class="me-2">`

---

## Recent Jobs Optimization (follow-up)

- Recent jobs: limit 5 items, loading skeleton "Loading recent jobs...", load after DOMContentLoaded
- Timing logs: `performance.mark` / `performance.measure`, `console.log` for fetch+parse and render
- On fetch failure: show "Could not load recent jobs. You can still generate."; tools remain usable
- All images: `loading="lazy"` `decoding="async"`; no extra per-job API calls (single jobs list fetch)

## Modified Files

- `includes/header.php` – LCP image attributes
- `api/labs/jobs.php` – `tool` filter
- `assets/js/kndlabs.js` – use `window.KND_PRICING` when present
- `assets/js/labs-lazy-history.js` – lazy-load, limit 5, timing logs, fallback on error
- `labs/text-to-image.php` – loading skeleton, limit 5
- `labs/upscale.php` – same changes

---

## Backend Performance Logging

Existing `labs_perf` instrumentation is unchanged and still active:
- HTML comment: `<!-- LABS_PERF total=Xms | ... -->`
- Log file: `logs/labs-perf.log`
