<script setup lang="ts">
import TopBar from '@/components/TopBar.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { api, validationErrors } from '@/lib/api';
import { boardColors, boardGradients } from '@/lib/colors';
import { useAuth } from '@/stores/auth';
import type { Board } from '@/types';
import { Check, LayoutGrid, Plus, X } from 'lucide-vue-next';
import { onMounted, reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';

const auth = useAuth();
const router = useRouter();
const boards = ref<Board[]>([]);
const loading = ref(true);

const creating = ref(false);
const form = reactive({ name: '', description: '', color: 'indigo' });
const error = ref('');

onMounted(async () => {
    const { data } = await api.get('/boards');
    boards.value = data.data;
    loading.value = false;
});

async function create() {
    error.value = '';
    try {
        const { data } = await api.post('/boards', form);
        router.push(`/boards/${data.data.id}`);
    } catch (e) {
        error.value = Object.values(validationErrors(e))[0];
    }
}
</script>

<template>
    <div class="min-h-screen">
        <TopBar />

        <main class="mx-auto max-w-6xl px-4 py-10">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Hi {{ auth.user?.name.split(' ')[0] }} 👋</h1>
                    <p class="text-sm text-slate-500">Your boards</p>
                </div>
                <button type="button" class="btn-primary" @click="creating = true"><Plus class="h-4 w-4" /> New board</button>
            </div>

            <div v-if="loading" class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="n in 3" :key="n" class="h-36 animate-pulse rounded-2xl bg-slate-200" />
            </div>

            <div v-else class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <RouterLink
                    v-for="b in boards"
                    :key="b.id"
                    :to="`/boards/${b.id}`"
                    class="group relative flex h-36 flex-col overflow-hidden rounded-2xl bg-gradient-to-br p-5 text-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
                    :class="boardGradients[b.color]"
                >
                    <p class="text-lg font-bold">{{ b.name }}</p>
                    <p class="line-clamp-1 text-sm text-white/80">{{ b.description }}</p>
                    <div class="mt-auto flex items-center justify-between">
                        <div class="flex -space-x-2">
                            <UserAvatar v-for="m in b.members.slice(0, 4)" :key="m.id" :user="m" size="sm" ring />
                        </div>
                        <span class="text-xs font-medium text-white/80">{{ b.cards_count }} cards</span>
                    </div>
                </RouterLink>

                <button
                    type="button"
                    class="flex h-36 flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-300 text-slate-500 transition hover:border-indigo-400 hover:text-indigo-600"
                    @click="creating = true"
                >
                    <LayoutGrid class="h-6 w-6" /> Create a board
                </button>
            </div>
        </main>

        <!-- New board dialog -->
        <div v-if="creating" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="creating = false">
            <form class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" @submit.prevent="create">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">New board</h2>
                    <button type="button" class="btn-ghost p-1.5" aria-label="Close" @click="creating = false"><X class="h-5 w-5" /></button>
                </div>
                <div class="mt-4 h-20 rounded-xl bg-gradient-to-br p-4 font-bold text-white" :class="boardGradients[form.color]">{{ form.name || 'Board name' }}</div>
                <label class="mt-4 block text-sm font-medium">
                    Name
                    <input v-model="form.name" class="input mt-1" required maxlength="100" autofocus />
                </label>
                <label class="mt-4 block text-sm font-medium">
                    Description <span class="text-slate-400">(optional)</span>
                    <input v-model="form.description" class="input mt-1" maxlength="500" />
                </label>
                <p class="mt-4 text-sm font-medium">Colour</p>
                <div class="mt-2 flex gap-2">
                    <button
                        v-for="c in boardColors"
                        :key="c"
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br text-white"
                        :class="boardGradients[c]"
                        :aria-label="c"
                        @click="form.color = c"
                    >
                        <Check v-if="form.color === c" class="h-4 w-4" />
                    </button>
                </div>
                <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>
                <button type="submit" class="btn-primary mt-6 w-full">Create board</button>
            </form>
        </div>
    </div>
</template>
