<script setup lang="ts">
import DateField from '@/components/DateField.vue';
import AttachmentList from '@/components/files/AttachmentList.vue';
import InputError from '@/components/InputError.vue';
import SelectMenu from '@/components/SelectMenu.vue';
import TaskComments from '@/components/tasks/TaskComments.vue';
import TaskTimeSection from '@/components/tasks/TaskTimeSection.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { Attachment, Comment, Status, Task, TimeEntry } from '@/types';
import { router } from '@inertiajs/vue3';
import { Trash2, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps<{
    task: Task | null;
    /** All tasks of the list, to choose dependencies from. */
    tasks: Task[];
    statuses: Status[];
    canEdit: boolean;
    /** Finished entries of all tasks in the list; empty when time is hidden. */
    timeEntries: TimeEntry[];
    showsTime: boolean;
    /** Files attached to tasks of the list. */
    attachments: Attachment[];
    projectId: number;
    comments: Comment[];
    canComment: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const fields = ref({ title: '', description: '', status_id: 0, start_date: '', due_date: '' });
const errors = ref<Record<string, string>>({});

// Only reset the fields when another task is opened, so that responses to
// earlier saves never overwrite what is being typed.
watch(
    () => props.task?.id,
    () => {
        const task = props.task;
        errors.value = {};
        if (!task) return;
        fields.value = {
            title: task.title,
            description: task.description ?? '',
            status_id: task.status_id,
            start_date: task.start_date ?? '',
            due_date: task.due_date ?? '',
        };
    },
    { immediate: true },
);

// Dates can also change on the server when a task this one depends on moves.
watch(
    () => [props.task?.start_date, props.task?.due_date],
    ([start, due]) => Object.assign(fields.value, { start_date: start ?? '', due_date: due ?? '' }),
);

const statusOptions = computed(() => props.statuses.map((status) => ({ value: status.id, label: status.name, color: status.color })));

const taskComments = computed(() => props.comments.filter((comment) => comment.task_id === props.task?.id));
const taskAttachments = computed(() => props.attachments.filter((attachment) => attachment.task_id === props.task?.id));
const taskEntries = computed(() => props.timeEntries.filter((entry) => entry.task_id === props.task?.id));

const dependencies = computed(() => props.tasks.filter((task) => props.task?.depends_on.includes(task.id)));
const dependencyOptions = computed(() =>
    props.tasks
        .filter((task) => task.id !== props.task?.id && !props.task?.depends_on.includes(task.id))
        .map((task) => ({ value: task.id, label: task.title })),
);

const addDependency = (dependsOnId: number) => {
    if (!props.task) return;
    router.post(
        route('tasks.dependencies.store', props.task.id),
        { depends_on_id: dependsOnId },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => delete errors.value.depends_on_id,
            onError: (newErrors) => (errors.value = newErrors),
        },
    );
};

const removeDependency = (dependsOn: Task) =>
    props.task && router.delete(route('tasks.dependencies.destroy', [props.task.id, dependsOn.id]), { preserveScroll: true, preserveState: true });

/** Save one field if it changed; empty strings clear optional fields. */
const save = async (field: keyof typeof fields.value) => {
    // `Input` passes on its new value a tick after the native change event.
    await nextTick();
    const task = props.task;
    const value = fields.value[field] === '' && field !== 'title' ? null : fields.value[field];
    if (!task || value === (task[field] ?? null) || (field === 'title' && value === '')) return;

    router.patch(
        route('tasks.update', task.id),
        { [field]: value },
        {
            preserveScroll: true,
            preserveState: true,
            async: true,
            onSuccess: () => delete errors.value[field],
            onError: (newErrors) => (errors.value = newErrors),
        },
    );
};

const destroy = () => {
    if (!props.task) return;
    router.delete(route('tasks.destroy', props.task.id), { preserveScroll: true, preserveState: true, onSuccess: () => emit('close') });
};
</script>

<template>
    <Dialog :open="task !== null" @update:open="(open) => !open && $emit('close')">
        <DialogContent class="flex max-h-[90dvh] w-[calc(100%-2rem)] flex-col gap-6 overflow-y-auto rounded-lg sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="sr-only">{{ $t('Task details') }}</DialogTitle>
                <textarea
                    v-model="fields.title"
                    rows="1"
                    class="w-full resize-none bg-transparent pr-6 text-xl font-semibold outline-none [field-sizing:content]"
                    :readonly="!canEdit"
                    @blur="save('title')"
                    @keydown.enter.prevent="($event.target as HTMLElement).blur()"
                />
                <InputError :message="errors.title" />
            </DialogHeader>

            <div class="grid grid-cols-[7rem_1fr] items-center gap-x-4 gap-y-3 text-sm">
                <Label for="task-status">{{ $t('Status') }}</Label>
                <SelectMenu id="task-status" v-model="fields.status_id" :options="statusOptions" :disabled="!canEdit" @change="save('status_id')" />

                <Label for="task-start">{{ $t('Date range') }}</Label>
                <div>
                    <div class="grid grid-cols-1 gap-2 min-[420px]:grid-cols-2">
                        <DateField
                            id="task-start"
                            v-model="fields.start_date"
                            :aria-label="$t('Start date')"
                            :title="$t('Start date')"
                            :disabled="!canEdit"
                            @change="save('start_date')"
                        />
                        <DateField
                            id="task-due"
                            v-model="fields.due_date"
                            :aria-label="$t('End date')"
                            :title="$t('End date')"
                            :min="fields.start_date || undefined"
                            :disabled="!canEdit"
                            @change="save('due_date')"
                        />
                    </div>
                    <InputError :message="errors.due_date" class="mt-1" />
                </div>

                <Label for="task-dependency" class="self-start pt-2">{{ $t('Waits for') }}</Label>
                <div class="space-y-2">
                    <ul v-if="dependencies.length" class="space-y-1">
                        <li
                            v-for="dependency in dependencies"
                            :key="dependency.id"
                            class="flex items-center gap-2 rounded-md bg-muted/50 py-1 pl-2 pr-1"
                        >
                            <span class="min-w-0 flex-1 truncate">{{ dependency.title }}</span>
                            <Button
                                v-if="canEdit"
                                variant="ghost"
                                size="icon"
                                class="size-6 shrink-0"
                                :aria-label="$t('Remove')"
                                @click="removeDependency(dependency)"
                            >
                                <X class="size-3.5" />
                            </Button>
                        </li>
                    </ul>
                    <SelectMenu
                        v-if="canEdit && dependencyOptions.length"
                        id="task-dependency"
                        :options="dependencyOptions"
                        :placeholder="$t('Add dependency…')"
                        @change="addDependency"
                    />
                    <p v-else-if="!dependencies.length" class="pt-2 text-muted-foreground">{{ $t('None') }}</p>
                    <InputError :message="errors.depends_on_id" />
                </div>
            </div>

            <TaskTimeSection v-if="task && showsTime" :task="task" :entries="taskEntries" :can-edit="canEdit" />

            <div class="flex flex-1 flex-col gap-2">
                <Label for="task-description">{{ $t('Description') }}</Label>
                <textarea
                    id="task-description"
                    v-model="fields.description"
                    :readonly="!canEdit"
                    :placeholder="canEdit ? $t('Add notes, links or lyrics…') : ''"
                    class="min-h-32 w-full flex-1 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    @blur="save('description')"
                />
                <InputError :message="errors.description" />
            </div>

            <section v-if="task && (canEdit || taskAttachments.length)" class="space-y-3">
                <h3 class="text-sm font-medium">{{ $t('Files') }}</h3>
                <AttachmentList :attachments="taskAttachments" :project-id="projectId" :task-id="task.id" :can-edit="canEdit" />
            </section>

            <TaskComments v-if="task" :task-id="task.id" :comments="taskComments" :can-comment="canComment" />

            <div v-if="canEdit" class="flex justify-end">
                <Button variant="ghost" size="sm" class="text-red-600 hover:text-red-600" @click="destroy">
                    <Trash2 class="size-4" />
                    {{ $t('Delete task') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
