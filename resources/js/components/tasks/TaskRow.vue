<script setup lang="ts">
import AssigneeStack from '@/components/tasks/AssigneeStack.vue';
import DueDate from '@/components/tasks/DueDate.vue';
import StatusCircle from '@/components/tasks/StatusCircle.vue';
import TrackedTime from '@/components/tasks/TrackedTime.vue';
import { LIST_COLUMNS } from '@/lib/listColumns';
import type { Status, Task } from '@/types';
import { GripVertical, MessageSquare } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    task: Task;
    status: Status;
    statuses: Status[];
    canEdit: boolean;
    draggable?: boolean;
    /** Which optional columns the list shows. */
    columns: { comments: boolean; assignees: boolean; time: boolean };
}>();

defineEmits<{
    open: [];
    changeStatus: [statusId: number];
}>();

/** Only statuses between the first one and done get a label; open tasks stay quiet. */
const inBetween = computed(() => !props.status.is_done && props.status.id !== props.statuses[0]?.id);
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

        <StatusCircle :status="status" :statuses="statuses" :disabled="!canEdit" @change="(id) => $emit('changeStatus', id)" />

        <span class="flex min-w-0 flex-1 items-baseline gap-2">
            <span class="truncate text-sm" :class="{ 'text-muted-foreground line-through': status.is_done }">{{ task.title }}</span>
            <span v-if="inBetween" class="shrink-0 text-xs font-medium" :style="{ color: status.color }">{{ status.name }}</span>
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
    </li>
</template>
