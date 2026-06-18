<script setup lang="ts">
import { ref } from 'vue';

defineProps<{ src: string | undefined; alt: string }>();

const loaded = ref(false);
const failed = ref(false);
</script>

<template>
    <div class="relative aspect-video w-full overflow-hidden bg-muted">
        <!-- Skeleton shimmer while the image streams in -->
        <div
            v-if="!loaded && !failed"
            class="absolute inset-0 animate-pulse bg-muted"
        />

        <!-- Fallback gradient when there's no image or it fails to load -->
        <div
            v-if="failed || !src"
            class="absolute inset-0 bg-gradient-to-br from-muted-foreground/20 to-muted-foreground/5"
        />

        <img
            v-if="src && !failed"
            :src="src"
            :alt="alt"
            loading="lazy"
            class="h-full w-full object-cover transition-all duration-500 group-hover:scale-105"
            :class="loaded ? 'opacity-100' : 'opacity-0'"
            @load="loaded = true"
            @error="failed = true"
        />
    </div>
</template>
