<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { Status } from '@/types';
import { Check } from 'lucide-vue-next';

defineProps<{
    status: Status;
    statuses: Status[];
    disabled?: boolean;
}>();

defineEmits<{
    change: [statusId: number];
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :disabled="disabled" as-child>
            <button
                type="button"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium transition hover:bg-muted disabled:pointer-events-none"
                :style="{ borderColor: `${status.color}66`, color: status.color }"
                @click.stop
            >
                <span class="size-1.5 rounded-full" :style="{ backgroundColor: status.color }" />
                {{ status.name }}
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem v-for="option in statuses" :key="option.id" @select="$emit('change', option.id)">
                <span class="mr-2 size-2 rounded-full" :style="{ backgroundColor: option.color }" />
                {{ option.name }}
                <Check v-if="option.id === status.id" class="ml-auto size-4" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
