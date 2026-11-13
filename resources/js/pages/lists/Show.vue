<script setup lang="ts">
import ShareDialog from '@/components/sharing/ShareDialog.vue';
import GanttChart from '@/components/tasks/GanttChart.vue';
import KanbanBoard from '@/components/tasks/KanbanBoard.vue';
import ListView from '@/components/tasks/ListView.vue';
import StatusSettingsDialog from '@/components/tasks/StatusSettingsDialog.vue';
import TaskDetailSheet from '@/components/tasks/TaskDetailSheet.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/AppLayout.vue';
import { t } from '@/lib/i18n';
import { editList, expandProjects } from '@/lib/projects';
import { formatDuration } from '@/lib/time';
import type { Attachment, BreadcrumbItem, Comment, ListSummary, Sharing, Status, Task, TimeEntry } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { useStorage } from '@vueuse/core';
import { Clock, Columns3, GanttChartSquare, List, ListChecks, MoreHorizontal, Pencil, Trash2, Users } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    list: ListSummary;
    ancestors: { id: number; name: string }[];
    statuses: Status[];
    tasks: Task[];
    showsTime: boolean;
    timeEntries: TimeEntry[];
    attachments: Attachment[];
    comments: Comment[];
    can: { manage: boolean; editTasks: boolean; comment: boolean };
    sharing: Sharing | null;
}>();

const canEdit = computed(() => props.can.editTasks);
const shareOpen = ref(false);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('Overview'), href: route('dashboard') },
    ...props.ancestors.map((ancestor) => ({ title: ancestor.name, href: route('projects.show', ancestor.id) })),
    { title: props.list.name, href: route('lists.show', props.list.id) },
]);

onMounted(() => expandProjects(props.ancestors.map((ancestor) => ancestor.id)));

const VIEWS = [
    { value: 'list', label: t('List'), icon: List },
    { value: 'kanban', label: t('Kanban'), icon: Columns3 },
    { value: 'gantt', label: t('Gantt'), icon: GanttChartSquare },
] as const;

/** The view last used per list, remembered in this browser. */
const views = useStorage<Record<number, (typeof VIEWS)[number]['value']>>('projektor.list-views', {});
const view = computed({
    get: () => views.value[props.list.id] ?? 'list',
    set: (value) => (views.value = { ...views.value, [props.list.id]: value }),
});

/** `?task=…` opens a task right away, e.g. from the project page. */
const linkedTaskId = Number(new URLSearchParams(window.location.search).get('task')) || null;
const selectedTaskId = ref<number | null>(linkedTaskId);
const selectedTask = computed(() => props.tasks.find((task) => task.id === selectedTaskId.value) ?? null);
const closeTask = () => {
    selectedTaskId.value = null;
    const url = new URL(window.location.href);
    if (url.searchParams.has('task')) {
        url.searchParams.delete('task');
        window.history.replaceState(window.history.state, '', url);
    }
};

const trackedSeconds = computed(() => props.tasks.reduce((sum, task) => sum + (task.tracked_seconds ?? 0), 0));

const statusSettingsOpen = ref(false);
const confirmDelete = ref(false);
const deleteList = () => router.delete(route('lists.destroy', props.list.id));
</script>

<template>
    <Head :title="list.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-0 flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-center gap-4" :class="{ 'mx-auto w-full max-w-4xl': view === 'list' }">
                <h1 class="min-w-0 truncate text-2xl font-semibold tracking-tight">{{ list.name }}</h1>
                <span v-if="showsTime && trackedSeconds" class="flex items-center gap-1 text-sm text-muted-foreground" :title="$t('Tracked time')">
                    <Clock class="size-4" />
                    {{ formatDuration(trackedSeconds) }}
                </span>

                <div class="ml-auto flex items-center gap-2">
                    <div class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800" role="tablist" :aria-label="$t('View')">
                        <button
                            v-for="option in VIEWS"
                            :key="option.value"
                            type="button"
                            role="tab"
                            :aria-selected="view === option.value"
                            class="flex items-center gap-1.5 rounded-md px-3 py-1 text-sm transition-colors"
                            :class="
                                view === option.value
                                    ? 'bg-white shadow-sm dark:bg-neutral-700 dark:text-neutral-100'
                                    : 'text-neutral-500 hover:text-foreground dark:text-neutral-400'
                            "
                            @click="view = option.value"
                        >
                            <component :is="option.icon" class="size-4" />
                            {{ option.label }}
                        </button>
                    </div>

                    <Button v-if="sharing" variant="outline" size="sm" @click="shareOpen = true">
                        <Users class="size-4" />
                        {{ $t('Share') }}
                    </Button>
                    <DropdownMenu v-if="can.manage">
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="icon" :aria-label="$t('List actions')">
                                <MoreHorizontal class="size-5" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @select="editList(list)">
                                <Pencil class="mr-2 size-4" />
                                {{ $t('Rename list') }}
                            </DropdownMenuItem>
                            <DropdownMenuItem @select="statusSettingsOpen = true">
                                <ListChecks class="mr-2 size-4" />
                                {{ $t('Edit statuses') }}
                            </DropdownMenuItem>
                            <DropdownMenuItem class="text-red-600 focus:text-red-600" @select="confirmDelete = true">
                                <Trash2 class="mr-2 size-4" />
                                {{ $t('Delete list') }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <ListView
                v-if="view === 'list'"
                class="mx-auto w-full max-w-4xl"
                :list-id="list.id"
                :statuses="statuses"
                :tasks="tasks"
                :can-edit="canEdit"
                :shows-time="showsTime"
                @open="(id) => (selectedTaskId = id)"
            />
            <GanttChart
                v-else-if="view === 'gantt'"
                :list-id="list.id"
                :statuses="statuses"
                :tasks="tasks"
                :can-edit="canEdit"
                @open="(id) => (selectedTaskId = id)"
            />
            <KanbanBoard
                v-else
                class="min-h-0 flex-1"
                :list-id="list.id"
                :statuses="statuses"
                :tasks="tasks"
                :can-edit="canEdit"
                @open="(id) => (selectedTaskId = id)"
            />
        </div>

        <TaskDetailSheet
            :task="selectedTask"
            :tasks="tasks"
            :statuses="statuses"
            :can-edit="canEdit"
            :time-entries="timeEntries"
            :shows-time="showsTime"
            :attachments="attachments"
            :project-id="list.project_id"
            :comments="comments"
            :can-comment="can.comment"
            @close="closeTask"
        />
        <ShareDialog v-if="sharing" v-model:open="shareOpen" :target="{ type: 'list', id: list.id, name: list.name }" :sharing="sharing" />
        <StatusSettingsDialog v-model:open="statusSettingsOpen" :list-id="list.id" :statuses="statuses" />

        <Dialog v-model:open="confirmDelete">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ $t('Delete list?') }}</DialogTitle>
                    <DialogDescription>{{ $t('":name" and all of its tasks will be permanently deleted.', { name: list.name }) }}</DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button variant="secondary" @click="confirmDelete = false">{{ $t('Cancel') }}</Button>
                    <Button variant="destructive" @click="deleteList">{{ $t('Delete') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
