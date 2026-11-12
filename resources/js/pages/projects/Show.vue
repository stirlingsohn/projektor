<script setup lang="ts">
import AttachmentList from '@/components/files/AttachmentList.vue';
import ProjectGrid from '@/components/projects/ProjectGrid.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { t } from '@/lib/i18n';
import { createDocument, createList, createProject, deleteProject, editProject } from '@/lib/projects';
import { formatDuration } from '@/lib/time';
import { type Attachment, type BreadcrumbItem, type ProjectSummary, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Clock, FilePlus, FileText, Folder, FolderPlus, ListPlus, ListTodo, Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    project: ProjectSummary;
    ancestors: { id: number; name: string }[];
    children: { id: number; name: string; color: string }[];
    /** Seconds tracked in this project and its subprojects; `null` when hidden. */
    trackedSeconds: number | null;
    attachments: Attachment[];
}>();

const page = usePage<SharedData>();
const isOwner = computed(() => page.props.auth.user.role === 'owner');
const lists = computed(() => page.props.lists.filter((list) => list.project_id === props.project.id));
const documents = computed(() => page.props.documents.filter((document) => document.project_id === props.project.id));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('Overview'), href: route('dashboard') },
    ...props.ancestors.map((ancestor) => ({ title: ancestor.name, href: route('projects.show', ancestor.id) })),
    { title: props.project.name, href: route('projects.show', props.project.id) },
]);
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
                <span v-if="trackedSeconds" class="flex items-center gap-1 text-sm text-muted-foreground" :title="$t('Tracked time')">
                    <Clock class="size-4" />
                    {{ formatDuration(trackedSeconds) }}
                </span>
                <div v-if="isOwner" class="ml-auto flex gap-2">
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

            <section class="space-y-3">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="font-medium">{{ $t('Lists') }}</h2>
                    <Button v-if="isOwner" variant="ghost" size="sm" @click="createList(project.id)">
                        <ListPlus class="size-4" />
                        {{ $t('New list') }}
                    </Button>
                </div>
                <div v-if="lists.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="list in lists"
                        :key="list.id"
                        :href="route('lists.show', list.id)"
                        class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 p-4 transition hover:border-foreground/20 hover:shadow-sm dark:border-sidebar-border"
                    >
                        <ListTodo class="size-5 shrink-0 text-muted-foreground" />
                        <span class="truncate font-medium">{{ list.name }}</span>
                    </Link>
                </div>
                <p v-else class="text-sm text-muted-foreground">{{ $t('No lists yet.') }}</p>
            </section>

            <section class="space-y-3">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="font-medium">{{ $t('Documents') }}</h2>
                    <Button v-if="isOwner" variant="ghost" size="sm" @click="createDocument(project.id)">
                        <FilePlus class="size-4" />
                        {{ $t('New document') }}
                    </Button>
                </div>
                <div v-if="documents.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="document in documents"
                        :key="document.id"
                        :href="route('documents.show', document.id)"
                        class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 p-4 transition hover:border-foreground/20 hover:shadow-sm dark:border-sidebar-border"
                    >
                        <FileText class="size-5 shrink-0 text-muted-foreground" />
                        <span class="truncate font-medium">{{ document.title }}</span>
                    </Link>
                </div>
                <p v-else class="text-sm text-muted-foreground">{{ $t('No documents yet.') }}</p>
            </section>

            <section class="space-y-3">
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

            <section class="space-y-3">
                <h2 class="font-medium">{{ $t('Files') }}</h2>
                <AttachmentList :attachments="attachments" :project-id="project.id" :can-edit="isOwner" show-task />
            </section>
        </div>
    </AppLayout>
</template>
