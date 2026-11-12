import { t } from '@/lib/i18n';
import type { SharedData, Task } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { useNow } from '@vueuse/core';
import { computed } from 'vue';

/** A duration like "45 Min." or "1:30 Std.", rounded to minutes. */
export function formatDuration(seconds: number): string {
    const minutes = Math.round(seconds / 60);
    if (seconds > 0 && minutes === 0) return t('< 1 min');
    if (minutes < 60) return t(':minutes min', { minutes: String(minutes) });

    return t(':hours h', { hours: `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}` });
}

/** A running clock like "0:05:09". */
export function formatClock(seconds: number): string {
    const [h, m, s] = [Math.floor(seconds / 3600), Math.floor(seconds / 60) % 60, Math.floor(seconds % 60)];

    return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

/**
 * Minutes from what someone typed: "1:30", "1.5", "1,5h", "1h 30m", "90m"
 * or "45 min". A bare number means hours. `null` if it makes no sense.
 */
export function parseDuration(text: string): number | null {
    const value = text.trim().toLowerCase().replace(',', '.');
    if (!value) return null;

    let minutes: number | null = null;
    const clock = value.match(/^(\d+):([0-5]?\d)$/);
    const units = value.match(/^(?:(\d+(?:\.\d+)?)\s*(?:h|std\.?|stunden?))?\s*(?:(\d+)\s*(?:m|min\.?|minuten?))?$/);
    if (clock) {
        minutes = Number(clock[1]) * 60 + Number(clock[2]);
    } else if (/^\d+(\.\d+)?$/.test(value)) {
        minutes = Number(value) * 60;
    } else if (units && (units[1] || units[2])) {
        minutes = Number(units[1] ?? 0) * 60 + Number(units[2] ?? 0);
    }

    return minutes !== null && minutes >= 1 && minutes <= 1440 ? Math.round(minutes) : null;
}

const background = { preserveScroll: true, preserveState: true } as const;
const now = useNow({ interval: 1000 });

/** The user's running timer, shared by the header and the task details. */
export function useTimer() {
    const page = usePage<SharedData>();
    const timer = computed(() => page.props.timer);
    const elapsed = computed(() => (timer.value ? Math.max(0, (now.value.getTime() - Date.parse(timer.value.started_at)) / 1000) : 0));

    return {
        timer,
        elapsed,
        isRunningOn: (task: Task) => timer.value?.task_id === task.id,
        start: (task: Task) => router.post(route('timer.start', task.id), {}, background),
        stop: () => router.delete(route('timer.stop'), background),
    };
}
