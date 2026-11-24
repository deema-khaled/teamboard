<script setup lang="ts">
import BoardColumn from '@/components/BoardColumn.vue';
import CardModal from '@/components/CardModal.vue';
import TopBar from '@/components/TopBar.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { api, validationErrors } from '@/lib/api';
import { boardGradients } from '@/lib/colors';
import { useAuth } from '@/stores/auth';
import { useBoard } from '@/stores/board';
import type { Activity, Card } from '@/types';
import { History, LoaderCircle, Plus, UserPlus, X, Zap } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import draggable from 'vuedraggable';

const props = defineProps<{ id: string }>();

const store = useBoard();
const auth = useAuth();
const route = useRoute();
const router = useRouter();

const loading = ref(true);
const notFound = ref(false);
const toast = ref<string | null>(null);
let toastTimer: ReturnType<typeof setTimeout> | undefined;

function flash(message: string) {
    toast.value = message;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (toast.value = null), 3500);
}

watch(
    () => props.id,
    async (id) => {
        loading.value = true;
        notFound.value = false;
        try {
            await store.open(Number(id));
        } catch {
            notFound.value = true;
        } finally {
            loading.value = false;
        }
    },
    { immediate: true },
);
onBeforeUnmount(() => store.close());

// Live: show who changed what.
watch(
    () => store.lastRemote,
    (change) => change && flash(`${change.actor?.name ?? 'Someone'} ${change.text}`),
);
watch(
    () => store.deleted,
    (gone) => gone && router.push('/boards'),
);

const onlineIds = computed(() => new Set(store.online.map((u) => u.id)));
const others = computed(() => store.online.filter((u) => u.id !== auth.user?.id));

// --- Card dialog (deep-linkable with ?card=ID) -----------------------------
const openCardId = computed(() => (route.query.card ? Number(route.query.card) : null));
const openCard = (card: Card) => router.replace({ query: { ...route.query, card: card.id } });
const closeCard = () => router.replace({ query: { ...route.query, card: undefined } });

// --- Columns ----------------------------------------------------------------
const newList = ref('');
const addingList = ref(false);
async function addList() {
    if (!newList.value.trim()) return;
    await store.addColumn(newList.value.trim());
    newList.value = '';
}

// --- Invite -------------------------------------------------------------------
const inviting = ref(false);
const inviteEmail = ref('');
const inviteState = ref<{ sending?: boolean; sent?: string; error?: string }>({});
async function invite() {
    inviteState.value = { sending: true };
    try {
        await api.post(`/boards/${props.id}/invitations`, { email: inviteEmail.value });
        inviteState.value = { sent: inviteEmail.value };
        inviteEmail.value = '';
    } catch (e) {
        inviteState.value = { error: Object.values(validationErrors(e))[0] };
    }
}

// --- Activity -----------------------------------------------------------------
const showActivity = ref(false);
const activity = ref<Activity[]>([]);
async function toggleActivity() {
    showActivity.value = !showActivity.value;
    if (showActivity.value) activity.value = (await api.get(`/boards/${props.id}/activity`)).data.data;
}
const ago = (iso: string) => {
    const s = (Date.now() - new Date(iso).getTime()) / 1000;
    return s < 60 ? 'just now' : s < 3600 ? `${Math.floor(s / 60)} min ago` : s < 86400 ? `${Math.floor(s / 3600)} h ago` : `${Math.floor(s / 86400)} d ago`;
};
</script>

<template>
    <div class="flex h-screen flex-col bg-gradient-to-br" :class="store.board ? boardGradients[store.board.color] : 'from-slate-500 to-slate-700'">
        <TopBar dark />

        <div v-if="loading" class="flex flex-1 items-center justify-center text-white"><LoaderCircle class="h-8 w-8 animate-spin" /></div>

        <div v-else-if="notFound" class="flex flex-1 flex-col items-center justify-center gap-3 text-white">
            <p class="text-xl font-semibold">This board doesn't exist or you don't have access.</p>
            <RouterLink to="/boards" class="btn bg-white text-slate-900">Back to my boards</RouterLink>
        </div>

        <template v-else-if="store.board">
            <!-- Board header -->
            <div class="flex flex-wrap items-center gap-3 px-4 py-3 text-white">
                <h1 class="text-xl font-bold">{{ store.board.name }}</h1>

                <span v-if="others.length" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/25 px-2.5 py-1 text-xs font-medium ring-1 ring-emerald-300/40">
                    <span class="relative flex h-2 w-2"><span class="absolute h-full w-full animate-ping rounded-full bg-emerald-300 opacity-75" /><span class="relative h-2 w-2 rounded-full bg-emerald-300" /></span>
                    {{ others.length }} online
                </span>

                <div class="ml-auto flex items-center gap-3">
                    <div class="flex -space-x-2">
                        <span v-for="m in store.board.members" :key="m.id" class="relative">
                            <UserAvatar :user="m" ring />
                            <span v-if="onlineIds.has(m.id)" class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full bg-emerald-400 ring-2 ring-white" :title="`${m.name} is online`" />
                        </span>
                    </div>
                    <button v-if="store.board.is_owner" type="button" class="btn bg-white/20 text-white hover:bg-white/30" @click="inviting = true">
                        <UserPlus class="h-4 w-4" /> Invite
                    </button>
                    <button type="button" class="btn bg-white/20 text-white hover:bg-white/30" :class="showActivity ? 'bg-white/35' : ''" @click="toggleActivity">
                        <History class="h-4 w-4" /> Activity
                    </button>
                </div>
            </div>

            <div class="flex min-h-0 flex-1">
                <!-- Columns (drag the header to reorder lists) -->
                <div class="flex min-w-0 flex-1 items-start gap-3 overflow-x-auto px-4 pb-4">
                    <draggable
                        v-model="store.board.columns"
                        item-key="id"
                        handle=".column-handle"
                        :animation="200"
                        class="flex h-full items-start gap-3"
                        @end="store.reorderColumns"
                    >
                        <template #item="{ element }">
                            <BoardColumn :column="element" class="max-h-full" @open="openCard" @error="flash" />
                        </template>
                    </draggable>

                    <div class="w-72 shrink-0">
                        <form v-if="addingList" class="rounded-xl bg-slate-100 p-3" @submit.prevent="addList">
                            <input v-model="newList" class="input" placeholder="List name" maxlength="60" autofocus @keydown.esc="addingList = false" />
                            <div class="mt-2 flex gap-1">
                                <button type="submit" class="btn-primary py-1.5">Add list</button>
                                <button type="button" class="btn-ghost p-1.5" aria-label="Cancel" @click="addingList = false"><X class="h-4 w-4" /></button>
                            </div>
                        </form>
                        <button v-else type="button" class="btn w-full justify-start bg-white/20 text-white hover:bg-white/30" @click="addingList = true">
                            <Plus class="h-4 w-4" /> Add another list
                        </button>
                    </div>
                </div>

                <!-- Activity panel -->
                <aside v-if="showActivity" class="w-80 shrink-0 overflow-y-auto border-l border-white/20 bg-white/95 p-4">
                    <h2 class="mb-4 font-semibold">Activity</h2>
                    <ul class="space-y-4">
                        <li v-for="a in activity" :key="a.id" class="flex gap-3 text-sm">
                            <UserAvatar v-if="a.user" :user="a.user" size="sm" />
                            <p>
                                <span class="font-semibold">{{ a.user?.name ?? 'Someone' }}</span> {{ a.description }}
                                <span class="block text-xs text-slate-400">{{ ago(a.created_at) }}</span>
                            </p>
                        </li>
                    </ul>
                </aside>
            </div>
        </template>

        <!-- Live change toast -->
        <Transition enter-from-class="translate-y-3 opacity-0" enter-active-class="transition" leave-active-class="transition" leave-to-class="opacity-0">
            <div v-if="toast" class="fixed bottom-5 left-1/2 z-40 flex -translate-x-1/2 items-center gap-2 rounded-full bg-slate-900 px-4 py-2.5 text-sm text-white shadow-xl">
                <Zap class="h-4 w-4 text-amber-300" /> {{ toast }}
            </div>
        </Transition>

        <CardModal v-if="openCardId && store.findCard(openCardId)" :key="openCardId" :card-id="openCardId" @close="closeCard" />

        <!-- Invite dialog -->
        <div v-if="inviting" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="inviting = false">
            <form class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" @submit.prevent="invite">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">Invite to {{ store.board?.name }}</h2>
                    <button type="button" class="btn-ghost p-1.5" aria-label="Close" @click="inviting = false"><X class="h-5 w-5" /></button>
                </div>
                <p class="mt-1 text-sm text-slate-500">They'll get an email with a link that works for 7 days.</p>
                <input v-model="inviteEmail" type="email" required class="input mt-4" placeholder="teammate@company.com" />
                <p v-if="inviteState.sent" class="mt-2 text-sm text-emerald-700">Invitation sent to {{ inviteState.sent }} ✓</p>
                <p v-if="inviteState.error" class="mt-2 text-sm text-red-600">{{ inviteState.error }}</p>
                <button type="submit" class="btn-primary mt-4 w-full" :disabled="inviteState.sending">
                    <LoaderCircle v-if="inviteState.sending" class="h-4 w-4 animate-spin" /> Send invitation
                </button>
            </form>
        </div>
    </div>
</template>
