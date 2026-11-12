<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    /** Set when someone else is signed in on this device. */
    otherAccount?: string;
}>();
</script>

<template>
    <AuthBase
        :title="otherAccount ? $t('This invitation is for someone else') : $t('This invitation is no longer valid')"
        :description="
            otherAccount
                ? $t('You are signed in as :email. Log out to accept the invitation with the address it was sent to.', { email: otherAccount })
                : $t('It may have expired, been used already or been withdrawn. Ask the person who invited you for a new one.')
        "
    >
        <Head :title="$t('Invitation')" />

        <div class="flex flex-col items-center gap-4">
            <Button v-if="otherAccount" as-child class="w-full">
                <Link :href="route('logout')" method="post" as="button">{{ $t('Log out') }}</Link>
            </Button>
            <TextLink :href="route('login')" class="text-sm underline underline-offset-4">{{ $t('Go to login') }}</TextLink>
        </div>
    </AuthBase>
</template>
