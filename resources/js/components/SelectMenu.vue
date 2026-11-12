<script setup lang="ts" generic="T extends string | number | null">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Check, ChevronDown } from 'lucide-vue-next';
import { type Component, computed } from 'vue';

export interface SelectOption<V> {
    value: V;
    label: string;
    /** Shown as a coloured dot, or as the colour of `icon`. */
    color?: string;
    icon?: Component;
    /** Indentation level, for trees. */
    depth?: number;
}

const props = defineProps<{
    options: SelectOption<T>[];
    placeholder?: string;
    id?: string;
    disabled?: boolean;
}>();

/** The selected value; leave unset for menus that only trigger an action. */
const model = defineModel<T>();

const emit = defineEmits<{
    /** An option was picked by the user. */
    change: [value: T];
}>();

const selected = computed(() => props.options.find((option) => option.value === model.value));

const pick = (value: T) => {
    model.value = value;
    emit('change', value);
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :disabled="disabled" as-child>
            <button
                :id="id"
                type="button"
                class="flex h-9 w-full items-center gap-2 rounded-md border border-input bg-transparent px-3 text-left text-sm shadow-sm transition hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
            >
                <template v-if="selected">
                    <component :is="selected.icon" v-if="selected.icon" class="size-4 shrink-0" :style="{ color: selected.color }" />
                    <span v-else-if="selected.color" class="size-2 shrink-0 rounded-full" :style="{ backgroundColor: selected.color }" />
                    <span class="truncate">{{ selected.label }}</span>
                </template>
                <span v-else class="truncate text-muted-foreground">{{ placeholder }}</span>
                <ChevronDown class="ml-auto size-4 shrink-0 text-muted-foreground" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="max-h-72 w-[var(--radix-dropdown-menu-trigger-width)] overflow-y-auto">
            <DropdownMenuItem
                v-for="option in options"
                :key="String(option.value)"
                :style="option.depth ? { paddingLeft: `${0.5 + option.depth}rem` } : undefined"
                @select="pick(option.value)"
            >
                <component :is="option.icon" v-if="option.icon" class="mr-2 size-4 shrink-0" :style="{ color: option.color }" />
                <span v-else-if="option.color" class="mr-2 size-2 shrink-0 rounded-full" :style="{ backgroundColor: option.color }" />
                <span class="truncate">{{ option.label }}</span>
                <Check v-if="model !== undefined && option.value === model" class="ml-auto size-4 shrink-0" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
