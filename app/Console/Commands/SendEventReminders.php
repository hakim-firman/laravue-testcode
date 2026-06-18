<?php

namespace App\Console\Commands;

use App\Mail\EventReminder;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Email attendees 3 days and 24 hours before their events (idempotent).';

    private const DAY = 86_400;

    public function handle(): int
    {
        $now = now()->timestamp;
        $sent = 0;

        // Only events that actually have attendees can need reminders — a tiny
        // slice of the full table — so the scan stays cheap.
        Event::with('attendees')
            ->whereHas('attendees')
            ->where('status', '!=', 'cancelled')
            ->chunkById(200, function ($events) use ($now, &$sent) {
                foreach ($events as $event) {
                    $startsAt = $event->startsAtTimestamp();
                    if ($startsAt <= $now) {
                        continue; // already started / past
                    }

                    foreach ($event->attendees as $attendee) {
                        if ($attendee->reminded_3day_at === null
                            && $now >= $startsAt - 3 * self::DAY) {
                            Mail::to($attendee->email)
                                ->send(new EventReminder($attendee, $event, '3-day'));
                            $attendee->reminded_3day_at = now();
                            $sent++;
                        }

                        if ($attendee->reminded_24h_at === null
                            && $now >= $startsAt - self::DAY) {
                            Mail::to($attendee->email)
                                ->send(new EventReminder($attendee, $event, '24-hour'));
                            $attendee->reminded_24h_at = now();
                            $sent++;
                        }

                        if ($attendee->isDirty()) {
                            $attendee->save();
                        }
                    }
                }
            });

        $this->info("Sent {$sent} reminder email(s).");

        return self::SUCCESS;
    }
}
