<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import AppToast from '@/components/AppToast.vue';
import DeleteProjectDialog from '@/components/projects/DeleteProjectDialog.vue';
import ListDialog from '@/components/projects/ListDialog.vue';
import ProjectDialog from '@/components/projects/ProjectDialog.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <ProjectDialog />
        <DeleteProjectDialog />
        <ListDialog />
        <AppToast />
    </AppShell>
</template>
