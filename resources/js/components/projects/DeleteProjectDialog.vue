<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { projectToDelete } from '@/lib/projects';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const processing = ref(false);

const close = () => (projectToDelete.value = null);

const destroy = () => {
    if (!projectToDelete.value) return;

    router.delete(route('projects.destroy', projectToDelete.value.id), {
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: close,
    });
};
</script>

<template>
    <Dialog :open="projectToDelete !== null" @update:open="(open) => !open && close()">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ $t('Delete project?') }}</DialogTitle>
                <DialogDescription>
                    {{ $t('":name" and all of its subprojects will be permanently deleted.', { name: projectToDelete?.name ?? '' }) }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button variant="secondary" @click="close">{{ $t('Cancel') }}</Button>
                <Button variant="destructive" :disabled="processing" @click="destroy">{{ $t('Delete') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
