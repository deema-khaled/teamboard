<script setup lang="ts">
import { api } from '@/lib/api';
import { useAuth } from '@/stores/auth';
import { LoaderCircle, MailOpen } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';

const props = defineProps<{ token: string }>();
const auth = useAuth();
const router = useRouter();

const invitation = ref<{ board: { name: string }; invited_by: string; email: string } | null>(null);
const error = ref('');
const accepting = ref(false);

onMounted(async () => {
    await auth.loadUser();
    try {
        invitation.value = (await api.get(`/invitations/${props.token}`)).data.data;
    } catch {
        error.value = 'This invitation is invalid or has expired.';
    }
});

async function accept() {
    accepting.value = true;
    try {
        const { data } = await api.post(`/invitations/${props.token}/accept`);
        router.push(`/boards/${data.data.board_id}`);
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? 'Could not accept the invitation.';
        accepting.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-xl ring-1 ring-slate-200">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-100"><MailOpen class="h-7 w-7 text-indigo-600" /></span>

            <p v-if="error" class="mt-6 text-red-600">{{ error }}</p>

            <template v-else-if="invitation">
                <h1 class="mt-5 text-xl font-bold">Join “{{ invitation.board.name }}”</h1>
                <p class="mt-2 text-slate-500">{{ invitation.invited_by }} invited <span class="font-medium text-slate-700">{{ invitation.email }}</span> to collaborate.</p>

                <button v-if="auth.isLoggedIn" type="button" class="btn-primary mt-6 w-full" :disabled="accepting" @click="accept">
                    <LoaderCircle v-if="accepting" class="h-4 w-4 animate-spin" /> Accept and open the board
                </button>
                <div v-else class="mt-6 grid gap-2">
                    <RouterLink :to="{ path: '/register', query: { redirect: `/invitations/${token}` } }" class="btn-primary">Create an account</RouterLink>
                    <RouterLink :to="{ path: '/login', query: { redirect: `/invitations/${token}` } }" class="btn-ghost">I already have one</RouterLink>
                </div>
            </template>

            <LoaderCircle v-else class="mx-auto mt-6 h-6 w-6 animate-spin text-slate-400" />
        </div>
    </div>
</template>
