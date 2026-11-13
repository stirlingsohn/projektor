import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** The company name and logo set by the owner, falling back to Projektor. */
export function useBranding() {
    const page = usePage<SharedData>();

    return computed(() => page.props.branding ?? { name: 'Projektor', logoUrl: null });
}
