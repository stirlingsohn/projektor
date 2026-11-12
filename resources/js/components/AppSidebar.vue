<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import ProjectTreeItem from '@/components/projects/ProjectTreeItem.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupAction,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { t } from '@/lib/i18n';
import { childrenByParent, createProject, groupBy, rootProjects } from '@/lib/projects';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Plus } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();

const mainNavItems: NavItem[] = [
    {
        title: t('Overview'),
        href: '/dashboard',
        icon: LayoutGrid,
    },
];

const children = computed(() => childrenByParent(page.props.projects));
const lists = computed(() => groupBy(page.props.lists, (list) => list.project_id));
const activeProjectId = computed(() => Number(page.url.match(/^\/projects\/(\d+)/)?.[1]) || null);
const activeListId = computed(() => Number(page.url.match(/^\/lists\/(\d+)/)?.[1]) || null);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />

            <SidebarGroup v-if="page.props.auth.user.role === 'owner' || page.props.projects.length" class="px-2 py-0">
                <SidebarGroupLabel>{{ $t('Projects') }}</SidebarGroupLabel>
                <SidebarGroupAction v-if="page.props.auth.user.role === 'owner'" :title="$t('New project')" @click="createProject()">
                    <Plus />
                    <span class="sr-only">{{ $t('New project') }}</span>
                </SidebarGroupAction>
                <SidebarMenu>
                    <ProjectTreeItem
                        v-for="project in rootProjects(page.props.projects)"
                        :key="project.id"
                        :project="project"
                        :children="children"
                        :lists="lists"
                        :active-id="activeProjectId"
                        :active-list-id="activeListId"
                    />
                    <p v-if="!page.props.projects.length" class="px-2 py-1 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden">
                        {{ $t('No projects yet.') }}
                    </p>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
