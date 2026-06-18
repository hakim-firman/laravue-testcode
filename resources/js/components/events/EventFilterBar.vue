<script setup lang="ts">
import { Search, X } from '@lucide/vue';
import { reactive } from 'vue';
import EventTypeBadge from '@/components/events/EventTypeBadge.vue';
import { Button } from '@/components/ui/button';
import type { EventFilters } from '@/types/events';

const props = defineProps<{
    types: string[];
    statuses: string[];
    initial: EventFilters;
}>();

const emit = defineEmits<{ apply: [filters: EventFilters] }>();

const form = reactive<EventFilters>({
    status: props.initial.status ?? '',
    type: props.initial.type ?? '',
    location: props.initial.location ?? '',
    from: props.initial.from ?? '',
    to: props.initial.to ?? '',
});

// `type` travels over the wire as a comma-separated string; track selection as a Set.
const selectedTypes = reactive(
    new Set<string>(form.type ? form.type.split(',') : []),
);

function toggleType(type: string) {
    if (selectedTypes.has(type)) {
        selectedTypes.delete(type);
    } else {
        selectedTypes.add(type);
    }
}

function apply() {
    emit('apply', {
        status: form.status || null,
        type: selectedTypes.size ? [...selectedTypes].join(',') : null,
        location: form.location || null,
        from: form.from || null,
        to: form.to || null,
    });
}

function clear() {
    form.status = '';
    form.location = '';
    form.from = '';
    form.to = '';
    selectedTypes.clear();
    apply();
}
</script>

<template>
    <div
        class="sticky top-0 z-20 -mx-4 border-b bg-background/80 px-4 py-3 backdrop-blur-sm"
    >
        <form class="flex flex-col gap-3" @submit.prevent="apply">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex min-w-[180px] flex-1 flex-col gap-1">
                    <label
                        class="text-xs font-medium text-muted-foreground"
                        for="location"
                        >Location</label
                    >
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <input
                            id="location"
                            v-model="form.location"
                            type="text"
                            placeholder="City or country…"
                            class="h-9 w-full rounded-md border border-input bg-background pr-3 pl-8 text-sm"
                        />
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <label
                        class="text-xs font-medium text-muted-foreground"
                        for="from"
                        >From</label
                    >
                    <input
                        id="from"
                        v-model="form.from"
                        type="date"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    />
                </div>

                <div class="flex flex-col gap-1">
                    <label
                        class="text-xs font-medium text-muted-foreground"
                        for="to"
                        >To</label
                    >
                    <input
                        id="to"
                        v-model="form.to"
                        type="date"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    />
                </div>

                <div class="flex flex-col gap-1">
                    <label
                        class="text-xs font-medium text-muted-foreground"
                        for="status"
                        >Status</label
                    >
                    <select
                        id="status"
                        v-model="form.status"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm capitalize"
                    >
                        <option value="">All statuses</option>
                        <option v-for="s in statuses" :key="s" :value="s">
                            {{ s.replace('_', ' ') }}
                        </option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <Button type="submit">Apply</Button>
                    <Button type="button" variant="ghost" @click="clear">
                        <X class="size-4" /> Clear
                    </Button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="t in types"
                    :key="t"
                    type="button"
                    class="rounded-full outline-offset-2 transition-opacity"
                    :class="
                        selectedTypes.has(t)
                            ? 'opacity-100 ring-2 ring-ring'
                            : 'opacity-50 hover:opacity-80'
                    "
                    @click="toggleType(t)"
                >
                    <EventTypeBadge :type="t" />
                </button>
            </div>
        </form>
    </div>
</template>
