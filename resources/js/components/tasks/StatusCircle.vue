<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { Status } from '@/types';
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * The circle in front of a task: it shows the status, and a click on it
 * opens the list of statuses to pick from.
 */
const props = defineProps<{
    status: Status;
    statuses: Status[];
    disabled?: boolean;
}>();

defineEmits<{
    change: [statusId: number];
}>();

/** Statuses between the first one and done, such as "In progress". */
const inBetween = computed(() => !props.status.is_done && props.status.id !== props.statuses[0]?.id);

const fill = computed(() => {
    if (props.status.is_done) return props.status.color;
    // Half full, like a pie, for anything that is underway.
    if (inBetween.value) return `conic-gradient(${props.status.color} 0 50%, transparent 0)`;

    return undefined;
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :disabled="disabled" as-child>
            <!-- The padding makes the target big enough for a finger. -->
            <button
                type="button"
                class="-m-2 flex shrink-0 rounded-full p-2 disabled:cursor-default"
                :aria-label="$t('Status: :status', { status: status.name })"
                :title="status.name"
                @click.stop
            >
                <span
                    class="flex size-[18px] items-center justify-center rounded-full border-2 transition"
                    :style="{ borderColor: status.color, background: fill }"
                >
                    <Check v-if="status.is_done" class="size-3 text-white" />
                </span>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start">
            <DropdownMenuItem v-for="option in statuses" :key="option.id" @select="$emit('change', option.id)">
                <span class="mr-2 size-2 rounded-full" :style="{ backgroundColor: option.color }" />
                {{ option.name }}
                <Check v-if="option.id === status.id" class="ml-auto size-4" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
