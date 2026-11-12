<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarMenuAction, SidebarMenuButton, SidebarMenuItem, SidebarMenuSub } from '@/components/ui/sidebar';
import { createDocument, createList, createProject, deleteProject, editProject, expandedProjectIds } from '@/lib/projects';
import type { ListSummary, ProjectSummary, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChevronRight,
    FilePlus,
    FileText,
    Folder,
    FolderOpen,
    FolderPlus,
    ListPlus,
    ListTodo,
    MoreHorizontal,
    Pencil,
    Trash2,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    project: ProjectSummary;
    children: Map<number | null, ProjectSummary[]>;
    lists: Map<number, ListSummary[]>;
    activeId: number | null;
    activeListId: number | null;
}>();

const page = usePage<SharedData>();
const isOwner = computed(() => page.props.auth.user.role === 'owner');
const subprojects = computed(() => props.children.get(props.project.id) ?? []);
const projectLists = computed(() => props.lists.get(props.project.id) ?? []);
const projectDocuments = computed(() => page.props.documents.filter((document) => document.project_id === props.project.id));
const activeDocumentId = computed(() => (page.component === 'documents/Show' ? (page.props.document as { id: number }).id : null));
const hasChildren = computed(() => subprojects.value.length + projectLists.value.length + projectDocuments.value.length > 0);
const open = computed(() => expandedProjectIds.value.includes(props.project.id));

const toggle = () => {
    expandedProjectIds.value = open.value
        ? expandedProjectIds.value.filter((id) => id !== props.project.id)
        : [...expandedProjectIds.value, props.project.id];
};
</script>

<template>
    <SidebarMenuItem>
        <SidebarMenuButton as-child :is-active="project.id === activeId" :tooltip="project.name">
            <Link :href="route('projects.show', project.id)">
                <span
                    v-if="hasChildren"
                    role="button"
                    class="-ml-1 flex size-4 shrink-0 items-center justify-center rounded text-muted-foreground hover:text-foreground"
                    @click.prevent.stop="toggle"
                >
                    <ChevronRight class="size-3.5 transition-transform" :class="{ 'rotate-90': open }" />
                </span>
                <span v-else class="-ml-1 size-4 shrink-0" />
                <component :is="open && hasChildren ? FolderOpen : Folder" class="size-4 shrink-0" :style="{ color: project.color }" />
                <span class="truncate">{{ project.name }}</span>
            </Link>
        </SidebarMenuButton>

        <DropdownMenu v-if="isOwner">
            <DropdownMenuTrigger as-child>
                <SidebarMenuAction show-on-hover :aria-label="$t('Project actions')">
                    <MoreHorizontal />
                </SidebarMenuAction>
            </DropdownMenuTrigger>
            <DropdownMenuContent side="right" align="start">
                <DropdownMenuItem @select="createList(project.id)">
                    <ListPlus class="mr-2 size-4" />
                    {{ $t('New list') }}
                </DropdownMenuItem>
                <DropdownMenuItem @select="createDocument(project.id)">
                    <FilePlus class="mr-2 size-4" />
                    {{ $t('New document') }}
                </DropdownMenuItem>
                <DropdownMenuItem @select="createProject(project.id)">
                    <FolderPlus class="mr-2 size-4" />
                    {{ $t('New subproject') }}
                </DropdownMenuItem>
                <DropdownMenuItem @select="editProject(project)">
                    <Pencil class="mr-2 size-4" />
                    {{ $t('Edit') }}
                </DropdownMenuItem>
                <DropdownMenuItem class="text-red-600 focus:text-red-600" @select="deleteProject(project)">
                    <Trash2 class="mr-2 size-4" />
                    {{ $t('Delete') }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <SidebarMenuSub v-if="open && hasChildren" class="mr-0 pr-0">
            <SidebarMenuItem v-for="list in projectLists" :key="`list-${list.id}`">
                <SidebarMenuButton as-child :is-active="list.id === activeListId">
                    <Link :href="route('lists.show', list.id)">
                        <span class="-ml-1 size-4 shrink-0" />
                        <ListTodo class="text-muted-foreground" />
                        <span class="truncate">{{ list.name }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem v-for="document in projectDocuments" :key="`document-${document.id}`">
                <SidebarMenuButton as-child :is-active="document.id === activeDocumentId">
                    <Link :href="route('documents.show', document.id)">
                        <span class="-ml-1 size-4 shrink-0" />
                        <FileText class="text-muted-foreground" />
                        <span class="truncate">{{ document.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
            <ProjectTreeItem
                v-for="child in subprojects"
                :key="child.id"
                :project="child"
                :children="children"
                :lists="lists"
                :active-id="activeId"
                :active-list-id="activeListId"
            />
        </SidebarMenuSub>
    </SidebarMenuItem>
</template>
