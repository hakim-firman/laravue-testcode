<script setup lang="ts">
import DateGroupHeader from '@/components/events/DateGroupHeader.vue';
import EventTimelineRow from '@/components/events/EventTimelineRow.vue';
import type { DateGroup } from '@/composables/useTimelineGroups';

defineProps<{ group: DateGroup }>();
</script>

<template>
    <section class="flex flex-col">
        <DateGroupHeader
            :label="group.label"
            :relative-label="group.relativeLabel"
            :is-past="group.isPast"
        />

        <!-- Left accent line runs the full height of the day's rows -->
        <div
            class="ml-2 flex flex-col gap-1 border-l-2 border-border/70 pl-3 transition-opacity"
            :class="group.isPast ? 'opacity-60 grayscale-[0.3]' : ''"
        >
            <EventTimelineRow
                v-for="event in group.events"
                :key="event.id"
                :event="event"
            />
        </div>
    </section>
</template>
