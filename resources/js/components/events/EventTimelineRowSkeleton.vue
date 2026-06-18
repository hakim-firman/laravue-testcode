<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="flex h-[72px] items-center gap-3 px-3">
        <Skeleton class="size-16 shrink-0 rounded-md" />
        <div class="flex flex-1 flex-col gap-2">
            <Skeleton class="h-4 w-1/2" />
            <Skeleton class="h-3 w-1/3" />
        </div>
        <Skeleton class="h-4 w-16" />
    </div>
</template>
