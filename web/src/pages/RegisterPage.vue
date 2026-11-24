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

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' });
const errors = ref<Record<string, string>>({});
const loading = ref(false);

async function submit() {
    loading.value = true;
    errors.value = {};
    try {
        await auth.register(form);
        router.push((route.query.redirect as string) || '/boards');
    } catch (e) {
        errors.value = validationErrors(e);
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <AuthCard title="Create your account" subtitle="Free for small teams">
        <form class="space-y-4" @submit.prevent="submit">
            <label class="block text-sm font-medium">
                Full name
                <input v-model="form.name" required autocomplete="name" class="input mt-1" />
                <span v-if="errors.name" class="text-sm text-red-600">{{ errors.name }}</span>
            </label>
            <label class="block text-sm font-medium">
                Email
                <input v-model="form.email" type="email" required autocomplete="email" class="input mt-1" />
                <span v-if="errors.email" class="text-sm text-red-600">{{ errors.email }}</span>
            </label>
            <label class="block text-sm font-medium">
                Password
                <input v-model="form.password" type="password" required autocomplete="new-password" class="input mt-1" />
                <span v-if="errors.password" class="text-sm text-red-600">{{ errors.password }}</span>
            </label>
            <label class="block text-sm font-medium">
                Confirm password
                <input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="input mt-1" />
            </label>
            <p v-if="errors.general" class="text-sm text-red-600">{{ errors.general }}</p>
            <button type="submit" class="btn-primary w-full" :disabled="loading">
                <LoaderCircle v-if="loading" class="h-4 w-4 animate-spin" /> Create account
            </button>
        </form>
        <p class="mt-6 text-center text-sm text-slate-500">
            Already have an account? <RouterLink :to="{ path: '/login', query: route.query }" class="font-semibold text-indigo-600 hover:underline">Log in</RouterLink>
        </p>
    </AuthCard>
</template>
