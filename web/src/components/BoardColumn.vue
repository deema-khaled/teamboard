<script setup lang="ts">
import BoardCard from '@/components/BoardCard.vue';
import { useBoard } from '@/stores/board';
import type { Card, Column } from '@/types';
import { MoreHorizontal, Plus, Trash2, X } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps<{ column: Column }>();
const emit = defineEmits<{ open: [card: Card]; error: [message: string] }>();

const store = useBoard();

// --- Drag & drop: vuedraggable has already moved the card in the UI. ------
function onChange(event: { added?: { element: Card; newIndex: number }; moved?: { element: Card; newIndex: number } }) {
    const change = event.added ?? event.moved;
    if (!change) return; // "removed" fires on the source column; the target handles it
    store.moveCard(change.element, props.column.id, change.newIndex).catch(() => emit('error', 'Could not move the card. The board was refreshed.'));
}

// --- Add card -------------------------------------------------------------
const adding = ref(false);
const title = ref('');
const input = ref<HTMLTextAreaElement | null>(null);

async function startAdding() {
    adding.value = true;
    await nextTick();
    input.value?.focus();
}

async function addCard() {
    const value = title.value.trim();
    if (!value) return;
    title.value = '';
    await store.addCard(props.column.id, value);
    input.value?.focus();
}

// --- Rename / delete column ----------------------------------------------
const menu = ref(false);
const renaming = ref(false);
const name = ref(props.column.name);

async function rename() {
    renaming.value = false;
    if (name.value.trim() && name.value !== props.column.name) await store.renameColumn(props.column, name.value.trim());
    else name.value = props.column.name;
}

async function remove() {
    menu.value = false;
    if (confirm(`Delete the list "${props.column.name}" and its ${props.column.cards.length} card(s)?`)) await store.deleteColumn(props.column);
}
</script>

<template>
    <section class="flex max-h-full w-72 shrink-0 flex-col rounded-xl bg-slate-100/95 shadow-sm">
        <header class="column-handle flex cursor-grab items-center gap-2 px-3 pb-1 pt-3 active:cursor-grabbing">
            <input
                v-if="renaming"
                v-model="name"
                class="input py-1 font-semibold"
                maxlength="60"
                @keydown.enter="rename"
                @keydown.esc="(renaming = false), (name = column.name)"
                @blur="rename"
            />
            <h2 v-else class="flex-1 truncate text-sm font-semibold" @dblclick="renaming = true">{{ column.name }}</h2>
            <span class="rounded-full bg-slate-200 px-2 text-xs font-medium text-slate-600">{{ column.cards.length }}</span>
            <div class="relative">
                <button type="button" class="btn-ghost p-1" :aria-label="`${column.name} options`" @click="menu = !menu"><MoreHorizontal class="h-4 w-4" /></button>
                <div v-if="menu" class="absolute right-0 z-20 mt-1 w-40 rounded-lg bg-white py-1 text-sm shadow-xl ring-1 ring-slate-200" @mouseleave="menu = false">
                    <button type="button" class="block w-full px-3 py-1.5 text-left hover:bg-slate-50" @click="(renaming = true), (menu = false)">Rename</button>
                    <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-red-600 hover:bg-red-50" @click="remove">
                        <Trash2 class="h-3.5 w-3.5" /> Delete list
                    </button>
                </div>
            </div>
        </header>

        <draggable
            :list="column.cards"
            item-key="id"
            group="cards"
            ghost-class="card-ghost"
            drag-class="card-drag"
            :animation="180"
            class="flex min-h-10 flex-1 flex-col gap-2 overflow-y-auto px-3 py-2"
            @change="onChange"
        >
            <template #item="{ element }">
                <BoardCard :card="element" @open="emit('open', element)" />
            </template>
        </draggable>

        <div class="px-3 pb-3">
            <form v-if="adding" @submit.prevent="addCard">
                <textarea
                    ref="input"
                    v-model="title"
                    rows="2"
                    class="input resize-none"
                    placeholder="Enter a title for this card…"
                    maxlength="200"
                    @keydown.enter.prevent="addCard"
                    @keydown.esc="adding = false"
                />
                <div class="mt-2 flex items-center gap-1">
                    <button type="submit" class="btn-primary py-1.5">Add card</button>
                    <button type="button" class="btn-ghost p-1.5" aria-label="Cancel" @click="adding = false"><X class="h-4 w-4" /></button>
                </div>
            </form>
            <button v-else type="button" class="btn-ghost w-full justify-start px-2 py-1.5 font-medium" @click="startAdding">
                <Plus class="h-4 w-4" /> Add a card
            </button>
        </div>
    </section>
</template>
