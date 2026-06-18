<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Clock, MapPin, Users } from '@lucide/vue';
import { computed } from 'vue';
import EventCardImage from '@/components/events/EventCardImage.vue';
import EventTypeBadge from '@/components/events/EventTypeBadge.vue';
import { useEventFormat } from '@/composables/useEventFormat';
import type { EnrichedEvent } from '@/types/events';

const props = defineProps<{ event: EnrichedEvent; index: number }>();

const { formatDate, formatTime, formatPrice, formatCapacity } =
    useEventFormat();

// Stagger the entry animation, capped so later cards don't lag noticeably.
const animationDelay = computed(() => `${Math.min(props.index * 30, 300)}ms`);

const location = computed(
    () =>
        props.event.geocoded_address ??
        `${props.event.latitude}, ${props.event.longitude}`,
);

const STATUS_CLASS: Record<string, string> = {
    published:
        'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
    sold_out:
        'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    draft: 'bg-muted text-muted-foreground',
};
const statusClass = computed(
    () => STATUS_CLASS[props.event.status] ?? 'bg-muted text-muted-foreground',
);
const price = computed(() =>
    formatPrice(props.event.min_price, props.event.currency),
);
</script>

<template>
    <Link
        :href="`/events/${event.id}`"
        class="event-card group flex flex-col overflow-hidden rounded-xl border bg-card shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
        :style="{ animationDelay }"
    >
        <div class="relative">
            <EventCardImage :src="event.images[0]" :alt="event.name" />

            <div class="absolute top-3 left-3 flex gap-2">
                <EventTypeBadge :type="event.type" />
            </div>
            <span
                v-if="event.status !== 'published'"
                :class="statusClass"
                class="absolute top-3 right-3 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
            >
                {{ event.status.replace('_', ' ') }}
            </span>
        </div>

        <div class="flex flex-1 flex-col gap-2 p-4">
            <h3 class="line-clamp-2 leading-snug font-semibold">
                {{ event.name }}
            </h3>

            <div
                class="mt-auto flex flex-col gap-1.5 text-sm text-muted-foreground"
            >
                <div class="flex items-center gap-2">
                    <CalendarDays class="size-4 shrink-0" />
                    <span class="truncate">{{
                        formatDate(event.starts_at)
                    }}</span>
                    <Clock class="size-4 shrink-0" />
                    <span class="truncate">{{
                        formatTime(event.starts_at)
                    }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <MapPin class="size-4 shrink-0" />
                    <span class="truncate">{{ location }}</span>
                </div>
            </div>

            <div
                class="mt-1 flex items-center justify-between border-t pt-3 text-xs text-muted-foreground"
            >
                <span v-if="event.capacity" class="flex items-center gap-1">
                    <Users class="size-3.5" />
                    {{ formatCapacity(event.capacity) }}
                </span>
                <span v-if="price" class="font-medium text-foreground">{{
                    price
                }}</span>
            </div>
        </div>
    </Link>
</template>

<style scoped>
.event-card {
    animation: card-fade-up 0.4s ease both;
}

@keyframes card-fade-up {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .event-card {
        animation: none;
    }
}
</style>
