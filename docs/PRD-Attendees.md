# PRD — Attendees & Emails

## 1. Executive Summary

**Problem:** Events can be browsed but not joined. There is no way to register interest or attendance, no attendee list, and no transactional or reminder email — the entire "Attendees & emails" requirement of the test is unbuilt.

**Solution:** A lightweight, account-free RSVP flow. Anyone can register their **interest** or **attendance** for an event with a name + email. Registration immediately triggers a confirmation email. A scheduled command scans upcoming events and emails attendees **3 days** and **24 hours** before the event, exactly once per window.

**Why email-only (no login):** Mirrors how public event pages actually work — visitors RSVP without creating an account. Keeps the flow to a single form and matches the test's literal "email them to confirm they're on the list." Trade-off: the form is public, so it needs validation, deduplication by email, and basic bot/abuse defenses (see Risks).

**Success Criteria:**
1. A visitor can register interest/attendance from an event page and sees immediate feedback.
2. Re-registering with the same email for the same event updates the record instead of duplicating it.
3. A confirmation email is sent on first registration (visible in the `log` mailer during dev).
4. Each attendee receives a 3-day and a 24-hour reminder, each sent **at most once**, even if the scheduler runs many times or the server was down for a stretch.
5. Cancelled and past events never generate reminders.
6. The attendee list (count + names) is visible on the event page without exposing email addresses.

---

## 2. User Experience & Functionality

### Where it lives

The event detail page (`Events/Show.vue`, currently a raw JSON dump) becomes the home for RSVP. It gains:

- A **registration card**: name, email, and a choice of **Interested** or **Going**.
- An **attendee list**: total count + going/interested breakdown, and a list of attendee **names** (emails hidden).

### User Stories & Acceptance Criteria

#### Story 1 — Register Interest / Attendance

> As a visitor, I want to say I'm interested in or going to an event so the organizer knows.

- Fields: `name` (required, ≤ 100), `email` (required, valid), `status` (`interested` | `going`, default `going`).
- Submit posts to `POST /events/{event}/attendees` (Inertia form).
- One record per `(event_id, email)`. Re-submitting the same email **updates** name/status (no duplicate row).
- Success: inline toast (`vue-sonner`, already wired) + the attendee list updates.
- Validation errors render inline under each field.
- Cannot register for a **cancelled** or already-**past** event (button disabled + server guard).

#### Story 2 — Confirmation Email

> As a registrant, I want an email confirming I'm on the list.

- Sent on **first** registration for that email+event (not on subsequent status edits).
- Contains: event name, date/time, location (`geocoded_address`), the chosen status, and the venue if present.
- Delivered via a queued Mailable; with the default `log` mailer it lands in `storage/logs/laravel.log`.

#### Story 3 — Attendee List

> As a visitor, I want to see who else is coming.

- Shows total count and a `going` / `interested` split.
- Lists attendee **names only** — emails are never sent to the client.
- Empty state: "Be the first to register."

#### Story 4 — Reminder Emails (3 days + 24 hours)

> As a registrant, I want reminders as the event approaches.

- A scheduled command `events:send-reminders` runs hourly.
- For every attendee of an **upcoming, non-cancelled** event:
  - If `now ≥ starts_at − 3 days` and the 3-day reminder hasn't been sent → send it, stamp `reminded_3day_at`.
  - If `now ≥ starts_at − 24 hours` and the 24-hour reminder hasn't been sent → send it, stamp `reminded_24h_at`.
- Each window sends **at most once** per attendee (idempotent via the stamp columns).
- If the event is already within 24h when an attendee registers, they still get the 24-hour reminder but the (now-moot) 3-day window is marked skipped so it never fires late.
- Past events and cancelled events are excluded.

### Non-Goals

- User accounts / login-gated RSVP (explicitly chose email-only).
- Capacity limits, waitlists, ticketing, or payment.
- Editing/cancelling an RSVP from a link in the email (a cancel endpoint is a nice-to-have, not required).
- SMS/push reminders, calendar (`.ics`) attachments.
- Per-event timezone resolution for emails (see Risks — emails render times in UTC, labeled).

---

## 3. Technical Specifications

### Data Model

New migration `create_event_attendees_table`:

```php
Schema::create('event_attendees', function (Blueprint $table) {
    $table->id();
    $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
    $table->string('name');
    $table->string('email');
    $table->string('status')->default('going'); // 'interested' | 'going'
    $table->timestamp('reminded_3day_at')->nullable();
    $table->timestamp('reminded_24h_at')->nullable();
    $table->timestamps();

    $table->unique(['event_id', 'email']);
    $table->index('event_id');
});
```

`reminded_*_at` double as **idempotency markers**: a non-null value means "already handled (sent or deliberately skipped)".

### Models

```php
// app/Models/Attendee.php
class Attendee extends Model
{
    protected $table = 'event_attendees';
    protected $guarded = [];
    protected $casts = [
        'reminded_3day_at' => 'datetime',
        'reminded_24h_at' => 'datetime',
    ];
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
}

// Event.php — add
public function attendees(): HasMany { return $this->hasMany(Attendee::class); }
```

### Start-time source

`starts_at` lives in the event `payload` JSON (`schedule.starts_at`, unix seconds), with `created_time` as fallback — same convention the listing already uses. The reminder scan only touches events that **have attendees** (`whereHas('attendees')`), a small set, so we compute `starts_at` in PHP rather than adding/indexing a column. No schema change to `events`.

### Registration Endpoint

```
POST /events/{event}/attendees   → AttendeeController@store
```

```php
public function store(Request $request, Event $event)
{
    abort_if($this->isClosed($event), 422, 'Registration closed for this event.');

    $data = $request->validate([
        'name' => ['required', 'string', 'max:100'],
        'email' => ['required', 'email', 'max:255'],
        'status' => ['required', 'in:interested,going'],
        'website' => ['nullable', 'size:0'], // honeypot — must be empty
    ]);

    $attendee = Attendee::firstOrNew([
        'event_id' => $event->id,
        'email' => $data['email'],
    ]);
    $isNew = ! $attendee->exists;

    $attendee->fill(['name' => $data['name'], 'status' => $data['status']]);

    // If the event is already inside the 3-day window, mark it skipped so the
    // scan never sends a late 3-day reminder.
    if ($isNew && $this->startsAt($event) <= now()->addDays(3)->timestamp) {
        $attendee->reminded_3day_at = now();
    }
    $attendee->save();

    if ($isNew) {
        Mail::to($attendee->email)->queue(new AttendeeConfirmation($attendee, $event));
    }

    return back()->with('success', "You're on the list for {$event->payload['name']}.");
}
```

Throttle the route (`throttle:10,1`) and use the honeypot field to blunt bots.

### Mailables

```php
// app/Mail/AttendeeConfirmation.php  (implements ShouldQueue)
//   subject: "You're registered: {event name}"
//   data: event name, formatted date/time (UTC, labeled), location, status, venue

// app/Mail/EventReminder.php  (implements ShouldQueue)
//   ctor: (Attendee $attendee, Event $event, string $window) // '3-day' | '24-hour'
//   subject: "Reminder: {event name} is in 3 days" | "... is tomorrow"
```

Both render Blade views under `resources/views/mail/`. With `MAIL_MAILER=log` (current `.env`) they are written to the log; `queue()` runs inline under the `sync` queue driver by default.

### Reminder Command

```php
// app/Console/Commands/SendEventReminders.php
// signature: events:send-reminders
final class SendEventReminders extends Command
{
    public function handle(): int
    {
        $now = now();

        Event::with('attendees')
            ->whereHas('attendees')
            ->where('status', '!=', 'cancelled')
            ->chunkById(200, function ($events) use ($now) {
                foreach ($events as $event) {
                    $startsAt = $this->startsAt($event); // unix
                    if ($startsAt <= $now->timestamp) {
                        continue; // past event
                    }

                    foreach ($event->attendees as $attendee) {
                        if ($attendee->reminded_3day_at === null
                            && $now->timestamp >= $startsAt - 3 * 86400) {
                            Mail::to($attendee->email)
                                ->queue(new EventReminder($attendee, $event, '3-day'));
                            $attendee->reminded_3day_at = $now;
                        }
                        if ($attendee->reminded_24h_at === null
                            && $now->timestamp >= $startsAt - 86400) {
                            Mail::to($attendee->email)
                                ->queue(new EventReminder($attendee, $event, '24-hour'));
                            $attendee->reminded_24h_at = $now;
                        }
                        $attendee->save();
                    }
                }
            });

        return self::SUCCESS;
    }
}
```

### Scheduler

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('events:send-reminders')->hourly()->withoutOverlapping();
```

### Frontend (`Events/Show.vue`)

- Replace the raw JSON dump with a proper detail view (image, title, description, date/time, location) plus the RSVP card and attendee list.
- Use the Inertia `useForm` helper for the POST; on success reset the form and let the redirected props refresh the list.
- Reuse `useEventFormat` for date/time, `EventTypeBadge`, `EventCardImage`.
- Controller `show()` passes `attendees` (name + status only) and `attendeeCount`.

### Controller wiring (`EventController@show`)

```php
$event->load(['user', 'images', 'attendees']);
return Inertia::render('Events/Show', [
    'event' => $event,
    'attendees' => $event->attendees->map(fn ($a) => [
        'name' => $a->name, 'status' => $a->status,
    ]),
    'canRegister' => ! $this->isClosed($event),
]);
```

(Emails are never included in the payload.)

---

## 4. Risks & Roadmap

| Risk | Mitigation |
|------|-----------|
| Public form invites spam/bots | `throttle:10,1` on the route + honeypot field; server-side email validation |
| Duplicate RSVPs | `unique(event_id, email)` + `firstOrNew` upsert by email |
| Double-sent reminders (scheduler overlap / retries) | `reminded_*_at` stamp columns gate each window; `withoutOverlapping()` on the schedule |
| Missed reminders after downtime | Scan compares `now ≥ starts_at − window` (not an exact instant), so a late run still catches and sends pending reminders |
| Late 3-day reminder for last-minute signups | On registration inside the 3-day window, stamp `reminded_3day_at` to skip it |
| `starts_at` missing on some events | Fall back to `created_time`, matching the listing transform |
| Emails have no per-event timezone | Render times in **UTC, explicitly labeled**; per-event TZ is a non-goal |
| Querying 1.25M events for reminders | Scan is scoped to `whereHas('attendees')` — only RSVP'd events, a small set; chunked |

### Build Order

1. Migration `create_event_attendees_table` + `Attendee` model + `Event::attendees()` relation
2. `AttendeeController@store` + route (throttle + honeypot) + `isClosed`/`startsAt` helpers
3. `AttendeeConfirmation` mailable + Blade view
4. `Show.vue` rebuild: detail view + RSVP form + attendee list; `EventController@show` props
5. `EventReminder` mailable (`3-day` / `24-hour`) + Blade view
6. `events:send-reminders` command + idempotency logic
7. Scheduler entry in `routes/console.php`
8. Tests (Pest): registration upsert + dedup, confirmation queued on new only, reminder sent-once per window, cancelled/past excluded
