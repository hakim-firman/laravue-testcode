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

class EventReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  '3-day'|'24-hour'  $window
     */
    public function __construct(
        public Attendee $attendee,
        public Event $event,
        public string $window,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->event->payload['name'] ?? 'your event';
        $lead = $this->window === '24-hour' ? 'is tomorrow' : 'is in 3 days';

        return new Envelope(subject: "Reminder: {$name} {$lead}");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.event-reminder',
            with: [
                'name' => $this->attendee->name,
                'eventName' => $this->event->payload['name'] ?? 'Your event',
                'lead' => $this->window === '24-hour'
                    ? 'is happening tomorrow'
                    : 'is just 3 days away',
                'when' => EventTime::label($this->event->startsAtTimestamp()),
                'location' => $this->event->geocoded_address,
                'venue' => $this->event->payload['venue']['name'] ?? null,
                'url' => route('events.show', $this->event),
            ],
        );
    }
}
