import type { Task } from '@/types';
import { router } from '@inertiajs/vue3';

/**
 * Options for saves that happen in the background. `async` keeps several
 * quick saves from cancelling each other.
 */
const background = { preserveScroll: true, preserveState: true, async: true } as const;

export const updateTask = (task: Task, attributes: Partial<Task>) => router.patch(route('tasks.update', task.id), attributes, background);

/** Place a task next to its new neighbours, optionally in another status. */
export const moveTask = (task: Task, neighbours: { after?: Task; before?: Task }, statusId?: number) =>
    router.put(
        route('tasks.move', task.id),
        { status_id: statusId, after_id: neighbours.after?.id ?? null, before_id: neighbours.before?.id ?? null },
        background,
    );
