<script setup lang="ts">
import TaskQuickAdd from '@/components/tasks/TaskQuickAdd.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { formatDay, fromDay, toDay, today } from '@/lib/dates';
import { useSortableList } from '@/lib/sortable';
import { moveTask, updateTask } from '@/lib/tasks';
import type { Status, Task } from '@/types';
import { router } from '@inertiajs/vue3';
import { useElementSize, useScroll } from '@vueuse/core';
import { CalendarX, Pencil, ZoomIn, ZoomOut } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, useTemplateRef, watch } from 'vue';

const props = defineProps<{
    listId: number;
    statuses: Status[];
    tasks: Task[];
    canEdit: boolean;
}>();

const emit = defineEmits<{
    open: [taskId: number];
}>();

const ROW_HEIGHT = 40;
const HEADER_HEIGHT = 72;
const DAY_WIDTHS = [16, 32, 48];
/** Days shown before the earliest and after the latest date. */
const PADDING_BEFORE = 7;
const PADDING_AFTER = 28;
const MIN_DAYS = 63;
/** 1 January 1970, day 0, was a Thursday. */
const weekday = (day: number) => (day + 4) % 7;
const isWeekend = (day: number) => weekday(day) === 0 || weekday(day) === 6;
/** "Mo", "Di", … in the page language; just the first letter when days are narrow. */
const weekdayFormat = new Intl.DateTimeFormat(document.documentElement.lang || 'de-CH', { weekday: 'short', timeZone: 'UTC' });
const weekdayLabel = (day: number, narrow: boolean) => {
    const label = weekdayFormat.format(new Date(fromDay(day))).replace('.', '');
    return narrow ? label.charAt(0) : label;
};

const zoom = ref(1);
const dayWidth = computed(() => DAY_WIDTHS[zoom.value]);
const todayDay = toDay(today());

const statusById = computed(() => new Map(props.statuses.map((status) => [status.id, status])));

// Dates being dragged, shown until the saved data arrives.
const drafts = ref(new Map<number, { start: number; end: number }>());
watch(
    () => props.tasks,
    () => drafts.value.clear(),
);

const span = (task: Task) => {
    const draft = drafts.value.get(task.id);
    if (draft) return draft;
    const start = task.start_date ?? task.due_date;
    const end = task.due_date ?? task.start_date;

    return start && end ? { start: toDay(start), end: toDay(end) } : null;
};

const range = computed(() => {
    const days = props.tasks.flatMap((task) => [task.start_date, task.due_date]).flatMap((date) => (date ? [toDay(date)] : []));
    const first = Math.min(todayDay, ...days) - PADDING_BEFORE;
    const start = first - ((weekday(first) + 6) % 7); // back to Monday
    const end = Math.max(todayDay, ...days) + PADDING_AFTER;

    return { start, length: Math.max(end - start + 1, MIN_DAYS) };
});
const dayList = computed(() => Array.from({ length: range.value.length }, (_, index) => range.value.start + index));
const x = (day: number) => (day - range.value.start) * dayWidth.value;

const months = computed(() => {
    const segments: { label: string; days: number }[] = [];
    for (const day of dayList.value) {
        const date = new Date(`${fromDay(day)}T00:00:00`);
        if (day === range.value.start || date.getDate() === 1) {
            segments.push({ label: date.toLocaleDateString(document.documentElement.lang || 'de-CH', { month: 'long', year: 'numeric' }), days: 0 });
        }
        segments[segments.length - 1].days++;
    }
    return segments;
});

// A copy of the tasks, so rows can be reordered by dragging their names.
const ordered = ref<Task[]>([]);
watch(
    () => props.tasks,
    (tasks) => (ordered.value = [...tasks]),
    { immediate: true },
);
useSortableList(useTemplateRef<HTMLElement>('names'), ordered, {
    disabled: !props.canEdit,
    // Pointer-based dragging, which also works on touch screens.
    forceFallback: true,
    onEnd: ({ oldIndex, newIndex }: { oldIndex: number; newIndex: number }) =>
        oldIndex !== newIndex &&
        nextTick(() => {
            const tasks = ordered.value;
            moveTask(tasks[newIndex], { after: tasks[newIndex - 1], before: tasks[newIndex + 1] });
        }),
});

const rows = computed(() =>
    ordered.value.map((task, index) => {
        const taskSpan = span(task);
        const status = statusById.value.get(task.status_id) ?? props.statuses[0];

        return {
            task,
            status,
            top: index * ROW_HEIGHT,
            bar: taskSpan && { left: x(taskSpan.start), width: (taskSpan.end - taskSpan.start + 1) * dayWidth.value, ...taskSpan },
        };
    }),
);

const ARROW_GAP = 8;
const arrows = computed(() => {
    const rowById = new Map(rows.value.map((row) => [row.task.id, row]));

    return rows.value.flatMap((row) =>
        row.task.depends_on.flatMap((dependsOnId) => {
            const before = rowById.get(dependsOnId);
            if (!before?.bar || !row.bar) return [];

            const [x1, y1] = [before.bar.left + before.bar.width, before.top + ROW_HEIGHT / 2];
            const [x2, y2] = [row.bar.left, row.top + ROW_HEIGHT / 2];
            const turn = x1 + ARROW_GAP;
            const path =
                x2 - ARROW_GAP >= turn
                    ? `M${x1},${y1} H${turn} V${y2} H${x2}`
                    : `M${x1},${y1} H${turn} V${(y1 + y2) / 2} H${x2 - ARROW_GAP} V${y2} H${x2}`;

            return [{ key: `${dependsOnId}-${row.task.id}`, path, conflict: row.bar.start <= before.bar.end }];
        }),
    );
});

type Drag = {
    task: Task;
    mode: 'move' | 'start' | 'end' | 'link' | 'create';
    originX: number;
    origin: { start: number; end: number };
    moved: boolean;
};
const drag = ref<Drag | null>(null);
const linkLine = ref<{ x1: number; y1: number; x2: number; y2: number } | null>(null);
const body = useTemplateRef<HTMLElement>('body');

const bodyPoint = (event: PointerEvent) => {
    const rect = body.value!.getBoundingClientRect();
    return { x: event.clientX - rect.left, y: event.clientY - rect.top };
};

const dayAt = (event: PointerEvent) => range.value.start + Math.floor(bodyPoint(event).x / dayWidth.value);

const startDrag = (event: PointerEvent, task: Task, mode: Drag['mode']) => {
    const taskSpan = mode === 'create' ? { start: dayAt(event), end: dayAt(event) } : span(task);
    if (!props.canEdit || !taskSpan || event.button !== 0) {
        if (mode === 'move') emit('open', task.id);
        return;
    }
    event.preventDefault();
    drag.value = { task, mode, originX: event.clientX, origin: taskSpan, moved: false };
    if (mode === 'create') drafts.value.set(task.id, taskSpan);
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', onPointerUp, { once: true });
    window.addEventListener('pointercancel', cancelDrag, { once: true });
};

const onPointerMove = (event: PointerEvent) => {
    const current = drag.value;
    if (!current) return;

    if (current.mode === 'link') {
        const row = rows.value.find((candidate) => candidate.task.id === current.task.id);
        if (!row?.bar) return;
        const point = bodyPoint(event);
        linkLine.value = { x1: row.bar.left + row.bar.width, y1: row.top + ROW_HEIGHT / 2, x2: point.x, y2: point.y };
        return;
    }

    if (current.mode === 'create') {
        const [day, anchor] = [dayAt(event), current.origin.start];
        drafts.value.set(current.task.id, { start: Math.min(anchor, day), end: Math.max(anchor, day) });
        return;
    }

    const delta = Math.round((event.clientX - current.originX) / dayWidth.value);
    const { start, end } = current.origin;
    current.moved ||= delta !== 0;
    drafts.value.set(
        current.task.id,
        {
            move: { start: start + delta, end: end + delta },
            start: { start: Math.min(start + delta, end), end },
            end: { start, end: Math.max(end + delta, start) },
        }[current.mode],
    );
};

/** Abandon a drag the browser took over, e.g. for scrolling on a touch screen. */
const cancelDrag = () => {
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', onPointerUp);
    if (drag.value) drafts.value.delete(drag.value.task.id);
    drag.value = null;
    linkLine.value = null;
};

const onPointerUp = (event: PointerEvent) => {
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointercancel', cancelDrag);
    const current = drag.value;
    drag.value = null;
    linkLine.value = null;
    if (!current) return;

    if (current.mode === 'link') {
        const targetId = Number(
            (document.elementFromPoint(event.clientX, event.clientY) as HTMLElement | null)?.closest<HTMLElement>('[data-gantt-task]')?.dataset
                .ganttTask,
        );
        if (targetId && targetId !== current.task.id) {
            router.post(
                route('tasks.dependencies.store', targetId),
                { depends_on_id: current.task.id },
                { preserveScroll: true, preserveState: true },
            );
        }
        return;
    }

    if (!current.moved && current.mode !== 'create') {
        if (current.mode === 'move') openMenu(current.task, bodyPoint(event).x);
        return;
    }

    const draft = drafts.value.get(current.task.id);
    if (draft) updateTask(current.task, { start_date: fromDay(draft.start), due_date: fromDay(draft.end) });
};

// Clicking a bar opens a small menu below it, which works the same on touch screens.
const menuOpen = ref(false);
const menu = ref<{ task: Task; left: number; top: number } | null>(null);
const openMenu = (task: Task, left?: number) => {
    const row = rows.value.find((candidate) => candidate.task.id === task.id);
    if (!row?.bar) return;
    menu.value = { task, left: left ?? row.bar.left, top: row.top + ROW_HEIGHT - 6 };
    menuOpen.value = true;
};
const clearDates = (task: Task) => {
    drafts.value.delete(task.id);
    updateTask(task, { start_date: null, due_date: null });
};

/** Clicking or dragging across the empty row of an undated task schedules it. */
const schedule = (event: PointerEvent, task: Task) => {
    if (props.canEdit && !span(task)) startDrag(event, task, 'create');
};

const scroller = useTemplateRef<HTMLElement>('scroller');
const { x: scrollLeft } = useScroll(scroller);
const { width: viewWidth } = useElementSize(scroller);

/** Bars scrolled out of view leave a small marker at the edge they went past. */
const MARKER_WIDTH = 6;
const markerSide = (bar: { left: number; width: number }) => {
    if (bar.left + bar.width < scrollLeft.value) return 'left';
    if (bar.left > scrollLeft.value + viewWidth.value) return 'right';

    return null;
};
const scrollToBar = (bar: { left: number }) => scroller.value?.scrollTo({ left: bar.left - 3 * dayWidth.value, behavior: 'smooth' });

const scrollToToday = () => nextTick(() => scroller.value?.scrollTo({ left: x(todayDay) - 3 * dayWidth.value }));
onMounted(scrollToToday);
watch(zoom, scrollToToday);
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-end gap-1">
            <Button variant="ghost" size="icon" class="size-8" :disabled="zoom === 0" :aria-label="$t('Zoom out')" @click="zoom--">
                <ZoomOut class="size-4" />
            </Button>
            <Button variant="ghost" size="icon" class="size-8" :disabled="zoom === DAY_WIDTHS.length - 1" :aria-label="$t('Zoom in')" @click="zoom++">
                <ZoomIn class="size-4" />
            </Button>
            <Button variant="outline" size="sm" @click="scrollToToday">{{ $t('Today') }}</Button>
        </div>

        <div class="flex overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border" :class="{ 'select-none': drag }">
            <!-- Task names -->
            <div class="w-36 shrink-0 border-r border-sidebar-border/70 dark:border-sidebar-border sm:w-56">
                <div
                    class="flex items-end border-b border-sidebar-border/70 px-3 pb-2 text-xs font-medium text-muted-foreground dark:border-sidebar-border"
                    :style="{ height: `${HEADER_HEIGHT}px` }"
                >
                    {{ $t('Task') }}
                </div>
                <div ref="names">
                    <button
                        v-for="row in rows"
                        :key="row.task.id"
                        type="button"
                        class="drag-handle flex w-full items-center gap-2 border-b border-sidebar-border/40 bg-background px-3 text-left text-sm hover:bg-muted/40"
                        :class="{ 'cursor-grab active:cursor-grabbing': canEdit }"
                        :style="{ height: `${ROW_HEIGHT}px` }"
                        @click="emit('open', row.task.id)"
                    >
                        <span class="size-2 shrink-0 rounded-full" :style="{ backgroundColor: row.status.color }" />
                        <span class="truncate" :class="{ 'text-muted-foreground line-through': row.status.is_done }">{{ row.task.title }}</span>
                    </button>
                </div>
            </div>

            <!-- Timeline -->
            <div class="relative min-w-0 flex-1">
                <div ref="scroller" class="scrollbar-visible overflow-x-auto">
                    <div class="relative" :style="{ width: `${range.length * dayWidth}px` }">
                        <div
                            class="sticky top-0 border-b border-sidebar-border/70 bg-background dark:border-sidebar-border"
                            :style="{ height: `${HEADER_HEIGHT}px` }"
                        >
                            <div class="flex h-7 text-xs font-medium">
                                <div
                                    v-for="(month, index) in months"
                                    :key="index"
                                    class="shrink-0 border-l border-sidebar-border/40 pt-1.5 first:border-l-0"
                                    :style="{ width: `${month.days * dayWidth}px` }"
                                >
                                    <!-- Sticky so the month stays readable while scrolled into it. --><span
                                        class="sticky left-0 inline-block whitespace-nowrap px-2"
                                        >{{ month.label }}</span
                                    >
                                </div>
                            </div>
                            <div class="flex h-11 text-[11px] text-muted-foreground">
                                <div
                                    v-for="day in dayList"
                                    :key="day"
                                    class="flex shrink-0 flex-col items-center justify-center gap-0.5"
                                    :style="{ width: `${dayWidth}px` }"
                                >
                                    <span
                                        class="text-[10px] leading-none"
                                        :class="{ 'opacity-50': isWeekend(day), 'font-semibold text-red-500': day === todayDay }"
                                        >{{ weekdayLabel(day, dayWidth < 32) }}</span
                                    >
                                    <span
                                        class="flex size-6 items-center justify-center rounded-full leading-none"
                                        :class="{ 'bg-red-500 font-semibold text-white': day === todayDay }"
                                    >
                                        <template v-if="dayWidth >= 32 || weekday(day) === 1 || day === todayDay">{{
                                            fromDay(day).slice(8).replace(/^0/, '')
                                        }}</template>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div ref="body" class="relative" :style="{ height: `${rows.length * ROW_HEIGHT}px` }">
                            <!-- Weekends and today -->
                            <template v-for="day in dayList" :key="day">
                                <div
                                    v-if="isWeekend(day)"
                                    class="absolute inset-y-0 bg-muted/50"
                                    :style="{ left: `${x(day)}px`, width: `${dayWidth}px` }"
                                />
                            </template>
                            <div class="absolute inset-y-0 w-px bg-red-500/70" :style="{ left: `${x(todayDay) + dayWidth / 2}px` }" />

                            <!-- Rows -->
                            <div
                                v-for="row in rows"
                                :key="row.task.id"
                                :data-gantt-task="row.task.id"
                                class="absolute inset-x-0 border-b border-sidebar-border/40"
                                :class="{ 'cursor-crosshair touch-none hover:bg-muted/40': canEdit && !row.bar }"
                                :title="canEdit && !row.bar ? $t('Click or drag to schedule') : undefined"
                                :style="{ top: `${row.top}px`, height: `${ROW_HEIGHT}px` }"
                                @pointerdown.self="schedule($event, row.task)"
                            >
                                <div
                                    v-if="row.bar"
                                    class="group absolute top-2 flex h-6 touch-none items-center rounded-md text-xs font-medium text-white shadow-sm"
                                    :class="[{ 'opacity-50': row.status.is_done }, canEdit ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer']"
                                    :style="{ left: `${row.bar.left}px`, width: `${row.bar.width}px`, backgroundColor: row.status.color }"
                                    role="button"
                                    tabindex="0"
                                    :aria-label="`${row.task.title}: ${formatDay(fromDay(row.bar.start))} – ${formatDay(fromDay(row.bar.end))}`"
                                    @pointerdown="startDrag($event, row.task, 'move')"
                                    @keydown.enter="canEdit ? openMenu(row.task) : emit('open', row.task.id)"
                                >
                                    <template v-if="canEdit">
                                        <span
                                            class="absolute inset-y-0 left-0 w-2 cursor-ew-resize rounded-l-md hover:bg-black/20"
                                            @pointerdown.stop="startDrag($event, row.task, 'start')"
                                        />
                                        <span
                                            class="absolute inset-y-0 right-0 w-2 cursor-ew-resize rounded-r-md hover:bg-black/20"
                                            @pointerdown.stop="startDrag($event, row.task, 'end')"
                                        />
                                        <span
                                            class="absolute -right-4 top-1/2 size-3 -translate-y-1/2 cursor-crosshair rounded-full border-2 bg-background opacity-0 transition group-hover:opacity-100"
                                            :style="{ borderColor: row.status.color }"
                                            :title="$t('Drag onto a task that has to wait for this one')"
                                            @pointerdown.stop="startDrag($event, row.task, 'link')"
                                        />
                                    </template>
                                    <span v-if="row.bar.width >= 48" class="pointer-events-none truncate px-2">{{ row.task.title }}</span>
                                </div>
                            </div>

                            <DropdownMenu v-model:open="menuOpen">
                                <DropdownMenuTrigger as-child>
                                    <span
                                        class="pointer-events-none absolute size-0"
                                        aria-hidden="true"
                                        :style="menu ? { left: `${menu.left}px`, top: `${menu.top}px` } : {}"
                                    />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent v-if="menu" align="start">
                                    <DropdownMenuItem @select="emit('open', menu.task.id)">
                                        <Pencil class="mr-2 size-4" />
                                        {{ $t('Edit') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem class="text-red-600 focus:text-red-600" @select="clearDates(menu.task)">
                                        <CalendarX class="mr-2 size-4" />
                                        {{ $t('Remove dates') }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <!-- Dependencies -->
                            <svg class="pointer-events-none absolute inset-0 size-full overflow-visible">
                                <defs>
                                    <marker id="gantt-arrow" viewBox="0 0 6 6" refX="5" refY="3" markerWidth="6" markerHeight="6" orient="auto">
                                        <path d="M0,0 L6,3 L0,6 z" class="fill-muted-foreground" />
                                    </marker>
                                    <marker
                                        id="gantt-arrow-conflict"
                                        viewBox="0 0 6 6"
                                        refX="5"
                                        refY="3"
                                        markerWidth="6"
                                        markerHeight="6"
                                        orient="auto"
                                    >
                                        <path d="M0,0 L6,3 L0,6 z" class="fill-red-500" />
                                    </marker>
                                </defs>
                                <path
                                    v-for="arrow in arrows"
                                    :key="arrow.key"
                                    :d="arrow.path"
                                    fill="none"
                                    stroke-width="1.5"
                                    :class="arrow.conflict ? 'stroke-red-500' : 'stroke-muted-foreground'"
                                    :marker-end="arrow.conflict ? 'url(#gantt-arrow-conflict)' : 'url(#gantt-arrow)'"
                                />
                                <line
                                    v-if="linkLine"
                                    v-bind="linkLine"
                                    class="stroke-foreground"
                                    stroke-width="1.5"
                                    stroke-dasharray="4 3"
                                    marker-end="url(#gantt-arrow)"
                                />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Outside the scrolling content, so markers stay put while scrolling. -->
                <template v-for="row in rows" :key="row.task.id">
                    <button
                        v-if="row.bar && markerSide(row.bar)"
                        type="button"
                        class="absolute h-6 opacity-70 transition-opacity hover:opacity-100"
                        :class="markerSide(row.bar) === 'left' ? 'left-0 rounded-r-md' : 'right-0 rounded-l-md'"
                        :style="{ top: `${HEADER_HEIGHT + row.top + 8}px`, width: `${MARKER_WIDTH}px`, backgroundColor: row.status.color }"
                        :aria-label="$t('Show :task', { task: row.task.title })"
                        :title="`${row.task.title}: ${formatDay(fromDay(row.bar.start))} – ${formatDay(fromDay(row.bar.end))}`"
                        @click="scrollToBar(row.bar)"
                    />
                </template>
            </div>
        </div>

        <TaskQuickAdd v-if="canEdit" :list-id="listId" :placeholder="$t('Add task, press Enter to save')" class="px-3" />
        <p v-if="canEdit && tasks.length" class="text-xs text-muted-foreground">
            {{
                $t(
                    'Drag bars to move them, their edges to change the dates, and the dot on the right onto another task to link them. Click a bar to edit it or remove its dates. Drag across an empty row to schedule a task, and drag task names to reorder them.',
                )
            }}
        </p>
    </div>
</template>
