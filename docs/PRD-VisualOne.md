# PRD — Visual One: Card Grid Explorer

## 1. Executive Summary

**Problem:** No visual way to browse 1.25M events. Raw table shows no images, unformatted timestamps, raw coordinates.

**Solution:** Responsive card grid with enriched event data, filters, and smooth entry animations.

**Success Criteria:**
1. First page (50 cards) renders within 1.5s on cold load
2. Filters (date range + location) narrow results with zero false-positives
3. Each card shows image, formatted date/time with timezone, human-readable location
4. Lighthouse Performance ≥ 85 on desktop
5. Zero layout shift during image lazy-load

---

## 2. User Experience & Functionality

### Layout

- Grid: 1 col (< 640px) → 2 col (640–1024px) → 3 col (1024–1280px) → 4 col (> 1280px)
- Sticky filter bar pinned below page header
- Infinite scroll — sentinel 400px before bottom

### User Stories & Acceptance Criteria

#### Story 1 — Card Grid Display

> As a browser, I want to scan events as visual cards so I can quickly assess multiple options.

- Hero image (first from `event_images`, `aspect-video`, `object-cover`)
- Event type badge (color-coded — see type-color map below)
- Status badge (published=green, cancelled=red, sold_out=amber, draft=muted)
- Event name (1–2 lines, truncated with ellipsis)
- Formatted date/time: `Sat 14 Jun 2025 · 8:00 PM EDT`
- Human-readable location: `Brooklyn, New York · US`
- Venue capacity from payload
- Full card is clickable → `/events/{id}`

#### Story 2 — Animated Entry

> As a browser, I want cards to animate in so the page feels alive without being distracting.

- Cards stagger-fade up on initial load
- Delay increment: 30ms per card, capped at 300ms total (cards 10+ animate together)
- No animation on filter-triggered re-fetch
- Respects `prefers-reduced-motion` — animation disabled when set

#### Story 3 — Image Loading

> As a browser, I want images to load gracefully so the page doesn't jump.

- Images lazy-loaded (`loading="lazy"`)
- Placeholder: blurred gradient or `PlaceholderPattern` component while loading
- Broken image fallback: type-specific colored gradient with event type initial
- Served from `/storage` symlink (local disk, no CDN)

#### Story 4 — Filter Panel

> As a browser, I want to filter by date and location so I find relevant events.

- **From date** (date input, default: today)
- **To date** (date input, default: +30 days)
- **Location** (text → searches `geocoded_address` ILIKE `%query%`)
- **Event type** (multi-select: all 8 types)
- **Status** (select: all / published / cancelled / sold_out / draft)
- "Apply" + "Clear" buttons
- Filter state in URL query params (`?from=&to=&location=&type=&status=`)
- Navigating back restores filter state from URL

#### Story 5 — Infinite Scroll

> As a browser, I want more cards to load as I scroll so I don't need pagination.

- 50 cards per page via `/events/data`
- `IntersectionObserver` sentinel triggers next page load
- Loading state: 6 skeleton cards pulsing at bottom
- End state: "Showing all {n} events" message
- Error state: "Failed to load — retry" button

### Non-Goals

- Event creation / editing
- Saved / favourite events
- Social sharing
- Map view (Visual Two)
- Sort controls (default: `created_time DESC`)

---

## 3. Technical Specifications

### Component Tree

```
VisualOne.vue (page)
├── EventFilterBar.vue           ← sticky filter strip (shared with Visual Two)
├── EventCardGrid.vue            ← CSS grid container
│   ├── EventCard.vue [×N]       ← individual card
│   │   ├── EventCardImage.vue   ← image + skeleton + fallback
│   │   └── EventTypeBadge.vue
├── EventCardSkeleton.vue [×6]   ← loading placeholders
└── EndOfResults.vue
```

### Type-to-Color Map

```ts
const TYPE_COLORS: Record<string, string> = {
  concert:    'bg-purple-100 text-purple-700',
  conference: 'bg-blue-100   text-blue-700',
  meetup:     'bg-green-100  text-green-700',
  workshop:   'bg-yellow-100 text-yellow-700',
  festival:   'bg-orange-100 text-orange-700',
  sports:     'bg-red-100    text-red-700',
  networking: 'bg-cyan-100   text-cyan-700',
  exhibition: 'bg-pink-100   text-pink-700',
}
```

### Date/Time Formatting

```ts
function formatEventDate(unixTs: number, tz: string): string {
  return new Intl.DateTimeFormat('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    timeZone: tz,
    timeZoneName: 'short',
  }).format(new Date(unixTs * 1000))
}

const userTz = Intl.DateTimeFormat().resolvedOptions().timeZone
```

### API Params (Visual One)

```
GET /events/data
  ?from=2025-01-01
  &to=2025-12-31
  &location=brooklyn
  &type=concert,festival
  &status=published
  &sort=created_time_desc   ← default
  &page=1
```

### Stagger Animation (CSS + Vue)

```ts
// EventCard.vue
const props = defineProps<{ index: number }>()
const delay = computed(() => `${Math.min(props.index * 30, 300)}ms`)
```

```html
<div
  class="animate-fade-up"
  :style="{ animationDelay: delay, animationFillMode: 'both' }"
>
```

```css
@keyframes fade-up {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: none; }
}
@media (prefers-reduced-motion: reduce) {
  .animate-fade-up { animation: none; }
}
```

---

## 4. Risks & Roadmap

| Risk | Mitigation |
|------|-----------|
| 4-col grid reflow on image load | Fixed `aspect-video` container prevents reflow |
| `geocoded_address` NULL on un-geocoded rows | Fallback to `{lat}, {lng}` client-side |
| JSON extract filter slow on 1.25M rows | Generated `starts_at` column with index |
| 50 cards × 2 images = 100 network requests | `loading="lazy"` defers off-screen; browser batches |

### Build Order

1. DB migrations + geocoding batch + image seeding *(shared with Visual Two)*
2. Extend `EventController@data` — new params + response fields
3. `EventTypeBadge.vue` + `EventCardImage.vue`
4. `EventCard.vue` + `EventCardSkeleton.vue`
5. `EventFilterBar.vue` *(shared component)*
6. `EventCardGrid.vue` + infinite scroll
7. `VisualOne.vue` page wiring
8. Stagger animation + `prefers-reduced-motion` guard
9. Empty / error / end-of-results states
