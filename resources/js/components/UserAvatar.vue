<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    name: string | null;
    avatar?: string | null;
}>();

const initials = computed(() =>
    (props.name ?? '?')
        .split(/\s+/)
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase(),
);
</script>

<template>
    <!-- Size it with a class such as `size-8`; the initials scale with `text-*`. -->
    <img v-if="avatar" :src="avatar" :alt="name ?? ''" class="shrink-0 rounded-full object-cover" />
    <span v-else class="flex shrink-0 items-center justify-center rounded-full bg-muted font-medium" :title="name ?? undefined">{{ initials }}</span>
</template>
