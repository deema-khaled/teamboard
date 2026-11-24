<script setup lang="ts">
import UserAvatar from '@/components/UserAvatar.vue';
import { labelClasses } from '@/lib/colors';
import type { Card } from '@/types';
import { AlignLeft, Calendar, MessageSquare } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ card: Card }>();
defineEmits<{ open: [] }>();

const due = computed(() => {
    if (!props.card.due_date) return null;
    const date = new Date(`${props.card.due_date}T00:00:00`);
    const days = Math.round((date.getTime() - new Date(new Date().toDateString()).getTime()) / 86_400_000);
    return {
        text: days === 0 ? 'Today' : days === 1 ? 'Tomorrow' : date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }),
        tone: days < 0 ? 'bg-red-100 text-red-700' : days <= 1 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600',
    };
});
</script>

<template>
    <button
        type="button"
        class="block w-full cursor-grab rounded-lg bg-white p-3 text-left shadow-sm ring-1 ring-slate-200 transition hover:ring-indigo-300 active:cursor-grabbing"
        @click="$emit('open')"
    >
        <span v-if="card.label" class="mb-2 block h-1.5 w-10 rounded-full" :class="labelClasses[card.label]" />
        <span class="block text-sm font-medium leading-snug">{{ card.title }}</span>

        <span v-if="due || card.description || card.comments_count || card.assignee" class="mt-2.5 flex items-center gap-2 text-xs text-slate-500">
            <span v-if="due" class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 font-medium" :class="due.tone">
                <Calendar class="h-3 w-3" /> {{ due.text }}
            </span>
            <AlignLeft v-if="card.description" class="h-3.5 w-3.5" aria-label="Has description" />
            <span v-if="card.comments_count" class="inline-flex items-center gap-0.5"><MessageSquare class="h-3.5 w-3.5" /> {{ card.comments_count }}</span>
            <UserAvatar v-if="card.assignee" :user="card.assignee" size="sm" class="ml-auto" />
        </span>
    </button>
</template>
