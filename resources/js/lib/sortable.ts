import { useSortable, type UseSortableOptions } from '@vueuse/integrations/useSortable';
import { type Ref, watch } from 'vue';

/**
 * Drag-and-drop sorting of `list` inside `el`, re-attached whenever the
 * element is (re-)rendered, e.g. inside a dialog or behind a `v-if`.
 */
export function useSortableList<T>(el: Readonly<Ref<HTMLElement | null>>, list: Ref<T[]>, options: UseSortableOptions = {}) {
    const sortable = useSortable(el, list, { animation: 150, handle: '.drag-handle', ...options });

    watch(el, (element) => {
        sortable.stop();
        if (element) sortable.start();
    });
}
