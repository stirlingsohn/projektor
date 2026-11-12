<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { PROJECT_COLORS } from '@/lib/projects';
import { useSortableList } from '@/lib/sortable';
import type { Status } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { GripVertical, Plus, X } from 'lucide-vue-next';
import { ref, useTemplateRef, watch } from 'vue';

const props = defineProps<{
    listId: number;
    statuses: Status[];
}>();

const open = defineModel<boolean>('open', { required: true });

type Row = { key: number; id: number | null; name: string; color: string; is_done: boolean };

let nextKey = 0;
const rows = ref<Row[]>([]);
const form = useForm<{ statuses: Omit<Row, 'key'>[] }>({ statuses: [] });

watch(open, (isOpen) => {
    if (!isOpen) return;
    rows.value = props.statuses.map((status) => ({ key: nextKey++, ...status }));
    form.clearErrors();
});

useSortableList(useTemplateRef<HTMLElement>('rowList'), rows);

const add = () =>
    rows.value.push({ key: nextKey++, id: null, name: '', color: PROJECT_COLORS[rows.value.length % PROJECT_COLORS.length], is_done: false });

const nextColor = (row: Row) => (row.color = PROJECT_COLORS[(PROJECT_COLORS.indexOf(row.color) + 1) % PROJECT_COLORS.length]);

const submit = () => {
    form.transform(() => ({ statuses: rows.value.map(({ id, name, color, is_done }) => ({ id, name, color, is_done })) })).put(
        route('lists.statuses.sync', props.listId),
        { preserveScroll: true, preserveState: true, onSuccess: () => (open.value = false) },
    );
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <form class="space-y-6" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ $t('Statuses') }}</DialogTitle>
                    <DialogDescription>
                        {{ $t('Tasks in a status marked as done count as completed. Tasks in a removed status move to the first similar status.') }}
                    </DialogDescription>
                </DialogHeader>

                <ul ref="rowList" class="space-y-2">
                    <li v-for="(row, index) in rows" :key="row.key" class="flex items-center gap-2">
                        <GripVertical class="drag-handle size-4 shrink-0 cursor-grab text-muted-foreground" />
                        <button
                            type="button"
                            class="size-5 shrink-0 rounded-full"
                            :style="{ backgroundColor: row.color }"
                            :title="$t('Change color')"
                            @click="nextColor(row)"
                        />
                        <Input v-model="row.name" required maxlength="50" class="h-8" :aria-label="$t('Name')" />
                        <label class="flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground">
                            <input v-model="row.is_done" type="checkbox" class="rounded border-input" />
                            {{ $t('Done') }}
                        </label>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8 shrink-0"
                            :aria-label="$t('Remove')"
                            @click="rows.splice(index, 1)"
                        >
                            <X class="size-4" />
                        </Button>
                    </li>
                </ul>

                <Button type="button" variant="outline" size="sm" @click="add">
                    <Plus class="size-4" />
                    {{ $t('Add status') }}
                </Button>

                <InputError :message="form.errors.statuses" />

                <DialogFooter class="gap-2">
                    <Button type="button" variant="secondary" @click="open = false">{{ $t('Cancel') }}</Button>
                    <Button type="submit" :disabled="form.processing">{{ $t('Save') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
