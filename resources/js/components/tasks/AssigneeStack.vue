<script setup lang="ts">
import UserAvatar from '@/components/UserAvatar.vue';
import { usePeople } from '@/lib/people';
import { computed } from 'vue';

const props = defineProps<{
    ids: number[];
}>();

const MAX = 3;
const people = usePeople();
// People who lost access to the list are left out.
const assignees = computed(() => props.ids.map((id) => people.value.get(id)).filter((person) => person !== undefined));
const names = computed(() => assignees.value.map((person) => person.name).join(', '));
</script>

<template>
    <span v-if="assignees.length" class="flex shrink-0 -space-x-1.5" :title="names" :aria-label="names">
        <UserAvatar
            v-for="person in assignees.slice(0, MAX)"
            :key="person.id"
            :name="person.name"
            :avatar="person.avatar"
            class="size-6 text-[10px] ring-2 ring-background"
        />
        <span
            v-if="assignees.length > MAX"
            class="flex size-6 items-center justify-center rounded-full bg-muted text-[10px] font-medium ring-2 ring-background"
        >
            +{{ assignees.length - MAX }}
        </span>
    </span>
</template>
