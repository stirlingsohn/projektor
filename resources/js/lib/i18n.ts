import { usePage } from '@inertiajs/vue3';

/**
 * Translate a key using the JSON translations shared by HandleInertiaRequests,
 * replacing `:name` placeholders like Laravel's `__()` helper.
 */
export function t(key: string, replacements: Record<string, string | number> = {}): string {
    const translations = (usePage().props.translations ?? {}) as Record<string, string>;

    return Object.entries(replacements).reduce((text, [name, value]) => text.replaceAll(`:${name}`, String(value)), translations[key] ?? key);
}
