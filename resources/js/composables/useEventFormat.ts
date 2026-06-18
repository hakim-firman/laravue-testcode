const userTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

const dateFormatter = new Intl.DateTimeFormat('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    timeZone: userTimeZone,
});

const timeFormatter = new Intl.DateTimeFormat('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    timeZoneName: 'short',
    timeZone: userTimeZone,
});

/** Tailwind classes per event type — light + dark aware. */
export const TYPE_COLORS: Record<string, string> = {
    concert:
        'bg-purple-100 text-purple-700 dark:bg-purple-500/15 dark:text-purple-300',
    conference:
        'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
    meetup: 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300',
    workshop:
        'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-300',
    festival:
        'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
    sports: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
    networking:
        'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300',
    exhibition:
        'bg-pink-100 text-pink-700 dark:bg-pink-500/15 dark:text-pink-300',
};

export function useEventFormat() {
    const formatDate = (unixSeconds: number | null): string => {
        if (!unixSeconds) {
            return 'Date TBD';
        }

        return dateFormatter.format(new Date(unixSeconds * 1000));
    };

    const formatTime = (unixSeconds: number | null): string => {
        if (!unixSeconds) {
            return '';
        }

        return timeFormatter.format(new Date(unixSeconds * 1000));
    };

    const formatPrice = (price: number | null, currency = 'USD'): string => {
        if (price === null) {
            return '';
        }

        if (price === 0) {
            return 'Free';
        }

        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency,
        }).format(price);
    };

    const formatCapacity = (capacity: number | null): string => {
        if (!capacity) {
            return '';
        }

        return `${capacity.toLocaleString()} cap`;
    };

    const typeBadgeClass = (type: string): string =>
        TYPE_COLORS[type] ?? 'bg-muted text-muted-foreground';

    return {
        userTimeZone,
        formatDate,
        formatTime,
        formatPrice,
        formatCapacity,
        typeBadgeClass,
    };
}
