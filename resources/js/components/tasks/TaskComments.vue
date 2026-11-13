<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import UserAvatar from '@/components/UserAvatar.vue';
import type { Comment, SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    taskId: number;
    /** The comments of this task, oldest first. */
    comments: Comment[];
    canComment: boolean;
}>();

const page = usePage<SharedData>();
const me = page.props.auth.user;

const form = useForm({ body: '' });
const submit = () => {
    if (!form.body.trim() || form.processing) return;
    form.post(route('tasks.comments.store', props.taskId), { preserveScroll: true, preserveState: true, onSuccess: () => form.reset() });
};

const editing = ref<number | null>(null);
const editBody = ref('');
watch(
    () => props.taskId,
    () => {
        editing.value = null;
        form.reset();
        form.clearErrors();
    },
);
const startEdit = (comment: Comment) => {
    editing.value = comment.id;
    editBody.value = comment.body;
};
const saveEdit = (comment: Comment) => {
    const body = editBody.value.trim();
    if (body && body !== comment.body) router.patch(route('comments.update', comment.id), { body }, { preserveScroll: true, preserveState: true });
    editing.value = null;
};
const destroy = (comment: Comment) => router.delete(route('comments.destroy', comment.id), { preserveScroll: true, preserveState: true });

const formatTime = (date: string) =>
    new Date(date).toLocaleString(document.documentElement.lang || 'de-CH', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <section class="space-y-3">
        <h3 class="text-sm font-medium">{{ $t('Comments') }}</h3>

        <ul v-if="comments.length" class="space-y-3">
            <li v-for="comment in comments" :key="comment.id" class="group flex gap-3">
                <UserAvatar :name="comment.user" :avatar="comment.avatar" class="size-7 text-[11px]" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline gap-2 text-xs">
                        <span class="font-medium">{{ comment.user ?? $t('Former member') }}</span>
                        <span class="text-muted-foreground"
                            >{{ formatTime(comment.created_at) }}<template v-if="comment.edited"> · {{ $t('edited') }}</template></span
                        >
                        <span class="ml-auto flex gap-0.5 opacity-0 transition group-focus-within:opacity-100 group-hover:opacity-100">
                            <Button
                                v-if="comment.user_id === me.id && canComment"
                                variant="ghost"
                                size="icon"
                                class="size-6"
                                :aria-label="$t('Edit')"
                                @click="startEdit(comment)"
                            >
                                <Pencil class="size-3.5" />
                            </Button>
                            <Button
                                v-if="comment.user_id === me.id || me.role === 'owner'"
                                variant="ghost"
                                size="icon"
                                class="size-6"
                                :aria-label="$t('Delete')"
                                @click="destroy(comment)"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </span>
                    </div>
                    <textarea
                        v-if="editing === comment.id"
                        v-model="editBody"
                        class="mt-1 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm outline-none [field-sizing:content] focus-visible:ring-1 focus-visible:ring-ring"
                        @keydown.enter.exact.prevent="saveEdit(comment)"
                        @keydown.esc.stop.prevent="editing = null"
                        @blur="saveEdit(comment)"
                    />
                    <p v-else class="mt-0.5 whitespace-pre-wrap break-words text-sm">{{ comment.body }}</p>
                </div>
            </li>
        </ul>
        <p v-else-if="!canComment" class="text-sm text-muted-foreground">{{ $t('No comments yet.') }}</p>

        <form v-if="canComment" class="space-y-2" @submit.prevent="submit">
            <textarea
                v-model="form.body"
                rows="2"
                maxlength="5000"
                class="w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm outline-none [field-sizing:content] focus-visible:ring-1 focus-visible:ring-ring"
                :placeholder="$t('Write a comment… (Enter to send, Shift+Enter for a new line)')"
                :aria-label="$t('Comment')"
                @keydown.enter.exact.prevent="submit"
            />
            <InputError :message="form.errors.body" />
        </form>
    </section>
</template>
