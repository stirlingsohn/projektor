<script setup lang="ts">
import DueDate from '@/components/tasks/DueDate.vue';
import StatusBadge from '@/components/tasks/StatusBadge.vue';
import TrackedTime from '@/components/tasks/TrackedTime.vue';
import type { Status, Task } from '@/types';
import { Check, GripVertical } from 'lucide-vue-next';
defineProps<{
    task: Task;
    status: Status;
    statuses: Status[];
    canEdit: boolean;
    draggable?: boolean;
    showsTime?: boolean;
}>();

defineEmits<{
    toggle: [];
    open: [];
    changeStatus: [statusId: number];
}>();
</script>

<template>
    <li
        class="group relative flex cursor-pointer items-center gap-3 border-b border-sidebar-border/50 py-2.5 pl-6 pr-2 hover:bg-muted/40"
        @click="$emit('open')"
    >
        <span
            v-if="draggable && canEdit"
            class="drag-handle absolute left-0 flex h-full w-6 cursor-grab items-center justify-center text-muted-foreground/60 opacity-0 group-hover:opacity-100"
            @click.stop
        >
            <GripVertical class="size-4" />
        </span>

        <button
            type="button"
            class="group/check flex size-[18px] shrink-0 items-center justify-center rounded-full border-2 transition disabled:cursor-default"
            :style="{ borderColor: status.color, backgroundColor: status.is_done ? status.color : undefined }"
            :aria-label="status.is_done ? $t('Mark as open') : $t('Mark as done')"
            :aria-pressed="status.is_done"
            :disabled="!canEdit"
            @click.stop="$emit('toggle')"
        >
            <Check
                class="size-3"
                :class="status.is_done ? 'text-white' : 'opacity-0 group-hover/check:opacity-60'"
                :style="status.is_done ? undefined : { color: status.color }"
            />
        </button>

        <span class="min-w-0 flex-1 truncate text-sm" :class="{ 'text-muted-foreground line-through': status.is_done }">
            {{ task.title }}
        </span>

        <span v-if="showsTime" class="hidden w-20 shrink-0 justify-end sm:flex"><TrackedTime :task="task" /></span>
        <span class="flex w-24 shrink-0 sm:w-36"><DueDate :task="task" :done="status.is_done" /></span>
        <span class="flex w-28 shrink-0 justify-end">
            <StatusBadge :status="status" :statuses="statuses" :disabled="!canEdit" @change="(id) => $emit('changeStatus', id)" />
        </span>
    </li>
</template>
