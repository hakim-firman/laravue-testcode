<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    private const TYPES = ['concert', 'conference', 'meetup', 'workshop', 'festival', 'sports', 'networking', 'exhibition'];

    private const STATUSES = ['draft', 'published', 'cancelled', 'sold_out'];

    public function index(Request $request): Response
    {
        return Inertia::render('Events/Index', [
            'filters' => [
                'status' => $request->status,
                'from' => $request->input('from', '2023-01-01'),
            ],
            'statuses' => self::STATUSES,
        ]);
    }

    /**
     * Card-grid visual browser (Visual One).
     */
    public function visualOne(Request $request): Response
    {
        return Inertia::render('Events/VisualOne', [
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
            'filters' => $this->currentFilters($request),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        [$events, $stats] = $this->loadListing($request);

        return response()->json([
            'data' => array_map($this->transform(...), $events->items()),
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
            'stats' => $stats,
        ]);
    }

    public function show(Event $event): Response
    {
        $event->load('user', 'images');

        return Inertia::render('Events/Show', [
            'event' => $event,
        ]);
    }

    /**
     * @return array{0: LengthAwarePaginator<int, Event>, 1: array{ms: int, bytes: int}}
     */
    private function loadListing(Request $request): array
    {
        $start = microtime(true);

        $sort = $request->input('sort') === 'starts_at_asc' ? 'asc' : 'desc';

        $events = Event::with(['user', 'images'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->filled('type'), function ($q) use ($request) {
                $types = array_filter(explode(',', (string) $request->input('type')));
                $q->whereIn('type', $types);
            })
            ->when($request->filled('location'), fn ($q) => $q->where('geocoded_address', 'like', '%'.$request->input('location').'%'))
            ->when($request->filled('from'), fn ($q) => $q->where('created_time', '>=', strtotime((string) $request->input('from'))))
            ->when($request->filled('to'), fn ($q) => $q->where('created_time', '<=', strtotime((string) $request->input('to').' 23:59:59')))
            ->orderBy('created_time', $sort)
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'ms' => (int) round((microtime(true) - $start) * 1000),
            'bytes' => strlen((string) json_encode($events->items())),
        ];

        return [$events, $stats];
    }

    /**
     * Flatten an event (plus its JSON payload) into the shape the UI consumes.
     *
     * @return array<string, mixed>
     */
    private function transform(Event $event): array
    {
        $payload = $event->payload ?? [];
        $schedule = $payload['schedule'] ?? [];
        $venue = $payload['venue'] ?? [];
        $pricing = $payload['pricing'] ?? [];

        return [
            'id' => $event->id,
            'type' => $event->type,
            'status' => $event->status,
            'created_time' => $event->created_time,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'name' => $payload['name'] ?? 'Untitled Event',
            'description' => $payload['description'] ?? null,
            'geocoded_address' => $event->geocoded_address,
            'city' => $event->geocoded_city,
            'country' => $event->geocoded_country,
            'starts_at' => isset($schedule['starts_at']) ? (int) $schedule['starts_at'] : $event->created_time,
            'ends_at' => isset($schedule['ends_at']) ? (int) $schedule['ends_at'] : null,
            'venue_name' => $venue['name'] ?? null,
            'capacity' => isset($venue['capacity']) ? (int) $venue['capacity'] : null,
            'min_price' => isset($pricing['min_price']) ? (float) $pricing['min_price'] : null,
            'currency' => $pricing['currency'] ?? 'USD',
            'images' => $event->images->pluck('path')->all(),
            'user' => $event->user ? ['id' => $event->user->id, 'name' => $event->user->name] : null,
        ];
    }

    /**
     * @return array{status: string|null, type: string|null, location: string|null, from: string|null, to: string|null}
     */
    private function currentFilters(Request $request): array
    {
        return [
            'status' => $request->input('status'),
            'type' => $request->input('type'),
            'location' => $request->input('location'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];
    }
}
