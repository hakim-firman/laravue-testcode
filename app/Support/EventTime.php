<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Formats event start times for server-side contexts (emails), where no
 * browser timezone is available. Renders in UTC with an explicit label so the
 * reader is never misled about the zone.
 */
class EventTime
{
    public static function label(int $unixSeconds): string
    {
        return CarbonImmutable::createFromTimestamp($unixSeconds, 'UTC')
            ->format('D, M j, Y \a\t g:i A T');
    }
}
