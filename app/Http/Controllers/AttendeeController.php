<?php

namespace App\Http\Controllers;

use App\Mail\AttendeeConfirmation;
use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AttendeeController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        abort_if(
            $event->isRegistrationClosed(),
            422,
            'Registration is closed for this event.',
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'status' => ['required', 'in:interested,going'],
            // Honeypot: real users never see or fill this; bots do.
            'website' => ['nullable', 'size:0'],
        ]);

        $attendee = Attendee::firstOrNew([
            'event_id' => $event->id,
            'email' => $data['email'],
        ]);
        $isNew = ! $attendee->exists;

        $attendee->fill([
            'name' => $data['name'],
            'status' => $data['status'],
        ]);

        // Already inside the 3-day window → stamp it so the reminder scan never
        // fires a late 3-day notice for a last-minute signup.
        if ($isNew && $event->startsAtTimestamp() <= now()->addDays(3)->timestamp) {
            $attendee->reminded_3day_at = now();
        }

        $attendee->save();

        if ($isNew) {
            Mail::to($attendee->email)->send(new AttendeeConfirmation($attendee, $event));
        }

        $name = $event->payload['name'] ?? 'this event';
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $isNew
                ? "You're on the list for {$name}. Check your email for confirmation."
                : "Your registration for {$name} was updated.",
        ]);

        return back();
    }
}
