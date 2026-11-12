<script setup lang="ts">
import { t } from '@/lib/i18n';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import TaskItem from '@tiptap/extension-task-item';
import TaskList from '@tiptap/extension-task-list';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor, type JSONContent } from '@tiptap/vue-3';
import {
    Bold,
    Code,
    Heading1,
    Heading2,
    Heading3,
    Italic,
    Link as LinkIcon,
    List,
    ListChecks,
    ListOrdered,
    Minus,
    Quote,
    Redo2,
    Strikethrough,
    Undo2,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, useTemplateRef, watch, type Component } from 'vue';

const props = defineProps<{
    content: JSONContent | null;
    editable: boolean;
}>();

const emit = defineEmits<{
    /** The content changed; sent on every edit, callers debounce. */
    change: [content: JSONContent];
}>();

const editor = useEditor({
    content: props.content ?? '',
    editable: props.editable,
    extensions: [
        StarterKit.configure({ heading: { levels: [1, 2, 3] } }),
        Placeholder.configure({ placeholder: t('Start writing: lyrics, notes, briefings…') }),
        Link.configure({ openOnClick: false, autolink: true, HTMLAttributes: { rel: 'noopener noreferrer nofollow', target: '_blank' } }),
        TaskList,
        TaskItem.configure({ nested: true }),
    ],
    onUpdate: ({ editor }) => emit('change', editor.getJSON()),
});

watch(
    () => props.editable,
    (editable) => editor.value?.setEditable(editable),
);
onBeforeUnmount(() => editor.value?.destroy());

type Tool = { label: string; icon: Component; run: () => void; active?: () => boolean; disabled?: () => boolean } | 'divider';

const chain = () => editor.value!.chain().focus();
const isActive = (name: string, attributes?: Record<string, unknown>) => editor.value?.isActive(name, attributes) ?? false;

const tools = computed<Tool[]>(() => [
    { label: t('Undo'), icon: Undo2, run: () => chain().undo().run(), disabled: () => !editor.value?.can().undo() },
    { label: t('Redo'), icon: Redo2, run: () => chain().redo().run(), disabled: () => !editor.value?.can().redo() },
    'divider',
    { label: t('Heading 1'), icon: Heading1, run: () => chain().toggleHeading({ level: 1 }).run(), active: () => isActive('heading', { level: 1 }) },
    { label: t('Heading 2'), icon: Heading2, run: () => chain().toggleHeading({ level: 2 }).run(), active: () => isActive('heading', { level: 2 }) },
    { label: t('Heading 3'), icon: Heading3, run: () => chain().toggleHeading({ level: 3 }).run(), active: () => isActive('heading', { level: 3 }) },
    'divider',
    { label: t('Bold'), icon: Bold, run: () => chain().toggleBold().run(), active: () => isActive('bold') },
    { label: t('Italic'), icon: Italic, run: () => chain().toggleItalic().run(), active: () => isActive('italic') },
    { label: t('Strikethrough'), icon: Strikethrough, run: () => chain().toggleStrike().run(), active: () => isActive('strike') },
    { label: t('Code'), icon: Code, run: () => chain().toggleCode().run(), active: () => isActive('code') },
    { label: t('Link'), icon: LinkIcon, run: () => openLink(), active: () => isActive('link') },
    'divider',
    { label: t('Bulleted list'), icon: List, run: () => chain().toggleBulletList().run(), active: () => isActive('bulletList') },
    { label: t('Numbered list'), icon: ListOrdered, run: () => chain().toggleOrderedList().run(), active: () => isActive('orderedList') },
    { label: t('Checklist'), icon: ListChecks, run: () => chain().toggleTaskList().run(), active: () => isActive('taskList') },
    { label: t('Quote'), icon: Quote, run: () => chain().toggleBlockquote().run(), active: () => isActive('blockquote') },
    { label: t('Divider'), icon: Minus, run: () => chain().setHorizontalRule().run() },
]);

// A small inline field instead of a browser prompt for adding links.
const linkOpen = ref(false);
const linkUrl = ref('');
const linkInput = useTemplateRef<HTMLInputElement>('linkInput');
const openLink = () => {
    linkUrl.value = editor.value?.getAttributes('link').href ?? '';
    linkOpen.value = true;
    nextTick(() => linkInput.value?.select());
};
const applyLink = () => {
    const url = linkUrl.value.trim();
    if (!url) {
        chain().extendMarkRange('link').unsetLink().run();
    } else {
        chain()
            .extendMarkRange('link')
            .setLink({ href: /^[a-z]+:/i.test(url) ? url : `https://${url}` })
            .run();
    }
    linkOpen.value = false;
};
</script>

<template>
    <div class="flex flex-col">
        <div
            v-if="editable && editor"
            class="sticky top-0 z-10 -mx-1 mb-4 flex flex-wrap items-center gap-0.5 border-b border-sidebar-border/70 bg-background/95 px-1 py-1.5 backdrop-blur"
            role="toolbar"
            :aria-label="$t('Formatting')"
        >
            <template v-for="(tool, index) in tools" :key="index">
                <span v-if="tool === 'divider'" class="mx-1 h-5 w-px bg-sidebar-border" />
                <button
                    v-else
                    type="button"
                    class="flex size-8 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                    :class="{ 'bg-muted text-foreground': tool.active?.() }"
                    :title="tool.label"
                    :aria-label="tool.label"
                    :aria-pressed="tool.active ? tool.active() : undefined"
                    :disabled="tool.disabled?.()"
                    @mousedown.prevent
                    @click="tool.run"
                >
                    <component :is="tool.icon" class="size-4" />
                </button>
            </template>

            <form v-if="linkOpen" class="ml-1 flex items-center gap-1" @submit.prevent="applyLink">
                <input
                    ref="linkInput"
                    v-model="linkUrl"
                    class="h-8 w-56 rounded-md border border-input bg-transparent px-2 text-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    :placeholder="$t('Paste a link, empty to remove')"
                    @keydown.esc.prevent="linkOpen = false"
                    @blur="linkOpen = false"
                />
            </form>
        </div>

        <EditorContent :editor="editor" class="rich-text flex-1" />
    </div>
</template>
