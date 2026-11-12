<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Folder } from 'lucide-vue-next';

defineProps<{
    projects: { id: number; name: string; color: string }[];
}>();
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <Link
            v-for="project in projects"
            :key="project.id"
            :href="route('projects.show', project.id)"
            class="group flex items-center gap-3 rounded-xl border border-sidebar-border/70 p-4 transition hover:border-foreground/20 hover:shadow-sm dark:border-sidebar-border"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                :style="{ backgroundColor: `${project.color}1f`, color: project.color }"
            >
                <Folder class="size-5" />
            </span>
            <span class="truncate font-medium">{{ project.name }}</span>
        </Link>
    </div>
</template>
