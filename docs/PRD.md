# PRD — LHP Coding Test: Event Visuals & Attendee System

## 1. Executive Summary

**Problem Statement:** Existing event platform has 1.25M seeded events but no visual browsing experience, no human-readable location/time display, no image support, and no attendee management or notification system.

**Proposed Solution:** Build two distinct visual event-browsing layouts, enrich event data (images, geocoded addresses, timezone-aware times), add attendee registration, and wire up transactional + scheduled reminder emails.

**Success Criteria:**
1. Both visual pages load and display enriched event data within 1s on paginated fetch of 50 events
2. Filters (date range + location) narrow results with zero false-positives
3. Attendee registration creates DB record + dispatches confirmation email within 2s
4. Reminder jobs fire within 5 min of scheduled time (3-day and 24-hour windows)
5. Zero TypeScript errors, zero PHPStan errors, all existing Pest tests green

---

## 2. User Experience & Functionality

### User Personas

| Persona | Description |
|---------|-------------|
| **Browser** | Unauthenticated visitor discovering events by location/date |
| **Attendee** | Authenticated user registering interest, expects email confirmation |
| **Organizer** | User who owns events, implicitly receives attendee activity |

### User Stories & Acceptance Criteria

#### Story 1 — Images

> As a browser, I want to see event images so I can visually identify events.

- Each event has ≥ 2 associated images
- Images served from `/storage` (Laravel local disk) — no external URLs
- Migration adds `event_images` table (`event_id` FK, `path`, `sort_order`)
- Seeder populates placeholder images (reuse same files is fine — 2 per event type × 8 types = 16 files)
- Images accessible via `storage:link` symlink
- `EventController` includes images in API response

#### Story 2 — Human-Readable Location

> As a browser, I want to see a city/country name instead of coordinates so I know where events are.

- `latitude`/`longitude` resolved to `{city}, {country}` string server-side
- Resolution uses static coordinate-to-city lookup from known `CITY_ANCHORS` in seeder (no external API, no rate limits)
- Resolved address stored in `geocoded_address` column (populated once via migration/seeder batch)
- Fallback: `{lat}, {lng}` if resolution fails

#### Story 3 — Timezone-Aware Date/Time

> As a browser, I want event times shown in my local timezone so I know when to attend.

- `schedule.starts_at` (unix timestamp from JSON payload) formatted as `Sat 14 Jun 2025 · 8:00 PM EDT`
- Timezone: browser-local via `Intl.DateTimeFormat().resolvedOptions().timeZone`
- UTC offset label included (`EDT`, `CET`, etc.)

#### Story 4 — Filtering

> As a browser, I want to filter events by date and location so I find relevant events.

- Date filter: from-date + to-date applied against `starts_at` (extracted from JSON payload)
- Location filter: text input matched against `geocoded_address` (case-insensitive LIKE)
- Filters composable simultaneously
- Filter state in URL query params (shareable/bookmarkable)
- Applying filters resets result set and re-fetches

#### Story 5 — Attendee Registration

> As an attendee, I want to register interest for an event so I'm on the attendee list.

- "Register Interest" button on event detail page (`Show.vue`)
- Auth required — unauthenticated users redirected to login, returned to event after
- Creates `attendees` record (`event_id`, `user_id`, `registered_at`)
- Duplicate registration prevented (unique constraint + graceful UI message)
- Button shows "Registered ✓" after success
- Attendee count shown on event

#### Story 6 — Confirmation Email

> As an attendee, I want an email confirming my registration so I have a record.

- `ConfirmationMail` mailable dispatched to queue on registration
- Email contains: event name, formatted date/time, location, link to event
- Queue worker handles delivery

#### Story 7 — Reminder Emails

> As an attendee, I want reminders before an event so I don't forget.

- Two jobs: `SendEventReminders3Day` and `SendEventReminders24Hour`
- Query `attendees` where event `starts_at` falls within window, not yet reminded
- `attendees` table tracks `reminded_3day_at` and `reminded_24h_at` (nullable timestamps) to prevent duplicate sends
- Jobs registered in Laravel `Schedule`; run on queue

### Non-Goals

- Real payment processing or ticketing
- Event creation/editing UI
- Public API or webhooks
- Push notifications / SMS
- Admin dashboard
- Rate limiting on attendee registration
- i18n / multi-language

---

## 3. Technical Specifications

### Architecture Overview

```
Browser
  └─ Inertia page (VisualOne / VisualTwo / Show)
       └─ fetch /events/data?... (JSON, paginated)
            └─ EventController
                 ├─ Event model (+ images, geocoded_address, starts_at)
                 └─ Attendee model

Queue Worker
  └─ ConfirmationMail job     → dispatch on attendee create
  └─ SendEventReminders3Day   → scheduled daily
  └─ SendEventReminders24Hour → scheduled hourly
```

### New DB Schema

```sql
-- event_images
CREATE TABLE event_images (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id     UUID NOT NULL,
  path         VARCHAR(255) NOT NULL,
  sort_order   TINYINT DEFAULT 0,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- attendees
CREATE TABLE attendees (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id        UUID NOT NULL,
  user_id         BIGINT UNSIGNED NOT NULL,
  registered_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reminded_3day_at  TIMESTAMP NULL,
  reminded_24h_at   TIMESTAMP NULL,
  UNIQUE (event_id, user_id),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
);
```

**`events` table addition:**
- `geocoded_address VARCHAR(255) NULL` — populated once via batch migration

### API Contract — Extended `/events/data`

New request params:
```
?from=2025-01-01        // starts_at >= unix(from)
&to=2025-12-31          // starts_at <= unix(to)
&location=brooklyn      // ILIKE on geocoded_address
&type=concert,festival  // comma-separated types
&status=published
&sort=starts_at_asc     // default: created_time DESC
```

New fields per response item:
```json
{
  "name": "Annual Jazz Festival",
  "geocoded_address": "Brooklyn, New York, US",
  "starts_at": 1718380800,
  "ends_at": 1718395200,
  "venue_name": "The Grand Arena",
  "capacity": 5000,
  "images": [
    { "path": "/storage/events/concert-1.jpg", "sort_order": 0 },
    { "path": "/storage/events/concert-2.jpg", "sort_order": 1 }
  ]
}
```

### JSON Extract Filtering (SQLite / MySQL)

```php
// SQLite
->whereRaw("CAST(json_extract(payload, '$.schedule.starts_at') AS INTEGER) BETWEEN ? AND ?", [$from, $to])
```

For scale (1.25M rows): add generated column `starts_at_extracted` with index.

### Security & Privacy

- Attendee routes behind `auth` middleware
- No PII in queue payloads — load user/event fresh from DB in job handlers
- No file uploads in scope — placeholder images only, no upload validation needed

---

## 4. Risks & Roadmap

### Technical Risks

| Risk | Likelihood | Mitigation |
|------|-----------|------------|
| JSON extract filter slow on 1.25M rows | High | Generated/virtual `starts_at` column with index |
| Geocoding 1.25M rows via external API | High | Static anchor-based lookup — offline, no rate limits |
| Reminder jobs re-firing duplicates | Medium | Guard columns + atomic update before send |
| `storage:link` not run → images 404 | Low | Document in setup steps |

### Phased Rollout

**Phase 1 — Data Enrichment**
- Migrations: `event_images`, `attendees`, `geocoded_address` column
- Seed 16 placeholder images (2 per event type)
- Batch-geocode events using anchor lookup
- Fix `aplyFilters` typo in `Events/Index.vue:148`

**Phase 2 — Visual Pages**
- `VisualOne.vue` — card grid (see [PRD-VisualOne.md](PRD-VisualOne.md))
- `VisualTwo.vue` — timeline (see [PRD-VisualTwo.md](PRD-VisualTwo.md))
- Extend `EventController@data` with new params + response fields

**Phase 3 — Attendee System**
- `AttendeeController` (store, destroy)
- `ConfirmationMail` mailable + Blade template
- `Show.vue` — register button, count, state
- Auth guard + redirect-back

**Phase 4 — Reminders & Polish**
- `SendEventReminders3Day` + `SendEventReminders24Hour` jobs
- Scheduler registration
- Reminder Blade email templates
- Animation pass on both visual pages
- PHPStan + ESLint clean pass
- Decision notes doc
