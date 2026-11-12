<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { expandProjects, listDialog } from '@/lib/projects';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const form = useForm({ name: '' });

const editing = computed(() => (listDialog.value?.mode === 'edit' ? listDialog.value.list : null));

watch(listDialog, (dialog) => {
    form.clearErrors();
    form.defaults({ name: dialog?.mode === 'edit' ? dialog.list.name : '' });
    form.reset();
});

const close = () => (listDialog.value = null);

const submit = () => {
    const dialog = listDialog.value;
    if (dialog?.mode === 'edit') {
        form.patch(route('lists.update', dialog.list.id), { preserveScroll: true, onSuccess: close });
    } else if (dialog) {
        form.post(route('projects.lists.store', dialog.projectId), {
            onSuccess: () => {
                expandProjects([dialog.projectId]);
                close();
            },
        });
    }
};
</script>

<template>
    <Dialog :open="listDialog !== null" @update:open="(open) => !open && close()">
        <DialogContent class="sm:max-w-md">
            <form class="space-y-6" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ editing ? $t('Rename list') : $t('New list') }}</DialogTitle>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="list-name">{{ $t('Name') }}</Label>
                    <Input id="list-name" v-model="form.name" required autofocus autocomplete="off" />
                    <InputError :message="form.errors.name" />
                </div>

                <DialogFooter class="gap-2">
                    <Button type="button" variant="secondary" @click="close">{{ $t('Cancel') }}</Button>
                    <Button type="submit" :disabled="form.processing">{{ editing ? $t('Save') : $t('Create') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
