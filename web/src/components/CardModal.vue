<script setup lang="ts">
import UserAvatar from '@/components/UserAvatar.vue';
import { api } from '@/lib/api';
import { labelClasses, labels } from '@/lib/colors';
import { useAuth } from '@/stores/auth';
import { useBoard } from '@/stores/board';
import type { Card, Comment } from '@/types';
import { AlignLeft, Calendar, Check, MessageSquare, Tag, Trash2, User, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ cardId: number }>();
const emit = defineEmits<{ close: [] }>();

const store = useBoard();
const auth = useAuth();

const card = computed(() => store.findCard(props.cardId));
const column = computed(() => store.board?.columns?.find((c) => c.id === card.value?.column_id));
const comments = ref<Comment[]>([]);
const title = ref(card.value?.title ?? '');
const description = ref(card.value?.description ?? '');
const comment = ref('');
const saving = ref(false);

onMounted(async () => {
    const { data } = await api.get(`/cards/${props.cardId}`);
    comments.value = data.data.comments;
    description.value = data.data.description ?? '';
});

async function save(changes: Partial<Card> & { assignee_id?: number | null }) {
    if (!card.value) return;
    saving.value = true;
    try {
        await store.updateCard(card.value, changes);
    } finally {
        saving.value = false;
    }
}

async function saveTitle() {
    if (title.value.trim() && title.value !== card.value?.title) await save({ title: title.value.trim() });
}

async function addComment() {
    if (!card.value || !comment.value.trim()) return;
    comments.value.push(await store.addComment(card.value, comment.value.trim()));
    comment.value = '';
}

async function deleteComment(c: Comment) {
    comments.value = comments.value.filter((x) => x.id !== c.id);
    await api.delete(`/comments/${c.id}`);
}

async function remove() {
    if (card.value && confirm(`Delete "${card.value.title}"?`)) {
        await store.deleteCard(card.value);
        emit('close');
    }
}

const ago = (iso: string) => new Date(iso).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 sm:p-10" @click.self="emit('close')" @keydown.esc="emit('close')">
        <article v-if="card" class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
            <header class="flex items-start gap-3 border-b p-5">
                <div class="flex-1">
                    <input v-model="title" class="w-full rounded-md px-1 text-xl font-bold outline-none focus:ring-2 focus:ring-indigo-500/30" maxlength="200" @blur="saveTitle" @keydown.enter="($event.target as HTMLInputElement).blur()" />
                    <p class="px-1 text-sm text-slate-500">in list <span class="font-medium">{{ column?.name }}</span></p>
                </div>
                <button type="button" class="btn-ghost p-1.5" aria-label="Close" @click="emit('close')"><X class="h-5 w-5" /></button>
            </header>

            <div class="grid gap-6 p-5 sm:grid-cols-[1fr_12rem]">
                <div class="space-y-6">
                    <section>
                        <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold"><AlignLeft class="h-4 w-4" /> Description</h3>
                        <textarea
                            v-model="description"
                            rows="4"
                            class="input"
                            placeholder="Add more detail…"
                            @blur="description !== (card.description ?? '') && save({ description })"
                        />
                    </section>

                    <section>
                        <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold"><MessageSquare class="h-4 w-4" /> Comments</h3>
                        <form class="flex gap-2" @submit.prevent="addComment">
                            <UserAvatar v-if="auth.user" :user="auth.user" />
                            <input v-model="comment" class="input" placeholder="Write a comment…" maxlength="5000" />
                            <button type="submit" class="btn-primary" :disabled="!comment.trim()">Send</button>
                        </form>
                        <ul class="mt-4 space-y-4">
                            <li v-for="c in comments.slice().reverse()" :key="c.id" class="flex gap-3">
                                <UserAvatar :user="c.user" />
                                <div class="flex-1">
                                    <p class="text-sm"><span class="font-semibold">{{ c.user.name }}</span> <span class="text-xs text-slate-400">{{ ago(c.created_at) }}</span></p>
                                    <p class="mt-1 whitespace-pre-line rounded-lg bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-100">{{ c.body }}</p>
                                    <button v-if="c.user.id === auth.user?.id" type="button" class="mt-1 text-xs text-slate-400 hover:text-red-600" @click="deleteComment(c)">Delete</button>
                                </div>
                            </li>
                        </ul>
                    </section>
                </div>

                <aside class="space-y-4 text-sm">
                    <div>
                        <p class="mb-1 flex items-center gap-1.5 font-semibold text-slate-500"><User class="h-3.5 w-3.5" /> Assignee</p>
                        <select class="input" :value="card.assignee?.id ?? ''" @change="save({ assignee_id: Number(($event.target as HTMLSelectElement).value) || null })">
                            <option value="">Unassigned</option>
                            <option v-for="m in store.board?.members" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>
                    <div>
                        <p class="mb-1 flex items-center gap-1.5 font-semibold text-slate-500"><Calendar class="h-3.5 w-3.5" /> Due date</p>
                        <input type="date" class="input" :value="card.due_date ?? ''" @change="save({ due_date: ($event.target as HTMLInputElement).value || null })" />
                    </div>
                    <div>
                        <p class="mb-1 flex items-center gap-1.5 font-semibold text-slate-500"><Tag class="h-3.5 w-3.5" /> Label</p>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="l in labels"
                                :key="l"
                                type="button"
                                class="flex h-7 w-9 items-center justify-center rounded-md text-white"
                                :class="labelClasses[l]"
                                :aria-label="l"
                                @click="save({ label: card.label === l ? null : (l as Card['label']) })"
                            >
                                <Check v-if="card.label === l" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn w-full bg-red-50 text-red-700 hover:bg-red-100" @click="remove"><Trash2 class="h-4 w-4" /> Delete card</button>
                    <p v-if="saving" class="text-xs text-slate-400">Saving…</p>
                </aside>
            </div>
        </article>
    </div>
</template>
