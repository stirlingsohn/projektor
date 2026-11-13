<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    digests: { digest_daily: boolean; digest_weekly: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: t('Email settings'), href: '/settings/emails' }];

const form = useForm({ ...props.digests });
// Save right away; there is nothing else on the page to wait for.
const save = () => form.put(route('digests.update'), { preserveScroll: true });

const OPTIONS = [
    {
        field: 'digest_daily' as const,
        label: t('Daily'),
        description: t('Every morning at 7:00: your tasks for the day, plus overdue ones.'),
    },
    {
        field: 'digest_weekly' as const,
        label: t('Weekly'),
        description: t('Monday morning at 7:00: your tasks for the week, plus overdue ones.'),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="$t('Email settings')" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="$t('Task emails')"
                    :description="$t('Get the tasks assigned to you by email. Days without tasks send nothing.')"
                />

                <div class="space-y-4">
                    <Label
                        v-for="option in OPTIONS"
                        :key="option.field"
                        :for="option.field"
                        class="flex cursor-pointer items-start gap-3 font-normal"
                    >
                        <Checkbox :id="option.field" v-model:checked="form[option.field]" class="mt-0.5" @update:checked="save" />
                        <span class="space-y-1">
                            <span class="block font-medium">{{ option.label }}</span>
                            <span class="block text-sm text-muted-foreground">{{ option.description }}</span>
                        </span>
                    </Label>
                </div>

                <TransitionRoot
                    :show="form.recentlySuccessful"
                    enter="transition ease-in-out"
                    enter-from="opacity-0"
                    leave="transition ease-in-out"
                    leave-to="opacity-0"
                >
                    <p class="text-sm text-neutral-600">{{ $t('Saved.') }}</p>
                </TransitionRoot>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
