<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the events listing shell without authentication', function () {
    $this->get(route('events.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Index')
            ->has('statuses', 4)
            ->where('filters.from', '2023-01-01')
        );
});

it('returns a json page of events with load stats for lazy loading', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    Event::factory()->for($user)->create([
        'type' => 'concert',
        'status' => 'published',
        'created_time' => 1_700_000_000,
        'latitude' => 40.7128,
        'longitude' => -74.0060,
    ]);

    $this->getJson(route('events.data'))
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'current_page',
            'last_page',
            'total',
            'stats' => ['ms', 'bytes'],
        ])
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.type', 'concert')
        ->assertJsonPath('data.0.created_time', 1_700_000_000)
        ->assertJsonPath('data.0.latitude', 40.7128)
        ->assertJsonPath('data.0.user.name', 'Ada Lovelace');
});

it('filters the data endpoint by status', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create(['status' => 'published']);
    Event::factory()->for($user)->create(['status' => 'cancelled']);

    $this->getJson(route('events.data', ['status' => 'cancelled']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.status', 'cancelled');
});

it('shows an event detail page with its payload', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user)->create([
        'payload' => ['name' => 'Global Tech Summit', 'location' => ['lat' => 1.5, 'lng' => 2.5]],
    ]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Show')
            ->where('event.id', $event->id)
            ->where('event.payload.name', 'Global Tech Summit')
        );
});

it('renders the two visualization pages and the dashboard without authentication', function () {
    $this->get(route('events.visual1'))->assertOk();
    $this->get(route('events.visual2'))->assertOk();
    $this->get(route('dashboard'))->assertOk();
});

it('renders the visual one card grid with type and status options', function () {
    $this->get(route('events.visual1'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/VisualOne')
            ->has('types', 8)
            ->has('statuses', 4)
        );
});

it('returns enriched event fields for the card grid', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user)->create([
        'status' => 'published',
        'geocoded_address' => 'Berlin, Germany',
        'geocoded_city' => 'Berlin',
        'geocoded_country' => 'Germany',
        'payload' => [
            'name' => 'Global Tech Summit',
            'description' => 'A summit.',
            'venue' => ['name' => 'The Grand Arena', 'capacity' => 5000],
            'schedule' => ['starts_at' => 1_700_000_000, 'ends_at' => 1_700_007_200],
            'pricing' => ['currency' => 'USD', 'min_price' => 49.5],
        ],
    ]);
    $event->images()->create(['path' => '/images/events/conference-1.svg', 'sort_order' => 0]);

    $this->getJson(route('events.data'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Global Tech Summit')
        ->assertJsonPath('data.0.geocoded_address', 'Berlin, Germany')
        ->assertJsonPath('data.0.city', 'Berlin')
        ->assertJsonPath('data.0.starts_at', 1_700_000_000)
        ->assertJsonPath('data.0.venue_name', 'The Grand Arena')
        ->assertJsonPath('data.0.capacity', 5000)
        ->assertJsonPath('data.0.images.0', '/images/events/conference-1.svg');
});

it('filters the data endpoint by type', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create(['type' => 'concert']);
    Event::factory()->for($user)->create(['type' => 'workshop']);

    $this->getJson(route('events.data', ['type' => 'concert']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.type', 'concert');
});

it('filters the data endpoint by location', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create(['geocoded_address' => 'Berlin, Germany']);
    Event::factory()->for($user)->create(['geocoded_address' => 'Tokyo, Japan']);

    $this->getJson(route('events.data', ['location' => 'berlin']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.geocoded_address', 'Berlin, Germany');
});

it('filters the data endpoint by a date range', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create(['created_time' => strtotime('2026-02-15')]);
    Event::factory()->for($user)->create(['created_time' => strtotime('2026-08-15')]);

    $this->getJson(route('events.data', ['from' => '2026-01-01', 'to' => '2026-03-01']))
        ->assertOk()
        ->assertJsonPath('total', 1);
});
