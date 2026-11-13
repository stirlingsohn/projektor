<script setup lang="ts">
import AttachmentList from '@/components/files/AttachmentList.vue';
import ProjectGrid from '@/components/projects/ProjectGrid.vue';
import ShareDialog from '@/components/sharing/ShareDialog.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { daysFromToday, formatDay } from '@/lib/dates';
import { t } from '@/lib/i18n';
import { createDocument, createList, createProject, deleteProject, editProject } from '@/lib/projects';
import { formatDuration } from '@/lib/time';
import {
    type Attachment,
    type BreadcrumbItem,
    type ListLabel,
    type ProjectDashboard,
    type ProjectSummary,
    type SharedData,
    type Sharing,
} from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, Clock, FilePlus, FileText, Folder, FolderPlus, ListPlus, ListTodo, Pencil, Trash2, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<
    ProjectDashboard & {
        project: ProjectSummary;
        ancestors: { id: number; name: string }[];
        children: { id: number; name: string; color: string }[];
        attachments: Attachment[];
        can: { manage: boolean; contribute: boolean; viewContents: boolean };
        sharing: Sharing | null;
    }
>();

const page = usePage<SharedData>();
const isOwner = computed(() => props.can.manage);
const shareOpen = ref(false);
const lists = computed(() => page.props.lists.filter((list) => list.project_id === props.project.id));
const documents = computed(() => page.props.documents.filter((document) => document.project_id === props.project.id));

const statsOf = (listId: number) => props.listStats[listId] ?? { total: 0, done: 0, overdue: 0, next_due: null };
const percent = (done: number, total: number) => (total ? Math.round((done / total) * 100) : 0);
const isOverdue = (date: string) => daysFromToday(date) < 0;
const listName = (list: ListLabel) => (list.project ? `${list.project} · ${list.name}` : list.name);
const longestList = computed(() => Math.max(1, ...(props.time?.lists.map((list) => list.seconds) ?? [])));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('Overview'), href: route('dashboard') },
    ...props.ancestors.map((ancestor) => ({ title: ancestor.name, href: route('projects.show', ancestor.id) })),
    { title: props.project.name, href: route('projects.show', props.project.id) },
]);

const card = 'rounded-xl border border-sidebar-border/70 dark:border-sidebar-border';
</script>

<template>
    <Head :title="project.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
            <div class="flex flex-wrap items-center gap-4">
                <h1 class="flex items-center gap-3 text-2xl font-semibold tracking-tight">
                    <Folder class="size-6 shrink-0" :style="{ color: project.color }" />
                    {{ project.name }}
                </h1>
                <div v-if="isOwner" class="ml-auto flex gap-2">
                    <Button variant="outline" size="sm" @click="shareOpen = true">
                        <Users class="size-4" />
                        {{ $t('Share') }}
                    </Button>
                    <Button variant="outline" size="sm" @click="editProject(project)">
                        <Pencil class="size-4" />
                        {{ $t('Edit') }}
                    </Button>
                    <Button variant="outline" size="sm" class="text-red-600 hover:text-red-600" @click="deleteProject(project)">
                        <Trash2 class="size-4" />
                        {{ $t('Delete') }}
                    </Button>
                </div>
            </div>

            <dl v-if="summary.total || time?.total" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div :class="[card, 'p-4']">
                    <dt class="text-sm text-muted-foreground">{{ $t('Open tasks') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ summary.open }}</dd>
                </div>
                <div :class="[card, 'p-4']">
                    <dt class="text-sm text-muted-foreground">{{ $t('Overdue') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums" :class="{ 'text-red-600': summary.overdue }">{{ summary.overdue }}</dd>
                </div>
                <div :class="[card, 'p-4']">
                    <dt class="text-sm text-muted-foreground">{{ $t('Done') }}</dt>
                    <dd class="mt-1 flex items-baseline gap-2">
                        <span class="text-2xl font-semibold tabular-nums">{{ percent(summary.done, summary.total) }}%</span>
                        <span class="text-sm text-muted-foreground">{{ $t(':done of :total', { done: summary.done, total: summary.total }) }}</span>
                    </dd>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full transition-all"
                            :style="{ width: `${percent(summary.done, summary.total)}%`, backgroundColor: project.color }"
                        />
                    </div>
                </div>
                <div v-if="time" :class="[card, 'p-4']">
                    <dt class="text-sm text-muted-foreground">{{ $t('Tracked time') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ formatDuration(time.total) }}</dd>
                    <p class="mt-1 text-xs text-muted-foreground">{{ $t(':time this week', { time: formatDuration(time.week) }) }}</p>
                </div>
            </dl>

            <div class="grid gap-8 lg:grid-cols-3">
                <div class="space-y-8 lg:col-span-2">
                    <section class="space-y-3">
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="font-medium">{{ $t('Lists') }}</h2>
                            <Button v-if="isOwner" variant="ghost" size="sm" @click="createList(project.id)">
                                <ListPlus class="size-4" />
                                {{ $t('New list') }}
                            </Button>
                        </div>
                        <div v-if="lists.length" class="grid gap-3 sm:grid-cols-2">
                            <Link
                                v-for="list in lists"
                                :key="list.id"
                                :href="route('lists.show', list.id)"
                                :class="[card, 'block p-4 transition hover:border-foreground/20 hover:shadow-sm']"
                            >
                                <div class="flex items-center gap-3">
                                    <ListTodo class="size-5 shrink-0 text-muted-foreground" />
                                    <span class="truncate font-medium">{{ list.name }}</span>
                                    <span class="ml-auto shrink-0 text-xs tabular-nums text-muted-foreground">
                                        {{ $t(':done of :total', { done: statsOf(list.id).done, total: statsOf(list.id).total }) }}
                                    </span>
                                </div>
                                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full"
                                        :style="{
                                            width: `${percent(statsOf(list.id).done, statsOf(list.id).total)}%`,
                                            backgroundColor: project.color,
                                        }"
                                    />
                                </div>
                                <p class="mt-2 flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                                    <span v-if="!statsOf(list.id).total">{{ $t('No tasks yet.') }}</span>
                                    <span v-else-if="statsOf(list.id).done === statsOf(list.id).total">{{ $t('All done') }}</span>
                                    <span v-if="statsOf(list.id).overdue" class="font-medium text-red-600">
                                        {{ $t(':count overdue', { count: statsOf(list.id).overdue }) }}
                                    </span>
                                    <span v-if="statsOf(list.id).next_due" class="inline-flex items-center gap-1">
                                        <CalendarDays class="size-3.5" />
                                        {{ $t('Next: :date', { date: formatDay(statsOf(list.id).next_due!) }) }}
                                    </span>
                                </p>
                            </Link>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">{{ $t('No lists yet.') }}</p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-medium">{{ $t('Up next') }}</h2>
                        <ul v-if="upcoming.length" :class="[card, 'divide-y divide-sidebar-border/50 text-sm']">
                            <li v-for="task in upcoming" :key="task.id">
                                <Link
                                    :href="route('lists.show', { list: task.list.id, task: task.id })"
                                    class="flex items-center gap-3 px-3 py-2.5 transition hover:bg-muted/50"
                                >
                                    <span
                                        class="size-2 shrink-0 rounded-full"
                                        :style="{ backgroundColor: task.status.color }"
                                        :title="task.status.name"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-medium">{{ task.title }}</p>
                                        <p class="truncate text-xs text-muted-foreground">{{ listName(task.list) }}</p>
                                    </div>
                                    <span
                                        class="inline-flex shrink-0 items-center gap-1 text-xs"
                                        :class="isOverdue(task.due_date) ? 'font-medium text-red-600' : 'text-muted-foreground'"
                                    >
                                        <CalendarDays class="size-3.5" />
                                        {{ formatDay(task.due_date) }}
                                    </span>
                                </Link>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">{{ $t('No open tasks with a due date.') }}</p>
                    </section>

                    <section v-if="isOwner || children.length" class="space-y-3">
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="font-medium">{{ $t('Subprojects') }}</h2>
                            <Button v-if="isOwner" variant="ghost" size="sm" @click="createProject(project.id)">
                                <FolderPlus class="size-4" />
                                {{ $t('New subproject') }}
                            </Button>
                        </div>
                        <ProjectGrid v-if="children.length" :projects="children" />
                        <p v-else class="text-sm text-muted-foreground">{{ $t('No subprojects.') }}</p>
                    </section>
                </div>

                <div class="space-y-8">
                    <section v-if="time" class="space-y-3">
                        <h2 class="flex items-center gap-2 font-medium">
                            <Clock class="size-4 text-muted-foreground" />
                            {{ $t('Time by list') }}
                        </h2>
                        <ul v-if="time.lists.length" :class="[card, 'space-y-3 p-4 text-sm']">
                            <li v-for="list in time.lists" :key="list.id">
                                <div class="flex items-baseline justify-between gap-3">
                                    <Link :href="route('lists.show', list.id)" class="truncate hover:underline">{{ listName(list) }}</Link>
                                    <span class="shrink-0 tabular-nums text-muted-foreground">{{ formatDuration(list.seconds) }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full"
                                        :style="{ width: `${(list.seconds / longestList) * 100}%`, backgroundColor: project.color }"
                                    />
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">{{ $t('No time tracked yet.') }}</p>
                    </section>

                    <section v-if="can.viewContents" class="space-y-3">
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="font-medium">{{ $t('Documents') }}</h2>
                            <Button v-if="can.contribute" variant="ghost" size="sm" @click="createDocument(project.id)">
                                <FilePlus class="size-4" />
                                {{ $t('New document') }}
                            </Button>
                        </div>
                        <ul v-if="documents.length" :class="[card, 'divide-y divide-sidebar-border/50 text-sm']">
                            <li v-for="document in documents" :key="document.id">
                                <Link
                                    :href="route('documents.show', document.id)"
                                    class="flex items-center gap-3 px-3 py-2.5 transition hover:bg-muted/50"
                                >
                                    <FileText class="size-4 shrink-0 text-muted-foreground" />
                                    <span class="truncate font-medium">{{ document.title }}</span>
                                </Link>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">{{ $t('No documents yet.') }}</p>
                    </section>
                </div>
            </div>

            <section v-if="can.viewContents" class="space-y-3">
                <h2 class="font-medium">{{ $t('Files') }}</h2>
                <AttachmentList :attachments="attachments" :project-id="project.id" :can-edit="can.contribute" show-task />
            </section>
        </div>
        <ShareDialog v-if="sharing" v-model:open="shareOpen" :target="{ type: 'project', id: project.id, name: project.name }" :sharing="sharing" />
    </AppLayout>
</template>
