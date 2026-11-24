<script setup lang="ts">
import AuthCard from '@/components/AuthCard.vue';
import { validationErrors } from '@/lib/api';
import { useAuth } from '@/stores/auth';
import { LoaderCircle } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';

const auth = useAuth();
const router = useRouter();
const route = useRoute();

const form = reactive({ email: '', password: '' });
const errors = ref<Record<string, string>>({});
const loading = ref(false);

async function submit() {
    loading.value = true;
    errors.value = {};
    try {
        await auth.login(form.email, form.password);
        router.push((route.query.redirect as string) || '/boards');
    } catch (e) {
        errors.value = validationErrors(e);
    } finally {
        loading.value = false;
    }
}

const demo = (email: string) => Object.assign(form, { email, password: 'password' });
</script>

<template>
    <AuthCard title="Welcome back" subtitle="Log in to your boards">
        <form class="space-y-4" @submit.prevent="submit">
            <label class="block text-sm font-medium">
                Email
                <input v-model="form.email" type="email" required autocomplete="email" class="input mt-1" />
            </label>
            <label class="block text-sm font-medium">
                Password
                <input v-model="form.password" type="password" required autocomplete="current-password" class="input mt-1" />
            </label>
            <p v-if="errors.email || errors.general" class="text-sm text-red-600">{{ errors.email ?? errors.general }}</p>
            <button type="submit" class="btn-primary w-full" :disabled="loading">
                <LoaderCircle v-if="loading" class="h-4 w-4 animate-spin" /> Log in
            </button>
        </form>

        <div class="mt-6 rounded-lg bg-slate-50 p-3 text-xs text-slate-600 ring-1 ring-slate-200">
            <p class="font-semibold">Demo accounts (password: password)</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <button v-for="e in ['sara@teamboard.test', 'omar@teamboard.test']" :key="e" type="button" class="rounded bg-white px-2 py-1 ring-1 ring-slate-200 hover:ring-indigo-400" @click="demo(e)">
                    {{ e }}
                </button>
            </div>
            <p class="mt-2">Tip: log in as each in two windows to watch changes sync live.</p>
        </div>

        <p class="mt-6 text-center text-sm text-slate-500">
            No account? <RouterLink :to="{ path: '/register', query: route.query }" class="font-semibold text-indigo-600 hover:underline">Create one</RouterLink>
        </p>
    </AuthCard>
</template>
