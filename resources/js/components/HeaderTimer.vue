<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { formatClock, useTimer } from '@/lib/time';
import { Link } from '@inertiajs/vue3';
import { Square } from 'lucide-vue-next';

const { timer, elapsed, stop } = useTimer();
</script>

<template>
    <div v-if="timer" class="ml-auto flex min-w-0 items-center gap-1 rounded-full border border-red-500/30 bg-red-500/5 py-0.5 pl-3 pr-0.5 text-sm">
        <span class="relative mr-1 flex size-2 shrink-0">
            <span class="absolute inline-flex size-full animate-ping rounded-full bg-red-500 opacity-60" />
            <span class="relative inline-flex size-2 rounded-full bg-red-500" />
        </span>
        <Link :href="route('lists.show', timer.list_id)" class="hidden max-w-48 truncate hover:underline sm:block">{{ timer.task_title }}</Link>
        <span class="font-medium tabular-nums">{{ formatClock(elapsed) }}</span>
        <Button variant="ghost" size="icon" class="size-7 rounded-full text-red-600 hover:text-red-600" :aria-label="$t('Stop timer')" @click="stop">
            <Square class="size-3.5 fill-current" />
        </Button>
    </div>
</template>
