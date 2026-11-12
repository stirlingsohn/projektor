<script setup lang="ts">
import TaskQuickAdd from '@/components/tasks/TaskQuickAdd.vue';
import TaskRow from '@/components/tasks/TaskRow.vue';
import { t } from '@/lib/i18n';
import { useSortableList } from '@/lib/sortable';
import { moveTask, updateTask } from '@/lib/tasks';
import type { Status, Task } from '@/types';
import { useStorage } from '@vueuse/core';
import { ArrowDown, ArrowUp, ChevronRight, ChevronsUpDown } from 'lucide-vue-next';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';

const props = defineProps<{
    listId: number;
    statuses: Status[];
    tasks: Task[];
    canEdit: boolean;
    showsTime: boolean;
}>();

defineEmits<{
    open: [taskId: number];
}>();

const statusById = computed(() => new Map(props.statuses.map((status) => [status.id, status])));
const statusOf = (task: Task) => statusById.value.get(task.status_id) ?? props.statuses[0];

// Local copies so that checking off and reordering feel instant.
const openTasks = ref<Task[]>([]);
const doneTasks = ref<Task[]>([]);
watch(
    () => [props.tasks, props.statuses],
    () => {
        const tasks = props.tasks.map((task) => ({ ...task }));
        openTasks.value = tasks.filter((task) => !statusOf(task).is_done);
        doneTasks.value = tasks.filter((task) => statusOf(task).is_done).sort((a, b) => (b.completed_at ?? '').localeCompare(a.completed_at ?? ''));
    },
    { immediate: true },
);

const showDone = ref(false);

type SortKey = 'date' | 'status';
type Sort = { key: SortKey; direction: 1 | -1 } | null;

/** The sort chosen per list, remembered in this browser; `null` is the manual order. */
const sorts = useStorage<Record<number, Sort>>('projektor.list-sorts', {});
const sort = computed({
    get: () => sorts.value[props.listId] ?? null,
    set: (value) => (sorts.value = { ...sorts.value, [props.listId]: value }),
});

/** Click once for ascending, twice for descending, a third time for the manual order. */
const toggleSort = (key: SortKey) => {
    const current = sort.value;
    sort.value = current?.key !== key ? { key, direction: 1 } : current.direction === 1 ? { key, direction: -1 } : null;
};

const statusIndex = computed(() => new Map(props.statuses.map((status, index) => [status.id, index])));
const sortValue = (task: Task, key: SortKey): string | number | null =>
    key === 'date' ? (task.start_date ?? task.due_date) : (statusIndex.value.get(task.status_id) ?? 0);

/** Tasks without a value always go last; ties keep the manual order. */
const sorted = (tasks: Task[]) => {
    const current = sort.value;
    if (!current) return tasks;

    return [...tasks].sort((a, b) => {
        const [x, y] = [sortValue(a, current.key), sortValue(b, current.key)];
        if (x === y) return 0;
        if (x === null) return 1;
        if (y === null) return -1;

        return (x < y ? -1 : 1) * current.direction;
    });
};
const visibleOpenTasks = computed(() => sorted(openTasks.value));
const visibleDoneTasks = computed(() => sorted(doneTasks.value));

const columns = computed(() => [
    ...(props.showsTime ? [{ label: t('Time'), class: 'hidden w-20 justify-end sm:flex' }] : []),
    { key: 'date' as const, label: t('Date'), class: 'flex w-24 sm:w-36' },
    { key: 'status' as const, label: t('Status'), class: 'flex w-28 justify-end' },
]);

const setStatus = (task: Task, statusId: number) => {
    const wasDone = statusOf(task).is_done;
    task.status_id = statusId;
    if (statusOf(task).is_done !== wasDone) {
        openTasks.value = wasDone ? [...openTasks.value, task] : openTasks.value.filter((open) => open.id !== task.id);
        doneTasks.value = wasDone ? doneTasks.value.filter((done) => done.id !== task.id) : [task, ...doneTasks.value];
    }
    updateTask(task, { status_id: statusId });
};

const toggle = (task: Task) => {
    const target = props.statuses.find((status) => status.is_done !== statusOf(task).is_done);
    if (target) setStatus(task, target.id);
};

useSortableList(useTemplateRef<HTMLElement>('openList'), openTasks, {
    onEnd: ({ oldIndex, newIndex }: { oldIndex: number; newIndex: number }) =>
        oldIndex !== newIndex &&
        nextTick(() => {
            const tasks = openTasks.value;
            moveTask(tasks[newIndex], { after: tasks[newIndex - 1], before: tasks[newIndex + 1] });
        }),
});
</script>

<template>
    <div class="space-y-6">
        <div>
            <div
                v-if="tasks.length"
                class="flex items-center gap-3 border-b border-sidebar-border/70 pb-2 pl-6 pr-2 text-xs font-medium text-muted-foreground"
            >
                <span class="flex-1 pl-[30px]">{{ $t('Task') }}</span>
                <span v-for="column in columns" :key="column.label" class="shrink-0" :class="column.class">
                    <button
                        v-if="column.key"
                        type="button"
                        class="-mx-1 inline-flex items-center gap-1 rounded px-1 hover:text-foreground"
                        :class="{ 'text-foreground': sort?.key === column.key }"
                        :aria-label="$t('Sort by :column', { column: column.label })"
                        @click="toggleSort(column.key)"
                    >
                        {{ column.label }}
                        <ArrowUp v-if="sort?.key === column.key && sort.direction === 1" class="size-3.5" />
                        <ArrowDown v-else-if="sort?.key === column.key" class="size-3.5" />
                        <ChevronsUpDown v-else class="size-3.5 opacity-50" />
                    </button>
                    <template v-else>{{ column.label }}</template>
                </span>
            </div>
            <ul ref="openList">
                <TaskRow
                    v-for="task in visibleOpenTasks"
                    :key="task.id"
                    :task="task"
                    :status="statusOf(task)"
                    :statuses="statuses"
                    :can-edit="canEdit"
                    :shows-time="showsTime"
                    :draggable="!sort"
                    @toggle="toggle(task)"
                    @open="$emit('open', task.id)"
                    @change-status="(id) => setStatus(task, id)"
                />
            </ul>

            <TaskQuickAdd v-if="canEdit" :list-id="listId" :placeholder="$t('Add task, press Enter to save')" class="py-2.5 pl-6 pr-2" />
            <p v-else-if="!openTasks.length" class="py-4 text-sm text-muted-foreground">{{ $t('No open tasks.') }}</p>
        </div>

        <div v-if="doneTasks.length">
            <button
                type="button"
                class="flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground"
                @click="showDone = !showDone"
            >
                <ChevronRight class="size-4 transition-transform" :class="{ 'rotate-90': showDone }" />
                {{ $t('Completed (:count)', { count: doneTasks.length }) }}
            </button>
            <ul v-if="showDone" class="mt-2">
                <TaskRow
                    v-for="task in visibleDoneTasks"
                    :key="task.id"
                    :task="task"
                    :status="statusOf(task)"
                    :statuses="statuses"
                    :can-edit="canEdit"
                    :shows-time="showsTime"
                    @toggle="toggle(task)"
                    @open="$emit('open', task.id)"
                    @change-status="(id) => setStatus(task, id)"
                />
            </ul>
        </div>
    </div>
</template>
