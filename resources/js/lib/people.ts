import type { Person } from '@/types';
import { computed, type ComputedRef, inject, type InjectionKey, provide } from 'vue';

const PEOPLE: InjectionKey<ComputedRef<Map<number, Person>>> = Symbol('people');

/** Make the people of a list available to its rows, cards and dialogs. */
export function providePeople(people: () => Person[]) {
    provide(
        PEOPLE,
        computed(() => new Map(people().map((person) => [person.id, person]))),
    );
}

/** The people tasks can be assigned to, by id. */
export function usePeople(): ComputedRef<Map<number, Person>> {
    return inject(
        PEOPLE,
        computed(() => new Map()),
    );
}
