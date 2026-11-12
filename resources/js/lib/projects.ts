import type { ListSummary, ProjectSummary } from '@/types';
import { router } from '@inertiajs/vue3';
import { useStorage } from '@vueuse/core';
import { ref } from 'vue';

/** IDs of projects expanded in the sidebar, remembered across page visits. */
export const expandedProjectIds = useStorage<number[]>('projektor.expanded-projects', []);

export const PROJECT_COLORS = ['#6366f1', '#0ea5e9', '#14b8a6', '#22c55e', '#eab308', '#f97316', '#ef4444', '#ec4899', '#a855f7', '#64748b'];

/** The project dialog that is currently open, shared by the sidebar and pages. */
export const projectDialog = ref<{ mode: 'create'; parentId: number | null } | { mode: 'edit'; project: ProjectSummary } | null>(null);

/** The project awaiting delete confirmation. */
export const projectToDelete = ref<ProjectSummary | null>(null);

export const createProject = (parentId: number | null = null) => (projectDialog.value = { mode: 'create', parentId });
export const editProject = (project: ProjectSummary) => (projectDialog.value = { mode: 'edit', project });
export const deleteProject = (project: ProjectSummary) => (projectToDelete.value = project);

/** The list dialog that is currently open. */
export const listDialog = ref<{ mode: 'create'; projectId: number } | { mode: 'edit'; list: ListSummary } | null>(null);

export const createList = (projectId: number) => (listDialog.value = { mode: 'create', projectId });
export const editList = (list: ListSummary) => (listDialog.value = { mode: 'edit', list });

export const expandProjects = (ids: number[]) => {
    expandedProjectIds.value = [...new Set([...expandedProjectIds.value, ...ids])];
};

export function groupBy<T, K>(items: T[], key: (item: T) => K): Map<K, T[]> {
    const map = new Map<K, T[]>();

    for (const item of items) {
        map.set(key(item), [...(map.get(key(item)) ?? []), item]);
    }

    return map;
}

export const childrenByParent = (projects: ProjectSummary[]) => groupBy(projects, (project) => project.parent_id);

/** Top-level projects, plus shared subprojects whose parent the user cannot see. */
export function rootProjects(projects: ProjectSummary[]): ProjectSummary[] {
    const visibleIds = new Set(projects.map((project) => project.id));

    return projects.filter((project) => project.parent_id === null || !visibleIds.has(project.parent_id));
}

/** All projects in tree order with their depth, skipping the subtree of `excludeId`. */
export function flattenTree(projects: ProjectSummary[], excludeId?: number): { project: ProjectSummary; depth: number }[] {
    const children = childrenByParent(projects);
    const walk = (parentId: number | null, depth: number): { project: ProjectSummary; depth: number }[] =>
        (children.get(parentId) ?? [])
            .filter((project) => project.id !== excludeId)
            .flatMap((project) => [{ project, depth }, ...walk(project.id, depth + 1)]);

    return walk(null, 0);
}

/** Create an empty document in the project and open it. */
export const createDocument = (projectId: number) => {
    expandProjects([projectId]);
    router.post(route('projects.documents.store', projectId));
};
