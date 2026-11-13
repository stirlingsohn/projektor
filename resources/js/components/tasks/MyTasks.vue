<script setup lang="ts">
import { formatDay } from '@/lib/dates';
import { t } from '@/lib/i18n';
import type { MyTask, MyTaskBucket } from '@/types';
import { Link } from '@inertiajs/vue3';
import { CalendarDays, ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    tasks: Record<MyTaskBucket, MyTask[]>;
}>();

const GROUPS = computed(() => [
    { bucket: 'overdue' as const, label: t('Overdue'), tone: 'text-red-600' },
    { bucket: 'today' as const, label: t('Today'), tone: '' },
    { bucket: 'week' as const, label: t('This week'), tone: '' },
    { bucket: 'later' as const, label: t('Later'), tone: 'text-muted-foreground' },
    { bucket: 'undated' as const, label: t('Without a date'), tone: 'text-muted-foreground' },
]);

/** Later and undated tasks start folded so the near ones stand out. */
const open = ref<Record<MyTaskBucket, boolean>>({ overdue: true, today: true, week: true, later: false, undated: false });
const total = computed(() => Object.values(props.tasks).reduce((sum, tasks) => sum + tasks.length, 0));

const dates = (task: MyTask) => {
    if (task.start_date && task.due_date && task.start_date !== task.due_date) return `${formatDay(task.start_date)} – ${formatDay(task.due_date)}`;
    const date = task.due_date ?? task.start_date;
    return date ? formatDay(date) : null;
};
</script>

<template>
    <section class="space-y-3">
        <h2 class="font-medium">{{ $t('Up next for me') }}</h2>

        <div v-if="total" class="divide-y divide-sidebar-border/50 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <template v-for="group in GROUPS" :key="group.bucket">
                <div v-if="tasks[group.bucket].length">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium uppercase tracking-wide"
                        :class="group.tone"
                        :aria-expanded="open[group.bucket]"
                        @click="open[group.bucket] = !open[group.bucket]"
                    >
                        <ChevronRight class="size-3.5 transition" :class="{ 'rotate-90': open[group.bucket] }" />
                        {{ group.label }}
                        <span class="font-normal text-muted-foreground">{{ tasks[group.bucket].length }}</span>
                    </button>
                    <ul v-if="open[group.bucket]" class="pb-1 text-sm">
                        <li v-for="task in tasks[group.bucket]" :key="task.id">
                            <Link
                                :href="route('lists.show', { list: task.list.id, task: task.id })"
                                class="flex items-center gap-3 px-3 py-2 transition hover:bg-muted/50"
                            >
                                <span
                                    class="size-2 shrink-0 rounded-full"
                                    :style="{ backgroundColor: task.status.color }"
                                    :title="task.status.name"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">{{ task.title }}</p>
                                    <p class="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
                                        <span class="size-1.5 shrink-0 rounded-full" :style="{ backgroundColor: task.project.color }" />
                                        <span class="truncate">{{ task.project.name }} · {{ task.list.name }}</span>
                                    </p>
                                </div>
                                <span
                                    v-if="dates(task)"
                                    class="inline-flex shrink-0 items-center gap-1 text-xs"
                                    :class="group.bucket === 'overdue' ? 'font-medium text-red-600' : 'text-muted-foreground'"
                                >
                                    <CalendarDays class="size-3.5" />
                                    {{ dates(task) }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </template>
        </div>
        <p v-else class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
            {{ $t('No open tasks are assigned to you.') }}
        </p>
    </section>
</template>
