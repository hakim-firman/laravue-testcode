<script setup lang="ts">
import { computed } from 'vue';
import { useEventFormat } from '@/composables/useEventFormat';

const props = defineProps<{ type: string }>();

const { typeBadgeClass } = useEventFormat();
const classes = computed(() => typeBadgeClass(props.type));
</script>

<template>
    <span
        :class="classes"
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
    >
        {{ type }}
    </span>
</template>
