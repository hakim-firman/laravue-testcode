<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock, MapPin } from '@lucide/vue';
import { computed, ref } from 'vue';
import EventTypeBadge from '@/components/events/EventTypeBadge.vue';
import { useEventFormat } from '@/composables/useEventFormat';
import { useScrollReveal } from '@/composables/useScrollReveal';
import type { EnrichedEvent } from '@/types/events';

const props = defineProps<{ event: EnrichedEvent }>();

const { formatTime } = useEventFormat();

const row = ref<HTMLElement | null>(null);
useScrollReveal(row);

const thumb = computed(() => props.event.images[0]);
const thumbFailed = ref(false);

const location = computed(() => {
    const e = props.event;

    return (
        e.geocoded_address ??
        [e.city, e.country].filter(Boolean).join(', ') ??
        `${e.latitude}, ${e.longitude}`
    );
});

const STATUS_CLASS: Record<string, string> = {
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
    sold_out:
        'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    draft: 'bg-muted text-muted-foreground',
};
const statusClass = computed(
    () => STATUS_CLASS[props.event.status] ?? 'bg-muted text-muted-foreground',
);
</script>

<template>
    <!-- ref lives on a real element so IntersectionObserver can observe it
         (a ref on the <Link> component would yield its instance, not a node). -->
    <div ref="row" class="reveal-row">
        <Link
            :href="`/events/${event.id}`"
            class="group flex h-[72px] items-center gap-3 rounded-lg px-3 transition-colors hover:bg-muted/60"
        >
            <!-- Thumbnail (fixed size, no reflow) -->
            <div
                class="size-16 shrink-0 overflow-hidden rounded-md bg-muted"
                aria-hidden="true"
            >
                <img
                    v-if="thumb && !thumbFailed"
                    :src="thumb"
                    :alt="event.name"
                    loading="lazy"
                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    @error="thumbFailed = true"
                />
                <div
                    v-else
                    class="h-full w-full bg-gradient-to-br from-muted-foreground/20 to-muted-foreground/5"
                />
            </div>

            <!-- Metadata -->
            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                <h3 class="truncate leading-snug font-semibold">
                    {{ event.name }}
                </h3>
                <div
                    class="flex min-w-0 items-center gap-1.5 text-sm text-muted-foreground"
                >
                    <MapPin class="size-3.5 shrink-0" />
                    <span class="truncate">{{ location }}</span>
                </div>
            </div>

            <!-- Time + badges -->
            <div class="flex shrink-0 flex-col items-end gap-1.5">
                <span
                    class="flex items-center gap-1 text-sm font-medium tabular-nums"
                >
                    <Clock class="size-3.5 text-muted-foreground" />
                    {{ formatTime(event.starts_at) }}
                </span>
                <div class="flex items-center gap-1.5">
                    <span
                        v-if="event.status !== 'published'"
                        :class="statusClass"
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                    >
                        {{ event.status.replace('_', ' ') }}
                    </span>
                    <EventTypeBadge :type="event.type" />
                </div>
            </div>
        </Link>
    </div>
</template>

<style scoped>
.reveal-row {
    transform: translateX(-16px);
    opacity: 0;
    transition:
        transform 0.3s ease,
        opacity 0.3s ease;
}

.reveal-row.revealed {
    transform: none;
    opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
    .reveal-row,
    .reveal-row.revealed {
        transform: none;
        opacity: 1;
        transition: none;
    }
}
</style>
