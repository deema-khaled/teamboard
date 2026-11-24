<script setup lang="ts">
defineProps<{ title: string; subtitle: string }>();
</script>

<template>
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="flex items-center justify-center px-6 py-12">
            <div class="w-full max-w-sm">
                <div class="mb-8 flex items-center gap-2 text-lg font-bold">
                    <img src="/logo.svg" alt="" class="h-8 w-8" /> TeamBoard
                </div>
                <h1 class="text-2xl font-bold tracking-tight">{{ title }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>
                <div class="mt-8"><slot /></div>
            </div>
        </div>

        <!-- Illustration: a mini board -->
        <div class="relative hidden overflow-hidden bg-gradient-to-br from-indigo-600 via-indigo-500 to-violet-600 lg:flex lg:items-center lg:justify-center">
            <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-white/10 blur-3xl" />
            <div class="relative flex gap-4" aria-hidden="true">
                <div v-for="(col, i) in ['To do', 'In progress', 'Done']" :key="col" class="w-48 rounded-xl bg-white/15 p-3 backdrop-blur" :class="i === 1 ? 'mt-10' : ''">
                    <p class="mb-3 text-sm font-semibold text-white">{{ col }}</p>
                    <div v-for="n in 3 - i" :key="n" class="mb-2 rounded-lg bg-white p-3 shadow-lg">
                        <div class="mb-2 h-1.5 w-10 rounded-full" :class="['bg-amber-400', 'bg-sky-500', 'bg-emerald-500'][(n + i) % 3]" />
                        <div class="h-2 w-28 rounded bg-slate-200" />
                        <div class="mt-1.5 h-2 w-20 rounded bg-slate-100" />
                    </div>
                </div>
            </div>
            <p class="absolute bottom-10 left-0 right-0 text-center text-lg font-medium text-indigo-100">Plan together. See every change live.</p>
        </div>
    </div>
</template>
