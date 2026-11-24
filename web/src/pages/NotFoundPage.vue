<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center gap-4 text-center">
        <p class="text-6xl font-bold text-indigo-600">404</p>
        <p class="text-lg text-slate-600">This page doesn't exist.</p>
        <RouterLink to="/boards" class="btn-primary">Go to my boards</RouterLink>
    </div>
</template>
