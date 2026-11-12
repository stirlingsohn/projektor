<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SelectMenu, { type SelectOption } from '@/components/SelectMenu.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { expandProjects, flattenTree, PROJECT_COLORS, projectDialog } from '@/lib/projects';
import type { SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { Check, Folder } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const page = usePage<SharedData>();

const form = useForm({
    name: '',
    color: PROJECT_COLORS[0],
    parent_id: null as number | null,
    client_can_see_time: false,
});

const editing = computed(() => (projectDialog.value?.mode === 'edit' ? projectDialog.value.project : null));
const parentSelectOptions = computed<SelectOption<number | null>[]>(() => [
    { value: null, label: t('None (top level)') },
    ...flattenTree(page.props.projects, editing.value?.id).map(({ project, depth }) => ({
        value: project.id,
        label: project.name,
        icon: Folder,
        color: project.color,
        depth,
    })),
]);

watch(projectDialog, (dialog) => {
    form.clearErrors();
    if (dialog?.mode === 'edit') {
        form.defaults({
            name: dialog.project.name,
            color: dialog.project.color,
            parent_id: dialog.project.parent_id,
            client_can_see_time: dialog.project.client_can_see_time,
        });
    } else if (dialog) {
        form.defaults({ name: '', color: PROJECT_COLORS[0], parent_id: dialog.parentId, client_can_see_time: false });
    }
    form.reset();
});

const close = () => (projectDialog.value = null);

const submit = () => {
    const onSuccess = () => {
        if (form.parent_id) {
            expandProjects([form.parent_id]);
        }
        close();
    };

    if (editing.value) {
        form.patch(route('projects.update', editing.value.id), { preserveScroll: true, onSuccess });
    } else {
        form.post(route('projects.store'), { onSuccess });
    }
};
</script>

<template>
    <Dialog :open="projectDialog !== null" @update:open="(open) => !open && close()">
        <DialogContent class="sm:max-w-md">
            <form class="space-y-6" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ editing ? $t('Edit project') : form.parent_id ? $t('New subproject') : $t('New project') }}</DialogTitle>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="project-name">{{ $t('Name') }}</Label>
                    <Input id="project-name" v-model="form.name" required autofocus autocomplete="off" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label>{{ $t('Color') }}</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="color in PROJECT_COLORS"
                            :key="color"
                            type="button"
                            class="flex size-7 items-center justify-center rounded-full ring-offset-2 ring-offset-background transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            :class="{ 'ring-2 ring-foreground/40': form.color === color }"
                            :style="{ backgroundColor: color }"
                            :aria-label="color"
                            :aria-pressed="form.color === color"
                            @click="form.color = color"
                        >
                            <Check v-if="form.color === color" class="size-4 text-white" />
                        </button>
                    </div>
                    <InputError :message="form.errors.color" />
                </div>

                <div class="grid gap-2">
                    <Label for="project-parent">{{ $t('Parent project') }}</Label>
                    <SelectMenu id="project-parent" v-model="form.parent_id" :options="parentSelectOptions" />
                    <InputError :message="form.errors.parent_id" />
                </div>

                <Label class="flex items-start gap-3 font-normal">
                    <Checkbox v-model:checked="form.client_can_see_time" class="mt-0.5" />
                    <span class="grid gap-0.5">
                        <span class="font-medium">{{ $t('Clients can see tracked time') }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            $t('Invited clients see the time tracked on the tasks of this project.')
                        }}</span>
                    </span>
                </Label>

                <DialogFooter class="gap-2">
                    <Button type="button" variant="secondary" @click="close">{{ $t('Cancel') }}</Button>
                    <Button type="submit" :disabled="form.processing">{{ editing ? $t('Save') : $t('Create') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
