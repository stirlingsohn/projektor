<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { useBranding } from '@/lib/branding';
import { t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { TransitionRoot } from '@headlessui/vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Trash2, Upload } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps<{
    companyName: string | null;
    defaultName: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: t('Branding'), href: '/settings/branding' }];

const branding = useBranding();
const form = useForm<{ company_name: string; logo: File | null }>({ company_name: props.companyName ?? '', logo: null });

const fileInput = ref<HTMLInputElement | null>(null);
const preview = ref<string | null>(null);
const shownLogo = computed(() => preview.value ?? branding.value.logoUrl);

const pickLogo = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.logo = file;
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = file ? URL.createObjectURL(file) : null;
};
onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value));

const submit = () =>
    form.post(route('branding.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.logo = null;
            preview.value = null;
            if (fileInput.value) fileInput.value.value = '';
        },
    });

const removeLogo = () => router.delete(route('branding.logo.destroy'), { preserveScroll: true });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="$t('Branding')" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall :title="$t('Branding')" :description="$t('Your company name and logo, shown in the app, the browser tab and emails')" />

                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="company_name">{{ $t('Company name') }}</Label>
                        <Input id="company_name" v-model="form.company_name" maxlength="60" :placeholder="defaultName" />
                        <InputError :message="form.errors.company_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ $t('Logo') }}</Label>
                        <div class="flex items-center gap-4">
                            <div
                                class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-sidebar-border/70 bg-muted/40"
                            >
                                <img v-if="shownLogo" :src="shownLogo" alt="" class="size-full object-contain p-1" />
                                <AppLogoIcon v-else class="size-8 text-muted-foreground" />
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" variant="outline" size="sm" @click="fileInput?.click()">
                                    <Upload class="size-4" />
                                    {{ branding.logoUrl || preview ? $t('Replace logo') : $t('Upload logo') }}
                                </Button>
                                <Button
                                    v-if="branding.logoUrl && !preview"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-red-600 hover:text-red-600"
                                    @click="removeLogo"
                                >
                                    <Trash2 class="size-4" />
                                    {{ $t('Remove') }}
                                </Button>
                            </div>
                            <input ref="fileInput" type="file" accept=".png,.jpg,.jpeg,.webp,.svg" class="hidden" @change="pickLogo" />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ $t('PNG, JPG, WebP or SVG, up to 2 MB. A square logo also works as the browser icon.') }}
                        </p>
                        <InputError :message="form.errors.logo" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">{{ $t('Save') }}</Button>
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
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
