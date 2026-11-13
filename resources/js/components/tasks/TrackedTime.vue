<script setup lang="ts">
import { formatClock, formatDuration, formatHours, useTimer } from '@/lib/time';
import type { Task } from '@/types';
import { Clock } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    task: Task;
    /** "1:50" instead of "1:50 Std.", for a column headed "Time". */
    compact?: boolean;
}>();

const { elapsed, isRunningOn } = useTimer();
const running = computed(() => isRunningOn(props.task));
</script>

<template>
    <span v-if="running" class="inline-flex shrink-0 items-center gap-1 text-xs font-medium tabular-nums text-red-600">
        <span class="size-1.5 animate-pulse rounded-full bg-red-500" />
        {{ formatClock(elapsed) }}
    </span>
    <span v-else-if="task.tracked_seconds" class="inline-flex shrink-0 items-center gap-1 text-xs tabular-nums text-muted-foreground">
        <Clock class="size-3.5" />
        {{ compact ? formatHours(task.tracked_seconds) : formatDuration(task.tracked_seconds) }}
    </span>
</template>
