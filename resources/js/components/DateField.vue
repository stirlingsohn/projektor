<script setup lang="ts">
import { Calendar, X } from 'lucide-vue-next';
import { useTemplateRef } from 'vue';

// Attributes like `aria-label` belong on the input itself.
defineOptions({ inheritAttrs: false });

defineProps<{
    id?: string;
    min?: string;
    max?: string;
    disabled?: boolean;
}>();

/** A `YYYY-MM-DD` date, or an empty string when unset. */
const model = defineModel<string>({ required: true });

const emit = defineEmits<{
    /** The value was changed by the user, by picking, typing or clearing. */
    change: [];
}>();

const input = useTemplateRef<HTMLInputElement>('input');

const openPicker = () => {
    try {
        input.value?.showPicker();
    } catch {
        input.value?.focus();
    }
};

// While a year is typed, browsers already report "0002" or "0020" as a
// complete date. Only pass on dates with a plausible year.
const onChange = () => {
    if (!model.value || Number(model.value.slice(0, 4)) >= 1900) emit('change');
};

const clear = () => {
    model.value = '';
    emit('change');
};
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="absolute inset-y-0 left-0 flex w-9 items-center justify-center text-muted-foreground hover:text-foreground disabled:pointer-events-none"
            tabindex="-1"
            :disabled="disabled"
            :aria-label="$t('Pick date')"
            @click="openPicker"
        >
            <Calendar class="size-4" />
        </button>
        <input
            :id="id"
            ref="input"
            v-bind="$attrs"
            v-model="model"
            type="date"
            :min="min"
            :max="max"
            :disabled="disabled"
            class="date-field flex h-9 w-full rounded-md border border-input bg-transparent pl-9 pr-8 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
            :class="{ 'text-muted-foreground': !model }"
            @change="onChange"
        />
        <button
            v-if="model && !disabled"
            type="button"
            class="absolute inset-y-0 right-0 flex w-8 items-center justify-center text-muted-foreground hover:text-foreground"
            :aria-label="$t('Clear date')"
            @click="clear"
        >
            <X class="size-3.5" />
        </button>
    </div>
</template>

<style scoped>
/* The calendar button on the left replaces the browser's own one. */
.date-field::-webkit-calendar-picker-indicator {
    display: none;
}
</style>
