<script setup lang="ts">
import AssigneeStack from '@/components/tasks/AssigneeStack.vue';
import DueDate from '@/components/tasks/DueDate.vue';
import StatusBadge from '@/components/tasks/StatusBadge.vue';
import TrackedTime from '@/components/tasks/TrackedTime.vue';
import { LIST_COLUMNS } from '@/lib/listColumns';
import type { Status, Task } from '@/types';
import { Check, GripVertical, MessageSquare } from 'lucide-vue-next';
defineProps<{
    task: Task;
    status: Status;
    statuses: Status[];
    canEdit: boolean;
    draggable?: boolean;
    /** Which optional columns the list shows. */
    columns: { comments: boolean; assignees: boolean; time: boolean };
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
        <span v-if="columns.comments" class="shrink-0" :class="LIST_COLUMNS.comments">
            <span v-if="task.comments_count" class="inline-flex items-center gap-1 text-xs text-muted-foreground" :title="$t('Comments')">
                <MessageSquare class="size-3.5" />
                {{ task.comments_count }}
            </span>
        </span>
        <span v-if="columns.assignees" class="shrink-0" :class="LIST_COLUMNS.assignees"><AssigneeStack :ids="task.assignee_ids" /></span>
        <span v-if="columns.time" class="shrink-0" :class="LIST_COLUMNS.time"><TrackedTime :task="task" compact /></span>
        <span class="shrink-0" :class="LIST_COLUMNS.date"><DueDate :task="task" :done="status.is_done" plain /></span>
        <span class="shrink-0" :class="LIST_COLUMNS.status">
            <StatusBadge :status="status" :statuses="statuses" :disabled="!canEdit" @change="(id) => $emit('changeStatus', id)" />
        </span>
    </li>
</template>
