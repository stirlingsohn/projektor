<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { t } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{
    token: string;
    email: string;
    inviter: string | null;
    target: string;
    level: string;
}>();

const form = useForm({ name: '', password: '', password_confirmation: '' });

const submit = () => form.post(route('invitations.store', props.token), { onFinish: () => form.reset('password', 'password_confirmation') });

const description = [
    props.inviter
        ? t(':name invited you to ":target".', { name: props.inviter, target: props.target })
        : t('You were invited to ":target".', { target: props.target }),
    t('Your access: :level', { level: props.level }),
].join(' ');
</script>

<template>
    <AuthBase :title="$t('Accept invitation')" :description="description">
        <Head :title="$t('Accept invitation')" />

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">{{ $t('Email address') }}</Label>
                    <Input id="email" type="email" :model-value="email" readonly disabled />
                </div>

                <div class="grid gap-2">
                    <Label for="name">{{ $t('Name') }}</Label>
                    <Input id="name" v-model="form.name" type="text" required autofocus autocomplete="name" :placeholder="$t('Full name')" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ $t('Password') }}</Label>
                    <Input id="password" v-model="form.password" type="password" required autocomplete="new-password" :placeholder="$t('Password')" />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">{{ $t('Confirm password') }}</Label>
                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        :placeholder="$t('Confirm password')"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-2 w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ $t('Create account and open') }}
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                {{ $t('Already have an account?') }}
                <TextLink :href="route('login')" class="underline underline-offset-4">{{ $t('Log in') }}</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
