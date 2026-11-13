<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useBranding } from '@/lib/branding';

const branding = useBranding();
</script>

<template>
    <img v-if="branding.logoUrl" :src="branding.logoUrl" alt="" class="size-8 shrink-0 rounded-md object-contain" />
    <div v-else class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none">{{ branding.name }}</span>
    </div>
</template>
