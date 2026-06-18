<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Support\CityResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnrichEvents extends Command
{
    protected $signature = 'events:enrich {--fresh : Re-resolve addresses and re-seed images even if already present}';

    protected $description = 'Generate local placeholder images, geocode addresses, and attach images to events';

    /** Event types and their [from, to] gradient colors. */
    private const TYPE_COLORS = [
        'concert' => ['#7c3aed', '#a855f7'],
        'conference' => ['#2563eb', '#3b82f6'],
        'meetup' => ['#16a34a', '#22c55e'],
        'workshop' => ['#ca8a04', '#eab308'],
        'festival' => ['#ea580c', '#f97316'],
        'sports' => ['#dc2626', '#ef4444'],
        'networking' => ['#0891b2', '#06b6d4'],
        'exhibition' => ['#db2777', '#ec4899'],
    ];

    private const VARIANTS = 3;

    public function handle(): int
    {
        $fresh = (bool) $this->option('fresh');

        $this->writePlaceholders();
        $this->backfillAddresses($fresh);
        $this->seedImages($fresh);

        $this->info('Enrichment complete.');

        return self::SUCCESS;
    }

    /**
     * Write one SVG placeholder per type per variant into public/images/events.
     */
    private function writePlaceholders(): void
    {
        $dir = public_path('images/events');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach (self::TYPE_COLORS as $type => [$from, $to]) {
            for ($variant = 1; $variant <= self::VARIANTS; $variant++) {
                file_put_contents(
                    "{$dir}/{$type}-{$variant}.svg",
                    $this->buildSvg($type, $from, $to, $variant),
                );
            }
        }

        $count = count(self::TYPE_COLORS) * self::VARIANTS;
        $this->info("Wrote {$count} placeholder images to public/images/events.");
    }

    private function buildSvg(string $type, string $from, string $to, int $variant): string
    {
        $label = strtoupper($type);
        $gradId = "g-{$type}-{$variant}";

        // A distinct decorative motif per variant keeps cards from looking identical.
        $motif = match ($variant) {
            1 => '<circle cx="640" cy="110" r="170" fill="#ffffff" opacity="0.10"/>'
                .'<circle cx="150" cy="370" r="120" fill="#ffffff" opacity="0.08"/>',
            2 => '<polygon points="0,450 260,160 520,450" fill="#ffffff" opacity="0.10"/>'
                .'<polygon points="420,450 640,210 800,450" fill="#ffffff" opacity="0.08"/>',
            default => '<path d="M0 330 Q 200 250 400 330 T 800 330 V 450 H 0 Z" fill="#ffffff" opacity="0.10"/>'
                .'<path d="M0 380 Q 200 300 400 380 T 800 380 V 450 H 0 Z" fill="#ffffff" opacity="0.08"/>',
        };

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="800" height="450" viewBox="0 0 800 450" role="img" aria-label="{$label} placeholder">
          <defs>
            <linearGradient id="{$gradId}" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="{$from}"/>
              <stop offset="100%" stop-color="{$to}"/>
            </linearGradient>
          </defs>
          <rect width="800" height="450" fill="url(#{$gradId})"/>
          {$motif}
          <text x="50%" y="50%" dy="0.1em" text-anchor="middle" fill="#ffffff"
                font-family="ui-sans-serif, system-ui, sans-serif" font-size="58" font-weight="700"
                letter-spacing="6" opacity="0.95">{$label}</text>
        </svg>
        SVG;
    }

    /**
     * Snap each event's lat/lng to its nearest city anchor and store the result.
     */
    private function backfillAddresses(bool $fresh): void
    {
        $query = Event::query()->select(['id', 'latitude', 'longitude']);
        if (! $fresh) {
            $query->whereNull('geocoded_address');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Addresses already resolved; skipping.');

            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunkById(1000, function ($events) use ($bar) {
            foreach ($events as $event) {
                $resolved = CityResolver::resolve((float) $event->latitude, (float) $event->longitude);

                $event->geocoded_address = $resolved['address'];
                $event->geocoded_city = $resolved['city'];
                $event->geocoded_country = $resolved['country'];
                $event->save();

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Resolved {$total} addresses.");
    }

    /**
     * Attach placeholder images to each event, reusing the type's SVG files.
     */
    private function seedImages(bool $fresh): void
    {
        if ($fresh) {
            DB::table('event_images')->delete();
        }

        $query = Event::query()->select(['id', 'type']);
        if (! $fresh) {
            $query->whereDoesntHave('images');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Images already attached; skipping.');

            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();
        $now = now();

        $query->orderBy('id')->chunkById(1000, function ($events) use ($bar, $now) {
            $rows = [];

            foreach ($events as $event) {
                $type = array_key_exists($event->type, self::TYPE_COLORS) ? $event->type : 'meetup';
                $rotation = crc32($event->id) % self::VARIANTS;

                for ($i = 0; $i < self::VARIANTS; $i++) {
                    $variant = (($rotation + $i) % self::VARIANTS) + 1;
                    $rows[] = [
                        'event_id' => $event->id,
                        'path' => "/images/events/{$type}-{$variant}.svg",
                        'sort_order' => $i,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $bar->advance();
            }

            DB::table('event_images')->insert($rows);
        });

        $bar->finish();
        $this->newLine();
        $this->info("Attached images to {$total} events.");
    }
}
