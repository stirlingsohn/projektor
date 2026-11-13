<script setup lang="ts">
import UserAvatar from '@/components/UserAvatar.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { usePeople } from '@/lib/people';
import { Check, ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    id?: string;
    disabled?: boolean;
}>();

/** The ids of the assigned people. */
const model = defineModel<number[]>({ required: true });

const emit = defineEmits<{
    change: [ids: number[]];
}>();

const people = usePeople();
const options = computed(() => [...people.value.values()]);
const selected = computed(() => options.value.filter((person) => model.value.includes(person.id)));

const toggle = (id: number) => {
    model.value = model.value.includes(id) ? model.value.filter((other) => other !== id) : [...model.value, id];
    emit('change', model.value);
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :disabled="props.disabled" as-child>
            <button
                :id="id"
                type="button"
                class="flex min-h-9 w-full items-center gap-2 rounded-md border border-input bg-transparent px-3 py-1.5 text-left text-sm shadow-sm transition hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span v-if="selected.length" class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                    <span v-for="person in selected" :key="person.id" class="flex items-center gap-1.5">
                        <UserAvatar :name="person.name" :avatar="person.avatar" class="size-5 text-[9px]" />
                        <span class="truncate">{{ person.name }}</span>
                    </span>
                </span>
                <span v-else class="truncate text-muted-foreground">{{ $t('Nobody') }}</span>
                <ChevronDown class="ml-auto size-4 shrink-0 text-muted-foreground" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="max-h-72 w-[var(--radix-dropdown-menu-trigger-width)] overflow-y-auto">
            <!-- Keep the menu open so several people can be picked in a row. -->
            <DropdownMenuItem v-for="person in options" :key="person.id" class="gap-2" @select.prevent="toggle(person.id)">
                <UserAvatar :name="person.name" :avatar="person.avatar" class="size-6 text-[10px]" />
                <span class="truncate">{{ person.name }}</span>
                <Check v-if="model.includes(person.id)" class="ml-auto size-4 shrink-0" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
