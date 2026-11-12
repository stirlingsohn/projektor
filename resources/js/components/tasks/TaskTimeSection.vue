<script setup lang="ts">
import DateField from '@/components/DateField.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDay, today } from '@/lib/dates';
import { t } from '@/lib/i18n';
import { formatClock, formatDuration, parseDuration, useTimer } from '@/lib/time';
import type { Task, TimeEntry } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Play, Square, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    task: Task;
    /** The finished entries of this task, newest first. */
    entries: TimeEntry[];
    canEdit: boolean;
}>();

const { elapsed, isRunningOn, start, stop } = useTimer();
const running = computed(() => isRunningOn(props.task));
const total = computed(() => (props.task.tracked_seconds ?? 0) + (running.value ? elapsed.value : 0));

const form = useForm({ date: today(), duration: '', note: '' });
const editing = ref<TimeEntry | null>(null);
const minutes = computed(() => parseDuration(form.duration));
const durationHint = computed(() => {
    if (!form.duration.trim()) return '';
    return minutes.value === null ? t('Try 1:30, 1.5 or 45m') : `= ${formatDuration(minutes.value * 60)}`;
});

const reset = () => {
    editing.value = null;
    form.reset();
    form.date = today();
    form.clearErrors();
};
watch(() => props.task.id, reset);

const edit = (entry: TimeEntry) => {
    editing.value = entry;
    form.date = entry.date;
    form.duration = formatClock(entry.seconds).replace(/:\d\d$/, '');
    form.note = entry.note ?? '';
};

const submit = () => {
    if (minutes.value === null) {
        form.setError('duration', t('Enter a duration like 1:30 or 45m.'));
        return;
    }
    form.transform(({ date, note }) => ({ date, note: note || null, minutes: minutes.value }));
    const options = { preserveScroll: true, preserveState: true, onSuccess: reset };
    if (editing.value) {
        form.patch(route('time-entries.update', editing.value.id), options);
    } else {
        form.post(route('tasks.time-entries.store', props.task.id), options);
    }
};

const destroy = (entry: TimeEntry) => {
    if (editing.value?.id === entry.id) reset();
    router.delete(route('time-entries.destroy', entry.id), { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <section class="space-y-3">
        <div class="flex items-center gap-3">
            <h3 class="text-sm font-medium">{{ $t('Tracked time') }}</h3>
            <span class="text-sm tabular-nums text-muted-foreground">{{ total ? formatDuration(total) : '–' }}</span>
            <template v-if="canEdit">
                <Button v-if="running" variant="outline" size="sm" class="ml-auto border-red-500/40 text-red-600 hover:text-red-600" @click="stop">
                    <Square class="size-3.5 fill-current" />
                    <span class="tabular-nums">{{ formatClock(elapsed) }}</span>
                </Button>
                <Button v-else variant="outline" size="sm" class="ml-auto" @click="start(task)">
                    <Play class="size-3.5 fill-current" />
                    {{ $t('Start timer') }}
                </Button>
            </template>
        </div>

        <form v-if="canEdit" class="space-y-1" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-[10rem_6rem_1fr_auto]">
                <DateField v-model="form.date" :aria-label="$t('Date')" />
                <Input v-model="form.duration" :placeholder="$t('Duration')" :title="$t('Try 1:30, 1.5 or 45m')" />
                <Input v-model="form.note" class="col-span-2 sm:col-span-1" :placeholder="$t('Note (optional)')" maxlength="255" />
                <div class="col-span-2 flex gap-2 sm:col-span-1">
                    <Button type="submit" size="sm" class="h-9 flex-1" :disabled="form.processing">
                        {{ editing ? $t('Save') : $t('Add') }}
                    </Button>
                    <Button v-if="editing" type="button" variant="ghost" size="sm" class="h-9" @click="reset">{{ $t('Cancel') }}</Button>
                </div>
            </div>
            <p v-if="durationHint && !form.errors.duration" class="text-xs text-muted-foreground">{{ durationHint }}</p>
            <InputError :message="form.errors.duration || form.errors.minutes || form.errors.date || form.errors.note" />
        </form>

        <ul v-if="entries.length" class="divide-y divide-sidebar-border/50 rounded-md border border-sidebar-border/70 text-sm">
            <li
                v-for="entry in entries"
                :key="entry.id"
                class="group flex items-center gap-3 px-3 py-1.5"
                :class="{ 'bg-muted/50': editing?.id === entry.id }"
            >
                <span class="w-20 shrink-0 text-muted-foreground">{{ formatDay(entry.date) }}</span>
                <span class="w-20 shrink-0 tabular-nums">{{ formatDuration(entry.seconds) }}</span>
                <span class="min-w-0 flex-1 truncate text-muted-foreground">{{ entry.note }}</span>
                <template v-if="canEdit">
                    <Button variant="ghost" size="icon" class="size-6 shrink-0" :aria-label="$t('Edit')" @click="edit(entry)">
                        <Pencil class="size-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon" class="size-6 shrink-0" :aria-label="$t('Delete')" @click="destroy(entry)">
                        <X class="size-3.5" />
                    </Button>
                </template>
            </li>
        </ul>
    </section>
</template>
