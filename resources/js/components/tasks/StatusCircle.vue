<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { Status } from '@/types';
import { Check, ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * The circle in front of a task: it shows the status and ticks the task off
 * with one click. The other statuses are behind the small arrow next to it,
 * or a right-click on the circle.
 */
const props = defineProps<{
    status: Status;
    statuses: Status[];
    disabled?: boolean;
}>();

defineEmits<{
    toggle: [];
    change: [statusId: number];
}>();

const menuOpen = ref(false);

/** Statuses between the first one and done, such as "In progress". */
const inBetween = computed(() => !props.status.is_done && props.status.id !== props.statuses[0]?.id);

const fill = computed(() => {
    if (props.status.is_done) return props.status.color;
    // Half full, like a pie, for anything that is underway.
    if (inBetween.value) return `conic-gradient(${props.status.color} 0 50%, transparent 0)`;

    return undefined;
});

const openMenu = () => {
    if (!props.disabled) menuOpen.value = true;
};
</script>

<template>
    <span class="relative mr-1 flex shrink-0 items-center" @click.stop>
        <button
            type="button"
            class="group/check flex size-[18px] items-center justify-center rounded-full border-2 transition disabled:cursor-default"
            :style="{ borderColor: status.color, background: fill }"
            :aria-label="status.is_done ? $t('Mark as open') : $t('Mark as done')"
            :aria-pressed="status.is_done"
            :title="disabled ? status.name : undefined"
            :disabled="disabled"
            @click="$emit('toggle')"
            @contextmenu.prevent="openMenu"
        >
            <Check
                class="size-3"
                :class="status.is_done ? 'text-white' : inBetween ? 'opacity-0' : 'opacity-0 group-hover/check:opacity-60'"
                :style="status.is_done ? undefined : { color: status.color }"
            />
        </button>

        <DropdownMenu v-if="!disabled" v-model:open="menuOpen">
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="absolute left-[19px] flex h-[18px] w-3 items-center justify-center rounded text-muted-foreground opacity-0 hover:text-foreground focus-visible:opacity-100 group-hover:opacity-100 data-[state=open]:opacity-100"
                    :aria-label="$t('Change status')"
                >
                    <ChevronDown class="size-3" />
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
    </span>
</template>
