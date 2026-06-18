<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EventImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(EventImage::class)->orderBy('sort_order');
    }

    /** @return HasMany<Attendee, $this> */
    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    /**
     * Event start as a unix timestamp. Prefers the scheduled start in the
     * payload, falling back to created_time (same convention as the listing).
     */
    public function startsAtTimestamp(): int
    {
        $scheduled = $this->payload['schedule']['starts_at'] ?? null;

        return (int) ($scheduled ?? $this->created_time ?? 0);
    }

    /** Registration is closed once the event is cancelled or has started. */
    public function isRegistrationClosed(): bool
    {
        return $this->status === 'cancelled'
            || $this->startsAtTimestamp() <= now()->timestamp;
    }
}
