<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { redo, undo } from '@/lib/history';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Toast {
    message: string;
    undo?: number;
    redo?: number;
    error?: boolean;
}

const page = usePage<SharedData>();
const toast = ref<Toast | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;

const hideLater = () => {
    clearTimeout(timer);
    timer = setTimeout(() => (toast.value = null), 8000);
};
/** Keep the notice while the pointer is on it. */
const pause = () => clearTimeout(timer);
const show = (next: Toast) => {
    toast.value = next;
    hideLater();
};

// Every response carries a fresh object, so the same notice can show twice in a row.
watch(
    () => page.props.toast,
    (next) => next && show(next),
);
watch(
    () => page.props.errors?.history,
    (message) => message && show({ message, error: true }),
);

// The buttons stay clickable while the notice fades out, so read it first.
const act = (kind: 'undo' | 'redo') => {
    const listId = toast.value?.[kind];
    toast.value = null;
    if (listId) (kind === 'undo' ? undo : redo)(listId);
};
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="translate-y-2 opacity-0"
    >
        <div
            v-if="toast"
            role="status"
            class="fixed bottom-4 left-1/2 z-50 flex max-w-[calc(100%-2rem)] -translate-x-1/2 items-center gap-3 rounded-lg border bg-neutral-900 py-2 pl-4 pr-2 text-sm text-white shadow-lg dark:border-neutral-700 dark:bg-neutral-800"
            :class="{ 'border-red-500/50': toast.error }"
            @mouseenter="pause"
            @mouseleave="hideLater"
        >
            <span class="min-w-0 truncate">{{ toast.message }}</span>
            <Button v-if="toast.undo" size="sm" variant="ghost" class="h-7 text-white hover:bg-white/10 hover:text-white" @click="act('undo')">
                {{ $t('Undo') }}
            </Button>
            <Button v-if="toast.redo" size="sm" variant="ghost" class="h-7 text-white hover:bg-white/10 hover:text-white" @click="act('redo')">
                {{ $t('Redo') }}
            </Button>
            <button type="button" class="rounded p-1 text-white/60 hover:text-white" :aria-label="$t('Close')" @click="toast = null">
                <X class="size-4" />
            </button>
        </div>
    </Transition>
</template>
