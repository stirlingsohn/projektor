<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { useTemplateRef } from 'vue';

const props = defineProps<{
    listId: number;
    statusId?: number;
    placeholder: string;
}>();

const form = useForm({ title: '', status_id: props.statusId ?? null });
const input = useTemplateRef<HTMLInputElement>('input');

// The field stays enabled while saving, so focus never leaves it and the
// next task can be typed right away.
const submit = () => {
    if (form.processing || !form.title.trim()) return;
    form.post(route('lists.tasks.store', props.listId), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset('title');
            input.value?.focus();
        },
    });
};
</script>

<template>
    <form class="flex items-center gap-3" @submit.prevent="submit">
        <Plus class="size-[18px] shrink-0 text-muted-foreground" />
        <input
            ref="input"
            v-model="form.title"
            class="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
            :placeholder="placeholder"
            maxlength="255"
        />
    </form>
</template>
