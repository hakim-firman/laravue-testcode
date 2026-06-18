# PRD — Visual Two: Date Timeline

## 1. Executive Summary

**Problem:** Card grid gives breadth; no layout helps users think temporally — "what's happening this weekend, next month?" Raw date strings don't communicate sequence or proximity.

**Solution:** Vertical timeline grouped by date, events listed under each day header. Dense, scannable, scroll-driven — visually distinct from the card grid.

**Success Criteria:**
1. Timeline groups events correctly by browser-local date (no UTC/local mismatch)
2. Day headers sticky-scroll within their group
3. Scroll-triggered animations fire once per row (no re-trigger on scroll-up)
4. Same filter capability as Visual One (date range + location + type + status)
5. Handles 0 events (empty state) and 500+ visible events without scroll jank

---

## 2. User Experience & Functionality

### Layout Concept

```
[Filter Bar — sticky]

  ── Today · Sat Jun 14, 2025 ─────────────────────
  │  [img] Annual Jazz Festival          8:00 PM
  │        Brooklyn, New York            concert
  │
  │  [img] Indie Film Showcase           6:30 PM
  │        Manhattan, New York           exhibition
  │
  ── Tomorrow · Sun Jun 15, 2025 ──────────────────
  │  [img] Global Tech Summit            9:00 AM
  │        Berlin, Germany               conference
```

- Vertical left accent line runs full height of each day group
- Day header: sticky within its group (`position: sticky; top: 56px`)
- Event rows: horizontal — thumbnail left, metadata right
- Past events: muted opacity (60%)
- Hover: row highlight + accent pulse

### User Stories & Acceptance Criteria

#### Story 1 — Timeline Grouped by Date

> As a browser, I want events grouped by day so I can think about "what's on this weekend."

- Events grouped client-side by `starts_at` in browser timezone (`Intl.DateTimeFormat`)
- Groups sorted ascending by date (soonest first by default)
- Day header format: `Sat · Jun 14, 2025` with relative label when applicable (`Today`, `Tomorrow`, `This Weekend`)
- Sticky header per group — sticks at `top: 56px` (filter bar height)
- Groups separated by visible rule + date label
- Past events: `opacity-60` + `grayscale-[30%]`

#### Story 2 — Event Row

> As a browser, I want each event to show key info in a compact row so I can scan efficiently.

- Thumbnail: 64×64px, rounded, `object-cover`, fixed height (no reflow)
- Event name: bold, 1 line truncated
- Time: `8:00 PM EDT`
- Location: `Brooklyn, New York`
- Type badge (same color system as Visual One)
- Status badge: only shown when **not** `published` (reduces noise)
- Full row click → `/events/{id}`
- Fixed row height: 72px

#### Story 3 — Scroll-Triggered Row Animations

> As a browser, I want rows to animate in as I scroll so the timeline feels progressive.

- Each row slides in from left on entering viewport (`translateX(-16px) opacity(0)` → normal)
- `IntersectionObserver` fires animation once then disconnects (no re-trigger on scroll-up)
- Day headers fade in only (no translate — they're sticky)
- No animation on filter-triggered re-render
- Respects `prefers-reduced-motion`

#### Story 4 — Filter Panel

> As a browser, I want same filter capability as Visual One.

- Identical fields: from/to date, location text, event type, status
- Filter state in URL query params
- Applying filters: scroll to top + re-render timeline
- Default range: today → +30 days (past events shown but de-emphasized)

#### Story 5 — Jump-to-Date

> As a browser, I want to jump to a specific date so I don't scroll through hundreds of groups.

- Date-picker input in filter bar labeled "Jump to"
- Selecting date: `scrollIntoView({ behavior: 'smooth' })` on matching group header
- If no events that date: scroll to nearest future group
- Only rendered when > 10 date groups visible

#### Story 6 — Infinite Scroll

> As a browser, I want the timeline to handle many events without freezing.

- 100 events per page (rows are lighter than cards)
- `IntersectionObserver` sentinel at bottom triggers next page
- Cross-page group merge: new page events appended to existing day group if same date
- Loading state: 3 skeleton rows at bottom of last group
- Max DOM rows before lightweight virtual scroll: 500 rows
  - `v-show` + `IntersectionObserver` per row as virtual scroll (no library required)

### Non-Goals

- Horizontal / calendar grid view
- Drag-to-reschedule
- Week / month toggle
- Map pins
- Export to `.ics`

---

## 3. Technical Specifications

### Component Tree

```
VisualTwo.vue (page)
├── EventFilterBar.vue              ← shared with Visual One
├── EventTimeline.vue               ← groups container + grouping logic
│   └── EventDateGroup.vue [×N]     ← per-day section
│       ├── DateGroupHeader.vue     ← sticky label
│       └── EventTimelineRow.vue [×M] ← per-event row
├── EventTimelineRowSkeleton.vue [×3]
└── EndOfResults.vue                ← shared
```

### Client-Side Grouping Logic

```ts
interface DateGroup {
  dateKey: string        // 'YYYY-MM-DD' in browser TZ
  label: string          // 'Sat · Jun 14, 2025'
  relativeLabel: string  // 'Today' | 'Tomorrow' | 'This Weekend' | ''
  events: EnrichedEvent[]
}

function groupByDate(events: EnrichedEvent[], tz: string): DateGroup[] {
  const groups = new Map<string, EnrichedEvent[]>()
  for (const event of events) {
    const key = new Intl.DateTimeFormat('en-CA', { timeZone: tz })
      .format(new Date(event.starts_at * 1000)) // 'YYYY-MM-DD'
    const bucket = groups.get(key)
    bucket ? bucket.push(event) : groups.set(key, [event])
  }
  return [...groups.entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([key, evts]) => ({ dateKey: key, ...formatGroupLabel(key, tz), events: evts }))
}

// Cross-page merge — call when new API page arrives
function mergeIntoGroups(
  existing: DateGroup[],
  incoming: EnrichedEvent[],
  tz: string,
): DateGroup[] {
  const newGroups = groupByDate(incoming, tz)
  for (const group of newGroups) {
    const match = existing.find(g => g.dateKey === group.dateKey)
    match ? match.events.push(...group.events) : existing.push(group)
  }
  return existing
}
```

### Scroll Reveal Composable

```ts
// composables/useScrollReveal.ts
import { type Ref, onBeforeUnmount, onMounted } from 'vue'

export function useScrollReveal(el: Ref<HTMLElement | null>) {
  onMounted(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('revealed')
          observer.disconnect()
        }
      },
      { threshold: 0.1 },
    )
    if (el.value) observer.observe(el.value)
    onBeforeUnmount(() => observer.disconnect())
  })
}
```

```css
.reveal-row {
  transform: translateX(-16px);
  opacity: 0;
  transition: transform 0.3s ease, opacity 0.3s ease;
}
.reveal-row.revealed {
  transform: none;
  opacity: 1;
}
@media (prefers-reduced-motion: reduce) {
  .reveal-row,
  .reveal-row.revealed {
    transform: none;
    opacity: 1;
    transition: none;
  }
}
```

### Sticky Headers CSS Note

```css
/* DateGroupHeader needs overflow:visible on all ancestors */
/* Check AppContent.vue — remove overflow:hidden if present */
.date-group-header {
  position: sticky;
  top: 56px; /* filter bar height */
  z-index: 10;
}
```

### API Params (Visual Two)

```
GET /events/data
  ?from=2025-01-01
  &to=2025-12-31
  &location=berlin
  &type=conference
  &status=published
  &sort=starts_at_asc     ← ascending for chronological timeline
  &page=1
```

---

## 4. Risks & Roadmap

| Risk | Mitigation |
|------|-----------|
| Sticky headers break inside `overflow:hidden` parent | Audit `AppContent.vue` — remove overflow:hidden |
| Cross-page group merge creates duplicate events | Deduplicate by event ID on merge |
| 500+ rows causes scroll jank | `v-show` + `IntersectionObserver` per row as lightweight virtual scroll |
| Date grouping UTC vs local mismatch | Group client-side only with `Intl` + browser TZ |
| `starts_at` missing from some seeded rows | Fallback to `created_time` column value |

### Build Order

1. DB migrations + geocoding + image seeding *(shared with Visual One)*
2. `useScrollReveal.ts` composable
3. `EventTimelineRow.vue` + `EventTimelineRowSkeleton.vue`
4. `DateGroupHeader.vue`
5. `EventDateGroup.vue` (sticky + grouping container)
6. `EventTimeline.vue` (grouping logic + cross-page merge)
7. `VisualTwo.vue` page wiring + infinite scroll
8. Jump-to-date picker
9. Skeleton + empty + error + end-of-results states
10. `prefers-reduced-motion` guard pass
