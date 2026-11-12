<script setup lang="ts">
import ProjectGrid from '@/components/projects/ProjectGrid.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { t } from '@/lib/i18n';
import { createProject, rootProjects } from '@/lib/projects';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { FolderPlus } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();

const breadcrumbs: BreadcrumbItem[] = [{ title: t('Overview'), href: '/dashboard' }];

const roots = computed(() => rootProjects(page.props.projects));
</script>

<template>
    <Head :title="$t('Overview')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold tracking-tight">{{ $t('Projects') }}</h1>
                <Button v-if="page.props.auth.user.role === 'owner'" @click="createProject()">
                    <FolderPlus class="size-4" />
                    {{ $t('New project') }}
                </Button>
            </div>

            <ProjectGrid v-if="roots.length" :projects="roots" />

            <div v-else class="flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed p-12 text-center">
                <p class="font-medium">{{ $t('No projects yet.') }}</p>
                <p v-if="page.props.auth.user.role === 'owner'" class="text-sm text-muted-foreground">
                    {{ $t('Create your first project to get started.') }}
                </p>
            </div>
        </div>
    </AppLayout>
</template>
