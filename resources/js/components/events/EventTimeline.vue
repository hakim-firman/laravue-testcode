<script setup lang="ts">
import { computed } from 'vue';
import EventDateGroup from '@/components/events/EventDateGroup.vue';
import type { DateGroup } from '@/composables/useTimelineGroups';

const props = withDefaults(
    defineProps<{ groups: DateGroup[]; stickyTop?: number }>(),
    { stickyTop: 56 },
);

// Day headers stick just below the (measured) filter bar.
const style = computed(() => ({ '--day-top': `${props.stickyTop}px` }));
</script>

<template>
    <div class="flex flex-col gap-6" :style="style">
        <EventDateGroup
            v-for="group in groups"
            :key="group.dateKey"
            :group="group"
        />
    </div>
</template>
