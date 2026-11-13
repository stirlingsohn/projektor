<script setup lang="ts">
import AssigneeStack from '@/components/tasks/AssigneeStack.vue';
import DueDate from '@/components/tasks/DueDate.vue';
import TaskQuickAdd from '@/components/tasks/TaskQuickAdd.vue';
import TrackedTime from '@/components/tasks/TrackedTime.vue';
import { useSortableList } from '@/lib/sortable';
import type { Status, Task } from '@/types';
import { MessageSquare } from 'lucide-vue-next';
import { ref, useTemplateRef } from 'vue';

const props = defineProps<{
    listId: number;
    status: Status;
    tasks: Task[];
    canEdit: boolean;
}>();

const emit = defineEmits<{
    open: [taskId: number];
    drop: [move: { taskId: number; toStatusId: number; newIndex: number }];
}>();

const cards = useTemplateRef<HTMLElement>('cards');

if (props.canEdit) {
    useSortableList(cards, ref([]), {
        group: `kanban-${props.listId}`,
        handle: undefined,
        // The board owns the data: undo SortableJS's DOM move and let Vue render the result.
        onUpdate: () => {},
        onEnd: ({
            from,
            to,
            item,
            oldIndex,
            newIndex,
        }: {
            from: HTMLElement;
            to: HTMLElement;
            item: HTMLElement;
            oldIndex: number;
            newIndex: number;
        }) => {
            if (from === to && oldIndex === newIndex) return;
            to.removeChild(item);
            from.insertBefore(item, from.children[oldIndex] ?? null);
            emit('drop', { taskId: Number(item.dataset.taskId), toStatusId: Number(to.dataset.statusId), newIndex });
        },
    });
}
</script>

<template>
    <section class="flex w-full shrink-0 flex-col rounded-xl bg-muted/50 dark:bg-muted/30 sm:max-h-full sm:w-72">
        <header class="flex items-center gap-2 px-3 pb-2 pt-3 text-sm font-medium">
            <span class="size-2.5 rounded-full" :style="{ backgroundColor: status.color }" />
            <span class="truncate">{{ status.name }}</span>
            <span class="ml-auto text-xs font-normal text-muted-foreground">{{ tasks.length }}</span>
        </header>

        <ul ref="cards" :data-status-id="status.id" class="flex min-h-12 flex-1 flex-col gap-2 overflow-y-auto px-2 pb-2">
            <li
                v-for="task in tasks"
                :key="task.id"
                :data-task-id="task.id"
                class="cursor-pointer space-y-2 rounded-lg border border-sidebar-border/70 bg-background p-3 text-sm shadow-sm transition hover:border-foreground/20 dark:border-sidebar-border"
                :class="{ 'cursor-grab active:cursor-grabbing': canEdit }"
                @click="emit('open', task.id)"
            >
                <p class="break-words" :class="{ 'text-muted-foreground line-through': status.is_done }">{{ task.title }}</p>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 empty:hidden">
                    <DueDate :task="task" :done="status.is_done" />
                    <TrackedTime :task="task" />
                    <span v-if="task.comments_count" class="inline-flex items-center gap-1 text-xs text-muted-foreground" :title="$t('Comments')">
                        <MessageSquare class="size-3.5" />
                        {{ task.comments_count }}
                    </span>
                    <AssigneeStack :ids="task.assignee_ids" class="ml-auto" />
                </div>
            </li>
        </ul>

        <TaskQuickAdd v-if="canEdit" :list-id="listId" :status-id="status.id" :placeholder="$t('Add task')" class="px-3 pb-3 pt-1" />
    </section>
</template>
