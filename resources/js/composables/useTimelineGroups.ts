import type { EnrichedEvent } from '@/types/events';

export interface DateGroup {
    /** 'YYYY-MM-DD' in the browser timezone. */
    dateKey: string;
    /** Display label, e.g. 'Sat · Jun 14, 2025'. */
    label: string;
    /** 'Today' | 'Tomorrow' | 'This Weekend' | '' */
    relativeLabel: string;
    /** Group falls before today (browser-local). */
    isPast: boolean;
    events: EnrichedEvent[];
}

const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;

// en-CA yields 'YYYY-MM-DD', which sorts lexicographically as chronological.
const keyFormatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: tz,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

const labelFormatter = new Intl.DateTimeFormat('en-US', {
    timeZone: tz,
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
});

const DAY_MS = 86_400_000;

/** Browser-local date key for a unix-seconds instant. */
function dateKeyOf(unixSeconds: number): string {
    return keyFormatter.format(new Date(unixSeconds * 1000));
}

/**
 * Derive label, relative label, and past-ness for a date key. The key is
 * already in browser-local time, so we anchor a local Date at noon to dodge
 * DST/midnight edges, then compare whole-day deltas against today.
 */
function describeGroup(dateKey: string): Omit<DateGroup, 'dateKey' | 'events'> {
    const [year, month, day] = dateKey.split('-').map(Number);
    const date = new Date(year, month - 1, day, 12);

    const today = new Date();
    today.setHours(12, 0, 0, 0);

    const diffDays = Math.round((date.getTime() - today.getTime()) / DAY_MS);

    let relativeLabel = '';

    if (diffDays === 0) {
        relativeLabel = 'Today';
    } else if (diffDays === 1) {
        relativeLabel = 'Tomorrow';
    } else if (diffDays > 1 && diffDays <= 7) {
        const dow = date.getDay(); // 0 Sun … 6 Sat

        if (dow === 0 || dow === 6) {
            relativeLabel = 'This Weekend';
        }
    }

    return {
        label: labelFormatter.format(date),
        relativeLabel,
        isPast: diffDays < 0,
    };
}

function makeGroup(dateKey: string, events: EnrichedEvent[] = []): DateGroup {
    return { dateKey, ...describeGroup(dateKey), events };
}

/**
 * Group a flat event list into ascending day buckets (soonest first). Used for
 * the initial page; later pages go through {@link mergeIntoGroups}.
 */
export function groupByDate(events: EnrichedEvent[]): DateGroup[] {
    return mergeIntoGroups([], events, new Set<string>());
}

/**
 * Merge a new page of events into existing groups, returning a fresh array
 * (new group objects + event arrays) so Vue reactivity picks up the change.
 * Deduplicates by event id via the caller-owned `seen` set, keeps each group's
 * events sorted by start time, and keeps groups sorted by date.
 */
export function mergeIntoGroups(
    existing: DateGroup[],
    incoming: EnrichedEvent[],
    seen: Set<string>,
): DateGroup[] {
    const byKey = new Map<string, DateGroup>();

    for (const group of existing) {
        byKey.set(group.dateKey, { ...group, events: group.events.slice() });
    }

    for (const event of incoming) {
        if (seen.has(event.id)) {
            continue;
        }

        seen.add(event.id);

        const key = dateKeyOf(event.starts_at);
        let group = byKey.get(key);

        if (!group) {
            group = makeGroup(key);
            byKey.set(key, group);
        }

        group.events.push(event);
    }

    const result = [...byKey.values()];

    for (const group of result) {
        group.events.sort((a, b) => a.starts_at - b.starts_at);
    }

    result.sort((a, b) => a.dateKey.localeCompare(b.dateKey));

    return result;
}
