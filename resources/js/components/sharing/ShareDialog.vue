<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SelectMenu from '@/components/SelectMenu.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import UserAvatar from '@/components/UserAvatar.vue';
import { formatDay } from '@/lib/dates';
import { t } from '@/lib/i18n';
import type { AccessLevel, Sharing } from '@/types';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Mail, RotateCw, UserPlus, X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    /** What is being shared. */
    target: { type: 'project' | 'list'; id: number; name: string };
    sharing: Sharing;
}>();

const open = defineModel<boolean>('open', { required: true });

const LEVELS = computed(() => [
    { value: 'view' as AccessLevel, label: t('Can view') },
    { value: 'comment' as AccessLevel, label: t('Can comment') },
    { value: 'edit' as AccessLevel, label: t('Can edit') },
]);
const levelLabel = (level: AccessLevel) => LEVELS.value.find((option) => option.value === level)?.label ?? level;

const background = { preserveScroll: true, preserveState: true } as const;

const form = useForm({ email: '', level: 'comment' as AccessLevel });
const invite = () =>
    form.post(route(props.target.type === 'project' ? 'projects.members.store' : 'lists.members.store', props.target.id), {
        ...background,
        onSuccess: () => form.reset('email'),
    });

const changeLevel = (membershipId: number, level: AccessLevel) => router.patch(route('memberships.update', membershipId), { level }, background);
const remove = (membershipId: number) => router.delete(route('memberships.destroy', membershipId), background);
const resend = (invitationId: number) =>
    router.post(route('invitations.resend', invitationId), {}, { ...background, onError: (errors) => form.setError('email', errors.email) });
const revoke = (invitationId: number) => router.delete(route('invitations.destroy', invitationId), background);

const isEmpty = computed(() => !props.sharing.members.length && !props.sharing.invitations.length && !props.sharing.inherited.length);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90dvh] w-[calc(100%-2rem)] gap-5 overflow-y-auto rounded-lg sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ $t('Share ":name"', { name: target.name }) }}</DialogTitle>
                <DialogDescription>
                    {{
                        target.type === 'project'
                            ? $t('Clients get access to this project with all its subprojects, lists, documents and files.')
                            : $t('Clients get access to this list only.')
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-1" @submit.prevent="invite">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Input
                        v-model="form.email"
                        type="email"
                        required
                        class="flex-1"
                        :placeholder="$t('Email address')"
                        :aria-label="$t('Email address')"
                    />
                    <div class="sm:w-48">
                        <SelectMenu v-model="form.level" :options="LEVELS" />
                    </div>
                    <Button type="submit" :disabled="form.processing">
                        <UserPlus class="size-4" />
                        {{ $t('Invite') }}
                    </Button>
                </div>
                <InputError :message="form.errors.email || form.errors.level" />
                <p class="text-xs text-muted-foreground">{{ $t('People without an account get an email with a link to sign up.') }}</p>
            </form>

            <ul v-if="!isEmpty" class="divide-y divide-sidebar-border/50 rounded-lg border border-sidebar-border/70 text-sm">
                <li v-for="member in sharing.members" :key="`member-${member.id}`" class="flex items-center gap-3 px-3 py-2">
                    <UserAvatar :name="member.name" :avatar="member.avatar" class="size-8 text-xs" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ member.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ member.email }}</p>
                    </div>
                    <div class="w-44 shrink-0 sm:w-48">
                        <SelectMenu :model-value="member.level" :options="LEVELS" @change="(level) => changeLevel(member.id, level)" />
                    </div>
                    <Button variant="ghost" size="icon" class="size-8 shrink-0" :aria-label="$t('Remove access')" @click="remove(member.id)">
                        <X class="size-4" />
                    </Button>
                </li>

                <li v-for="invitation in sharing.invitations" :key="`invitation-${invitation.id}`" class="flex items-center gap-3 px-3 py-2">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full border border-dashed text-muted-foreground">
                        <Mail class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ invitation.email }}</p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ $t('Invited, valid until :date', { date: formatDay(invitation.expires_at.slice(0, 10)) }) }} ·
                            {{ levelLabel(invitation.level) }}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8 shrink-0"
                        :title="$t('Send again')"
                        :aria-label="$t('Send again')"
                        @click="resend(invitation.id)"
                    >
                        <RotateCw class="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8 shrink-0"
                        :aria-label="$t('Withdraw invitation')"
                        @click="revoke(invitation.id)"
                    >
                        <X class="size-4" />
                    </Button>
                </li>

                <li v-for="member in sharing.inherited" :key="`inherited-${member.id}`" class="flex items-center gap-3 px-3 py-2">
                    <UserAvatar :name="member.name" :avatar="member.avatar" class="size-8 text-xs" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ member.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ levelLabel(member.level) }} ·
                            <Link :href="route('projects.show', member.source_id)" class="underline underline-offset-2" @click="open = false">{{
                                $t('through ":project"', { project: member.source })
                            }}</Link>
                        </p>
                    </div>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">{{ $t('Not shared with anyone yet.') }}</p>
        </DialogContent>
    </Dialog>
</template>
