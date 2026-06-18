<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarX2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import EventCard from '@/components/events/EventCard.vue';
import EventFilterBar from '@/components/events/EventFilterBar.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type {
    EnrichedEvent,
    EventDataResponse,
    EventFilters,
} from '@/types/events';

const props = defineProps<{
    types: string[];
    statuses: string[];
    filters: EventFilters;
}>();

const filters = ref<EventFilters>({ ...props.filters });
const rows = ref<EnrichedEvent[]>([]);
const page = ref(0);
const lastPage = ref<number | null>(null);
const total = ref<number | null>(null);
const loading = ref(false);
const errored = ref(false);
const hasLoadedOnce = ref(false);

const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const hasMore = computed(
    () => lastPage.value === null || page.value < lastPage.value,
);
const isEmpty = computed(
    () => hasLoadedOnce.value && !loading.value && rows.value.length === 0,
);

function buildParams(): URLSearchParams {
    const params = new URLSearchParams({ page: String(page.value + 1) });

    if (filters.value.status) {
        params.set('status', filters.value.status);
    }

    if (filters.value.type) {
        params.set('type', filters.value.type);
    }

    if (filters.value.location) {
        params.set('location', filters.value.location);
    }

    if (filters.value.from) {
        params.set('from', filters.value.from);
    }

    if (filters.value.to) {
        params.set('to', filters.value.to);
    }

    return params;
}

function syncUrl() {
    const params = new URLSearchParams();

    if (filters.value.status) {
        params.set('status', filters.value.status);
    }

    if (filters.value.type) {
        params.set('type', filters.value.type);
    }

    if (filters.value.location) {
        params.set('location', filters.value.location);
    }

    if (filters.value.from) {
        params.set('from', filters.value.from);
    }

    if (filters.value.to) {
        params.set('to', filters.value.to);
    }

    const query = params.toString();
    window.history.replaceState(
        {},
        '',
        query ? `?${query}` : window.location.pathname,
    );
}

async function loadMore() {
    if (loading.value || !hasMore.value) {
        return;
    }

    loading.value = true;
    errored.value = false;

    try {
        const response = await fetch(
            `/events/data?${buildParams().toString()}`,
            {
                headers: { Accept: 'application/json' },
            },
        );

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const payload: EventDataResponse = await response.json();

        rows.value.push(...payload.data);
        page.value = payload.current_page;
        lastPage.value = payload.last_page;
        total.value = payload.total;
        hasLoadedOnce.value = true;
    } catch {
        errored.value = true;
    } finally {
        loading.value = false;
    }
}

function applyFilters(next: EventFilters) {
    filters.value = next;
    rows.value = [];
    page.value = 0;
    lastPage.value = null;
    total.value = null;
    hasLoadedOnce.value = false;
    errored.value = false;
    syncUrl();
    loadMore();
}

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0]?.isIntersecting) {
                loadMore();
            }
        },
        { rootMargin: '400px' },
    );

    if (sentinel.value) {
        observer.observe(sentinel.value);
    }

    loadMore();
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <Head title="Events Visual 1" />

    <div class="flex flex-col gap-4 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Discover Events
            </h1>
            <p class="text-sm text-muted-foreground">
                {{
                    total !== null
                        ? `${total.toLocaleString()} events`
                        : 'Browsing events…'
                }}
            </p>
        </div>

        <EventFilterBar
            :types="types"
            :statuses="statuses"
            :initial="filters"
            @apply="applyFilters"
        />

        <!-- Empty state -->
        <div
            v-if="isEmpty"
            class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed py-20 text-center"
        >
            <CalendarX2 class="size-10 text-muted-foreground" />
            <div>
                <p class="font-medium">No events found</p>
                <p class="text-sm text-muted-foreground">
                    Try widening your date range or clearing filters.
                </p>
            </div>
        </div>

        <!-- Card grid -->
        <div
            v-else
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <EventCard
                v-for="(event, i) in rows"
                :key="event.id"
                :event="event"
                :index="i"
            />

            <!-- Skeleton placeholders while loading -->
            <template v-if="loading">
                <div
                    v-for="n in 6"
                    :key="`sk-${n}`"
                    class="flex flex-col gap-3 rounded-xl border p-0"
                >
                    <Skeleton
                        class="aspect-video w-full rounded-none rounded-t-xl"
                    />
                    <div class="flex flex-col gap-2 p-4">
                        <Skeleton class="h-4 w-3/4" />
                        <Skeleton class="h-3 w-1/2" />
                        <Skeleton class="h-3 w-2/3" />
                    </div>
                </div>
            </template>
        </div>

        <!-- Error / retry -->
        <div
            v-if="errored"
            class="flex flex-col items-center gap-2 py-6 text-center"
        >
            <p class="text-sm text-muted-foreground">Failed to load events.</p>
            <Button variant="outline" @click="loadMore">Retry</Button>
        </div>

        <!-- Infinite-scroll sentinel -->
        <div ref="sentinel" class="h-px"></div>

        <!-- End of results -->
        <p
            v-if="hasLoadedOnce && !hasMore && rows.length > 0"
            class="py-4 text-center text-sm text-muted-foreground"
        >
            Showing all {{ total?.toLocaleString() }} events
        </p>
    </div>
</template>
