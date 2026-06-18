<?php

namespace App\Mail;

use App\Models\Attendee;
use App\Models\Event;
use App\Support\EventTime;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttendeeConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Attendee $attendee,
        public Event $event,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->event->payload['name'] ?? 'your event';

        return new Envelope(subject: "You're registered: {$name}");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.attendee-confirmation',
            with: [
                'name' => $this->attendee->name,
                'status' => $this->attendee->status,
                'eventName' => $this->event->payload['name'] ?? 'Your event',
                'when' => EventTime::label($this->event->startsAtTimestamp()),
                'location' => $this->event->geocoded_address,
                'venue' => $this->event->payload['venue']['name'] ?? null,
                'url' => route('events.show', $this->event),
            ],
        );
    }
}
