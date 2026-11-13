import { router } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';

const visit = { preserveScroll: true, preserveState: true } as const;

export const undo = (listId: number) => router.post(route('lists.undo', listId), {}, visit);
export const redo = (listId: number) => router.post(route('lists.redo', listId), {}, visit);

/** "⌘" on Apple devices, "Ctrl" elsewhere, for shortcut hints. */
export const modifierKey = /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘' : 'Ctrl+';

/**
 * ⌘Z / Ctrl+Z undoes, ⇧⌘Z / Ctrl+Shift+Z / Ctrl+Y redoes. Text fields keep
 * their own undo, so typing is never affected.
 */
export function useHistoryShortcuts(listId: () => number, enabled: () => boolean) {
    useEventListener(window, 'keydown', (event: KeyboardEvent) => {
        if (!enabled() || !(event.metaKey || event.ctrlKey) || event.altKey) return;
        const key = event.key.toLowerCase();
        const isRedo = (key === 'z' && event.shiftKey) || (key === 'y' && event.ctrlKey && !event.shiftKey);
        const isUndo = key === 'z' && !event.shiftKey;
        if (!isUndo && !isRedo) return;

        const target = event.target as HTMLElement | null;
        if (target?.closest('input, textarea, select, [contenteditable="true"]')) return;

        event.preventDefault();
        if (isRedo) redo(listId());
        else undo(listId());
    });
}
