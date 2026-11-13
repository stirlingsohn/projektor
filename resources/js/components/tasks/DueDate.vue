<script setup lang="ts">
import { daysFromToday, formatDay } from '@/lib/dates';
import type { Task } from '@/types';
import { CalendarDays } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    task: Task;
    done: boolean;
    /** Without the calendar icon, e.g. in a column headed "Date". */
    plain?: boolean;
}>();

const overdue = computed(() => !props.done && props.task.due_date !== null && daysFromToday(props.task.due_date) < 0);
</script>

<template>
    <span
        v-if="task.due_date"
        class="inline-flex shrink-0 items-center gap-1 text-xs"
        :class="overdue ? 'font-medium text-red-600' : 'text-muted-foreground'"
    >
        <CalendarDays v-if="!plain" class="size-3.5" />
        <template v-if="task.start_date && task.start_date !== task.due_date">{{ formatDay(task.start_date) }} – </template>
        {{ formatDay(task.due_date) }}
    </span>
</template>
