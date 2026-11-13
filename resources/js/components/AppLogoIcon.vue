<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <!-- The Projektor mark: a "P" made of a bar and a reel. -->
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" :class="className" v-bind="$attrs">
        <rect x="5" y="3" width="4" height="18" rx="2" fill="currentColor" />
        <path
            fill="currentColor"
            fill-rule="evenodd"
            d="M13.5 3a6.5 6.5 0 1 1 0 13a6.5 6.5 0 1 1 0-13Zm0 3.7a2.8 2.8 0 1 0 0 5.6a2.8 2.8 0 1 0 0-5.6Z"
        />
    </svg>
</template>
