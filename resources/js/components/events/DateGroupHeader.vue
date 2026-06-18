<script setup lang="ts">
defineProps<{
    label: string;
    relativeLabel: string;
    isPast: boolean;
}>();
</script>

<template>
    <div
        class="date-group-header sticky z-10 -mx-1 flex items-center gap-3 bg-background/80 px-1 py-2 backdrop-blur-sm"
    >
        <span
            v-if="relativeLabel"
            class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary"
        >
            {{ relativeLabel }}
        </span>
        <span
            class="text-sm font-semibold tracking-tight"
            :class="isPast ? 'text-muted-foreground' : 'text-foreground'"
        >
            {{ label }}
        </span>
        <span class="h-px flex-1 bg-border" />
    </div>
</template>

<style scoped>
.date-group-header {
    /* Offset set by the timeline container; falls back to filter-bar height. */
    top: var(--day-top, 56px);
    animation: header-fade 0.3s ease both;
}

@media (prefers-reduced-motion: reduce) {
    .date-group-header {
        animation: none;
    }
}

@keyframes header-fade {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>
