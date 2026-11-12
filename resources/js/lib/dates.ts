import { t } from '@/lib/i18n';

const MS_PER_DAY = 24 * 60 * 60 * 1000;

/** Today's date as `YYYY-MM-DD` in the viewer's time zone. */
export function today(): string {
    const now = new Date();

    return new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);
}

/** A `YYYY-MM-DD` date as a whole day number, for date arithmetic. */
export const toDay = (date: string): number => Math.round(Date.parse(`${date}T00:00:00Z`) / MS_PER_DAY);

/** A whole day number as a `YYYY-MM-DD` date. */
export const fromDay = (day: number): string => new Date(day * MS_PER_DAY).toISOString().slice(0, 10);

/** Whole days from today until the `YYYY-MM-DD` date (negative when past). */
export function daysFromToday(date: string): number {
    return toDay(date) - toDay(today());
}

/** A short, friendly label like "Heute", "Morgen" or "12. Nov.". */
export function formatDay(date: string): string {
    const relative: Record<number, string> = { [-1]: t('Yesterday'), 0: t('Today'), 1: t('Tomorrow') };
    const label = relative[daysFromToday(date)];
    if (label) return label;

    const sameYear = date.slice(0, 4) === today().slice(0, 4);

    return new Date(`${date}T00:00:00`).toLocaleDateString(document.documentElement.lang || 'de-CH', {
        day: 'numeric',
        month: 'short',
        year: sameYear ? undefined : 'numeric',
    });
}
