<script setup lang="ts">
import RichTextEditor from '@/components/documents/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { t } from '@/lib/i18n';
import { expandProjects } from '@/lib/projects';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import type { JSONContent } from '@tiptap/vue-3';
import { useDebounceFn, useEventListener } from '@vueuse/core';
import { Check, Loader2, Trash2, TriangleAlert } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';

const props = defineProps<{
    document: { id: number; project_id: number; title: string; content: JSONContent | null; updated_at: string };
    ancestors: { id: number; name: string }[];
    canEdit: boolean;
}>();

const title = ref(props.document.title);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('Overview'), href: route('dashboard') },
    ...props.ancestors.map((ancestor) => ({ title: ancestor.name, href: route('projects.show', ancestor.id) })),
    { title: title.value || props.document.title, href: route('documents.show', props.document.id) },
]);

const titleInput = useTemplateRef<HTMLTextAreaElement>('titleInput');

onMounted(() => {
    expandProjects(props.ancestors.map((ancestor) => ancestor.id));
    // A brand-new document starts with its title selected, ready to be typed over.
    if (props.canEdit && props.document.content === null) titleInput.value?.select();
});

// Autosave: changes wait a moment, then go to the server one save at a time.
const state = ref<'saved' | 'pending' | 'saving' | 'error'>('saved');
let pending: { title?: string; content?: JSONContent } = {};

const flush = () => {
    if (!Object.keys(pending).length || state.value === 'saving') return;
    const changes = pending;
    pending = {};
    state.value = 'saving';
    router.put(route('documents.update', props.document.id), changes, {
        preserveScroll: true,
        preserveState: true,
        async: true,
        only: ['documents'],
        onSuccess: () => (state.value = Object.keys(pending).length ? 'pending' : 'saved'),
        onError: () => {
            pending = { ...changes, ...pending };
            state.value = 'error';
        },
        onNetworkError: () => {
            pending = { ...changes, ...pending };
            state.value = 'error';
        },
        onFinish: () => state.value === 'pending' && flush(),
    });
};
const scheduleFlush = useDebounceFn(flush, 800);

const queue = (changes: typeof pending) => {
    Object.assign(pending, changes);
    if (state.value !== 'saving') state.value = 'pending';
    scheduleFlush();
};

const saveTitle = () => {
    const value = title.value.trim();
    if (!value) {
        title.value = props.document.title;
        return;
    }
    if (value !== props.document.title) queue({ title: value });
};

// Save what is left when leaving the page, and warn if it might get lost.
onBeforeUnmount(flush);
useEventListener(window, 'beforeunload', (event: BeforeUnloadEvent) => {
    if (state.value !== 'saved') event.preventDefault();
});

const confirmDelete = ref(false);
const destroy = () => router.delete(route('documents.destroy', props.document.id));
</script>

<template>
    <Head :title="title || document.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4 md:p-6">
            <div class="flex items-start gap-3">
                <textarea
                    ref="titleInput"
                    v-model="title"
                    rows="1"
                    class="min-w-0 flex-1 resize-none bg-transparent text-3xl font-semibold tracking-tight outline-none [field-sizing:content]"
                    :readonly="!canEdit"
                    :aria-label="$t('Title')"
                    maxlength="255"
                    @input="saveTitle"
                    @blur="saveTitle"
                    @keydown.enter.prevent="($event.target as HTMLElement).blur()"
                />
                <div v-if="canEdit" class="flex shrink-0 items-center gap-2 pt-2">
                    <span class="flex items-center gap-1 text-xs text-muted-foreground" aria-live="polite">
                        <template v-if="state === 'saved'"><Check class="size-3.5" />{{ $t('Saved') }}</template>
                        <template v-else-if="state === 'error'">
                            <TriangleAlert class="size-3.5 text-red-600" />
                            <button type="button" class="text-red-600 underline" @click="flush">{{ $t('Not saved, try again') }}</button>
                        </template>
                        <template v-else><Loader2 class="size-3.5 animate-spin" />{{ $t('Saving…') }}</template>
                    </span>
                    <Button variant="ghost" size="icon" class="size-8" :aria-label="$t('Delete document')" @click="confirmDelete = true">
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>

            <RichTextEditor :content="document.content" :editable="canEdit" class="flex-1" @change="(content) => queue({ content })" />
        </div>

        <Dialog v-model:open="confirmDelete">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ $t('Delete document?') }}</DialogTitle>
                    <DialogDescription>{{ $t('":name" will be permanently deleted.', { name: title || document.title }) }}</DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button variant="secondary" @click="confirmDelete = false">{{ $t('Cancel') }}</Button>
                    <Button variant="destructive" @click="destroy">{{ $t('Delete') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
