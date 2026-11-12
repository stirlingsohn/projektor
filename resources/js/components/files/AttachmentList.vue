<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { formatDay } from '@/lib/dates';
import { t } from '@/lib/i18n';
import type { Attachment, SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Download, FileAudio, File as FileIcon, FileText, FileVideo, Loader2, Trash2, Upload } from 'lucide-vue-next';
import { computed, ref, useTemplateRef } from 'vue';

const props = defineProps<{
    attachments: Attachment[];
    projectId: number;
    /** Uploads go to this task when set. */
    taskId?: number;
    canEdit: boolean;
    /** Show which task a file belongs to. */
    showTask?: boolean;
}>();

const page = usePage<SharedData>();

const kind = (attachment: Attachment) => {
    const [type, subtype] = attachment.mime_type.split('/');
    if (type === 'image' && /^(png|jpe?g|gif|webp|avif)$/.test(subtype)) return 'image';
    if (type === 'audio' || type === 'video') return type;
    if (attachment.mime_type === 'application/pdf' || type === 'text') return 'document';

    return 'other';
};
const ICONS = { audio: FileAudio, video: FileVideo, document: FileText, other: FileIcon, image: FileIcon };

const formatSize = (bytes: number) => {
    const units = ['B', 'KB', 'MB', 'GB'];
    const exponent = Math.min(Math.floor(Math.log(Math.max(bytes, 1)) / Math.log(1024)), units.length - 1);
    const value = bytes / 1024 ** exponent;

    return `${value.toLocaleString(document.documentElement.lang || 'de-CH', { maximumFractionDigits: exponent ? 1 : 0 })} ${units[exponent]}`;
};

const images = computed(() => props.attachments.filter((attachment) => kind(attachment) === 'image'));
const others = computed(() => props.attachments.filter((attachment) => kind(attachment) !== 'image'));

// Uploading, by picking files or dropping them anywhere on the list.
const form = useForm<{ files: File[]; task_id: number | null }>({ files: [], task_id: null });
const clientError = ref('');
const input = useTemplateRef<HTMLInputElement>('input');
const dragging = ref(false);

const upload = (files: FileList | File[] | null) => {
    const list = Array.from(files ?? []);
    if (!list.length || !props.canEdit) return;
    const tooLarge = list.find((file) => file.size > page.props.maxUploadMb * 1024 * 1024);
    clientError.value = tooLarge ? t('":name" is larger than :size MB.', { name: tooLarge.name, size: String(page.props.maxUploadMb) }) : '';
    if (tooLarge) return;

    form.files = list;
    form.task_id = props.taskId ?? null;
    form.post(route('projects.attachments.store', props.projectId), {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
        onFinish: () => {
            form.files = [];
            if (input.value) input.value.value = '';
        },
    });
};
const uploadError = computed(
    () => clientError.value || form.errors.files || Object.entries(form.errors).find(([key]) => key.startsWith('files.'))?.[1],
);

const onDrop = (event: DragEvent) => {
    dragging.value = false;
    upload(event.dataTransfer?.files ?? null);
};

const preview = ref<Attachment | null>(null);
const toDelete = ref<Attachment | null>(null);
const destroy = () => {
    if (!toDelete.value) return;
    router.delete(route('attachments.destroy', toDelete.value.id), { preserveScroll: true, preserveState: true });
    if (preview.value?.id === toDelete.value.id) preview.value = null;
    toDelete.value = null;
};
</script>

<template>
    <div
        class="relative space-y-3 rounded-xl"
        @dragover.prevent="canEdit && (dragging = true)"
        @dragleave.self="dragging = false"
        @drop.prevent="onDrop"
    >
        <div
            v-if="dragging"
            class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl border-2 border-dashed border-foreground/30 bg-background/80 text-sm font-medium"
        >
            {{ $t('Drop files to upload') }}
        </div>

        <div v-if="images.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
            <button
                v-for="image in images"
                :key="image.id"
                type="button"
                class="group relative aspect-square overflow-hidden rounded-lg border border-sidebar-border/70 bg-muted"
                :title="image.name"
                @click="preview = image"
            >
                <img :src="image.url" :alt="image.name" loading="lazy" class="size-full object-cover transition group-hover:scale-105" />
            </button>
        </div>

        <ul v-if="others.length" class="divide-y divide-sidebar-border/50 rounded-lg border border-sidebar-border/70 text-sm">
            <li v-for="attachment in others" :key="attachment.id" class="group flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2">
                <component :is="ICONS[kind(attachment)]" class="size-5 shrink-0 text-muted-foreground" />
                <div class="min-w-0 flex-1">
                    <a
                        :href="kind(attachment) === 'other' ? attachment.download_url : attachment.url"
                        :target="kind(attachment) === 'other' ? undefined : '_blank'"
                        class="block truncate font-medium hover:underline"
                        >{{ attachment.name }}</a
                    >
                    <p class="truncate text-xs text-muted-foreground">
                        {{ formatSize(attachment.size) }} · {{ formatDay(attachment.created_at.slice(0, 10)) }}
                        <template v-if="showTask && attachment.task_title"> · {{ attachment.task_title }}</template>
                    </p>
                </div>
                <audio
                    v-if="kind(attachment) === 'audio'"
                    :src="attachment.url"
                    controls
                    preload="metadata"
                    class="order-last h-9 w-full sm:order-none sm:w-64"
                />
                <div class="flex shrink-0 gap-0.5">
                    <Button variant="ghost" size="icon" class="size-8" as="a" :href="attachment.download_url" :aria-label="$t('Download')">
                        <Download class="size-4" />
                    </Button>
                    <Button v-if="canEdit" variant="ghost" size="icon" class="size-8" :aria-label="$t('Delete')" @click="toDelete = attachment">
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </li>
        </ul>

        <div v-if="canEdit" class="flex flex-wrap items-center gap-3">
            <input ref="input" type="file" multiple class="hidden" @change="upload(($event.target as HTMLInputElement).files)" />
            <Button variant="outline" size="sm" :disabled="form.processing" @click="input?.click()">
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                <Upload v-else class="size-4" />
                {{ form.processing && form.progress ? `${Math.round(form.progress.percentage ?? 0)} %` : $t('Upload files') }}
            </Button>
            <span class="text-xs text-muted-foreground">{{
                $t('or drop them here, up to :size MB each', { size: String(page.props.maxUploadMb) })
            }}</span>
        </div>
        <p v-else-if="!attachments.length" class="text-sm text-muted-foreground">{{ $t('No files yet.') }}</p>
        <InputError :message="uploadError" />

        <Dialog :open="preview !== null" @update:open="(open) => !open && (preview = null)">
            <DialogContent class="w-[calc(100%-2rem)] max-w-4xl gap-3 rounded-lg p-3 sm:p-4">
                <DialogHeader class="pr-8">
                    <DialogTitle class="truncate text-base">{{ preview?.name }}</DialogTitle>
                    <DialogDescription v-if="preview" class="text-xs">
                        {{ formatSize(preview.size) }}<template v-if="preview.task_title"> · {{ preview.task_title }}</template>
                    </DialogDescription>
                </DialogHeader>
                <img v-if="preview" :src="preview.url" :alt="preview.name" class="max-h-[70dvh] w-full rounded-md object-contain" />
                <DialogFooter v-if="preview" class="gap-2">
                    <Button v-if="canEdit" variant="ghost" class="text-red-600 hover:text-red-600" @click="toDelete = preview">
                        <Trash2 class="size-4" />
                        {{ $t('Delete') }}
                    </Button>
                    <Button variant="outline" as="a" :href="preview.download_url">
                        <Download class="size-4" />
                        {{ $t('Download') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="toDelete !== null" @update:open="(open) => !open && (toDelete = null)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ $t('Delete file?') }}</DialogTitle>
                    <DialogDescription>{{ $t('":name" will be permanently deleted.', { name: toDelete?.name ?? '' }) }}</DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button variant="secondary" @click="toDelete = null">{{ $t('Cancel') }}</Button>
                    <Button variant="destructive" @click="destroy">{{ $t('Delete') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
