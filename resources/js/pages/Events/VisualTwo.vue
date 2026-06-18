<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarX2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import EventFilterBar from '@/components/events/EventFilterBar.vue';
import EventTimeline from '@/components/events/EventTimeline.vue';
import EventTimelineRowSkeleton from '@/components/events/EventTimelineRowSkeleton.vue';
import { Button } from '@/components/ui/button';
import { mergeIntoGroups } from '@/composables/useTimelineGroups';
import type { DateGroup } from '@/composables/useTimelineGroups';
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
const groups = ref<DateGroup[]>([]);
const seen = new Set<string>();
const page = ref(0);
const lastPage = ref<number | null>(null);
const total = ref<number | null>(null);
const loading = ref(false);
const errored = ref(false);
const hasLoadedOnce = ref(false);

// Measured filter-bar height → day headers stick directly beneath it.
const filterWrap = ref<HTMLElement | null>(null);
const filterHeight = ref(56);
let resizeObserver: ResizeObserver | null = null;

const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const hasMore = computed(
    () => lastPage.value === null || page.value < lastPage.value,
);
const isEmpty = computed(
    () => hasLoadedOnce.value && !loading.value && groups.value.length === 0,
);

function buildParams(): URLSearchParams {
    const params = new URLSearchParams({
        page: String(page.value + 1),
        // Ascending so the timeline reads soonest-first.
        sort: 'starts_at_asc',
    });

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
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const payload: EventDataResponse = await response.json();

        groups.value = mergeIntoGroups(
            groups.value,
            payload.data as EnrichedEvent[],
            seen,
        );
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
    groups.value = [];
    seen.clear();
    page.value = 0;
    lastPage.value = null;
    total.value = null;
    hasLoadedOnce.value = false;
    errored.value = false;
    syncUrl();
    window.scrollTo({ top: 0, behavior: 'smooth' });
    loadMore();
}

onMounted(() => {
    if (filterWrap.value) {
        resizeObserver = new ResizeObserver((entries) => {
            const h = entries[0]?.contentRect.height;

            if (h) {
                filterHeight.value = Math.round(h);
            }
        });
        resizeObserver.observe(filterWrap.value);
    }

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

onBeforeUnmount(() => {
    observer?.disconnect();
    resizeObserver?.disconnect();
});
</script>

<template>
    <Head title="Events Visual 2" />

    <div class="flex flex-col gap-4 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Events Timeline
            </h1>
            <p class="text-sm text-muted-foreground">
                {{
                    total !== null
                        ? `${total.toLocaleString()} events`
                        : 'Building timeline…'
                }}
            </p>
        </div>

        <div ref="filterWrap">
            <EventFilterBar
                :types="types"
                :statuses="statuses"
                :initial="filters"
                @apply="applyFilters"
            />
        </div>

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

        <!-- Timeline -->
        <EventTimeline v-else :groups="groups" :sticky-top="filterHeight" />

        <!-- Loading skeletons appended under the last day group -->
        <div
            v-if="loading"
            class="ml-2 flex flex-col gap-1 border-l-2 border-border/70 pl-3"
        >
            <EventTimelineRowSkeleton v-for="n in 3" :key="`sk-${n}`" />
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
            v-if="hasLoadedOnce && !hasMore && groups.length > 0"
            class="py-4 text-center text-sm text-muted-foreground"
        >
            Showing all {{ total?.toLocaleString() }} events
        </p>
    </div>
</template>
