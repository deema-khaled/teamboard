<script setup lang="ts">
import type { PublicUser } from '@/types';

withDefaults(defineProps<{ user: PublicUser; size?: 'sm' | 'md'; ring?: boolean }>(), { size: 'md' });

// Full class names so Tailwind keeps them in the build.
const colors: Record<string, string> = {
    indigo: 'bg-indigo-500',
    sky: 'bg-sky-500',
    emerald: 'bg-emerald-500',
    amber: 'bg-amber-500',
    rose: 'bg-rose-500',
    violet: 'bg-violet-500',
    teal: 'bg-teal-500',
    orange: 'bg-orange-500',
};
</script>

<template>
    <span
        class="inline-flex shrink-0 select-none items-center justify-center rounded-full font-semibold text-white"
        :class="[colors[user.color] ?? 'bg-slate-500', size === 'sm' ? 'h-6 w-6 text-[10px]' : 'h-8 w-8 text-xs', ring ? 'ring-2 ring-white' : '']"
        :title="user.name"
    >
        {{ user.initials }}
    </span>
</template>
