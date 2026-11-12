<script setup lang="ts">
import KanbanColumn from '@/components/tasks/KanbanColumn.vue';
import { groupBy } from '@/lib/projects';
import { moveTask } from '@/lib/tasks';
import type { Status, Task } from '@/types';
import { ref, watch } from 'vue';

const props = defineProps<{
    listId: number;
    statuses: Status[];
    tasks: Task[];
    canEdit: boolean;
}>();

defineEmits<{
    open: [taskId: number];
}>();

// Local copy per status so that dropped cards stay put while saving.
const columns = ref(new Map<number, Task[]>());
watch(
    () => props.tasks,
    () =>
        (columns.value = groupBy(
            props.tasks.map((task) => ({ ...task })),
            (task) => task.status_id,
        )),
    { immediate: true },
);

const drop = ({ taskId, toStatusId, newIndex }: { taskId: number; toStatusId: number; newIndex: number }) => {
    // Look in the local columns, which may be ahead of the server's data.
    const task = [...columns.value.values()].flat().find((candidate) => candidate.id === taskId);
    if (!task) return;

    const source = (columns.value.get(task.status_id) ?? []).filter((card) => card.id !== taskId);
    columns.value.set(task.status_id, source);
    const target = [...(columns.value.get(toStatusId) ?? [])];
    const moved = { ...task, status_id: toStatusId };
    target.splice(newIndex, 0, moved);
    columns.value.set(toStatusId, target);

    moveTask(moved, { after: target[newIndex - 1], before: target[newIndex + 1] }, toStatusId === task.status_id ? undefined : toStatusId);
};
</script>

<template>
    <!-- Columns stack on phones and scroll sideways with a visible scrollbar on wider screens. -->
    <div class="scrollbar-visible flex flex-col gap-3 pb-4 sm:h-full sm:flex-row sm:items-start sm:overflow-x-auto">
        <KanbanColumn
            v-for="status in statuses"
            :key="status.id"
            :list-id="listId"
            :status="status"
            :tasks="columns.get(status.id) ?? []"
            :can-edit="canEdit"
            @open="(id) => $emit('open', id)"
            @drop="drop"
        />
    </div>
</template>
