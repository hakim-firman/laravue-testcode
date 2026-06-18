<?php

use App\Mail\AttendeeConfirmation;
use App\Mail\EventReminder;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/** Create an event a given number of seconds from now. */
function eventStartingIn(int $seconds, string $status = 'published'): Event
{
    return Event::factory()->for(User::factory())->create([
        'status' => $status,
        'payload' => [
            'name' => 'Global Tech Summit',
            'schedule' => ['starts_at' => now()->timestamp + $seconds],
        ],
    ]);
}

it('registers an attendee and sends a confirmation email', function () {
    Mail::fake();
    $event = eventStartingIn(10 * 86400);

    $this->post(route('events.attendees.store', $event), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'status' => 'going',
    ])->assertRedirect();

    $this->assertDatabaseHas('event_attendees', [
        'event_id' => $event->id,
        'email' => 'ada@example.com',
        'status' => 'going',
    ]);

    Mail::assertSent(
        AttendeeConfirmation::class,
        fn ($mail) => $mail->hasTo('ada@example.com'),
    );
});

it('upserts on re-registration instead of duplicating, with no second confirmation', function () {
    Mail::fake();
    $event = eventStartingIn(10 * 86400);

    $payload = ['name' => 'Ada', 'email' => 'ada@example.com', 'status' => 'interested'];
    $this->post(route('events.attendees.store', $event), $payload);
    $this->post(route('events.attendees.store', $event), [...$payload, 'status' => 'going']);

    expect(Attendee::where('event_id', $event->id)->count())->toBe(1);
    expect(Attendee::where('email', 'ada@example.com')->first()->status)->toBe('going');
    Mail::assertSent(AttendeeConfirmation::class, 1);
});

it('rejects a submission that fills the honeypot', function () {
    Mail::fake();
    $event = eventStartingIn(10 * 86400);

    $this->post(route('events.attendees.store', $event), [
        'name' => 'Bot',
        'email' => 'bot@example.com',
        'status' => 'going',
        'website' => 'http://spam.example',
    ])->assertInvalid(['website']);

    $this->assertDatabaseCount('event_attendees', 0);
    Mail::assertNothingSent();
});

it('refuses registration for a cancelled event', function () {
    $event = eventStartingIn(10 * 86400, status: 'cancelled');

    $this->post(route('events.attendees.store', $event), [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'status' => 'going',
    ])->assertStatus(422);

    $this->assertDatabaseCount('event_attendees', 0);
});

it('refuses registration for an event that already started', function () {
    $event = eventStartingIn(-3600); // an hour ago

    $this->post(route('events.attendees.store', $event), [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'status' => 'going',
    ])->assertStatus(422);
});

it('exposes attendees by name and status but never their email', function () {
    $event = eventStartingIn(10 * 86400);
    Attendee::factory()->for($event)->create([
        'name' => 'Ada Lovelace',
        'email' => 'secret@example.com',
        'status' => 'going',
    ]);

    $response = $this->get(route('events.show', $event));

    $response->assertInertia(fn ($page) => $page
        ->component('Events/Show')
        ->where('canRegister', true)
        ->where('attendees.0.name', 'Ada Lovelace')
        ->where('attendees.0.status', 'going')
    );
    $response->assertDontSee('secret@example.com');
});

it('sends the 3-day reminder once and never twice', function () {
    Mail::fake();
    $event = eventStartingIn(2 * 86400); // 2 days out → inside 3-day window only
    Attendee::factory()->for($event)->create(['email' => 'ada@example.com']);

    $this->artisan('events:send-reminders')->assertSuccessful();
    $this->artisan('events:send-reminders')->assertSuccessful();

    Mail::assertSent(
        EventReminder::class,
        fn ($mail) => $mail->window === '3-day' && $mail->hasTo('ada@example.com'),
    );
    Mail::assertSent(EventReminder::class, 1); // not twice
    expect(Attendee::first()->reminded_3day_at)->not->toBeNull();
    expect(Attendee::first()->reminded_24h_at)->toBeNull();
});

it('sends the 24-hour reminder when inside that window', function () {
    Mail::fake();
    $event = eventStartingIn(12 * 3600); // 12h out
    Attendee::factory()->for($event)->create([
        'email' => 'ada@example.com',
        'reminded_3day_at' => now(), // 3-day already handled
    ]);

    $this->artisan('events:send-reminders')->assertSuccessful();

    Mail::assertSent(
        EventReminder::class,
        fn ($mail) => $mail->window === '24-hour',
    );
    Mail::assertSent(EventReminder::class, 1);
});

it('never reminds attendees of cancelled or past events', function () {
    Mail::fake();
    $cancelled = eventStartingIn(2 * 86400, status: 'cancelled');
    $past = eventStartingIn(-86400);
    Attendee::factory()->for($cancelled)->create();
    Attendee::factory()->for($past)->create();

    $this->artisan('events:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
});
