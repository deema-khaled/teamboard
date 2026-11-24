<script setup lang="ts">
import UserAvatar from '@/components/UserAvatar.vue';
import { useAuth } from '@/stores/auth';
import { Bell, LogOut } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';

defineProps<{ dark?: boolean }>();

const auth = useAuth();
const router = useRouter();
const open = ref(false);
const menu = ref<HTMLElement | null>(null);

async function toggle() {
    open.value = !open.value;
    if (open.value) await auth.loadNotifications();
}

function go(n: { board_id: number; card_id: number }) {
    open.value = false;
    router.push({ path: `/boards/${n.board_id}`, query: { card: n.card_id } });
}

async function logout() {
    await auth.logout();
    router.push('/login');
}

const ago = (iso: string) => {
    const s = (Date.now() - new Date(iso).getTime()) / 1000;
    return s < 60 ? 'just now' : s < 3600 ? `${Math.floor(s / 60)}m ago` : s < 86400 ? `${Math.floor(s / 3600)}h ago` : `${Math.floor(s / 86400)}d ago`;
};

const close = (e: MouseEvent) => menu.value && !menu.value.contains(e.target as Node) && (open.value = false);
onMounted(() => document.addEventListener('click', close));
onBeforeUnmount(() => document.removeEventListener('click', close));
</script>

<template>
    <header class="flex h-14 shrink-0 items-center gap-4 px-4" :class="dark ? 'bg-black/20 text-white' : 'border-b border-slate-200 bg-white'">
        <RouterLink to="/boards" class="flex items-center gap-2 font-bold">
            <img src="/logo.svg" alt="" class="h-7 w-7" /> TeamBoard
        </RouterLink>
        <slot />

        <div class="ml-auto flex items-center gap-2">
            <div ref="menu" class="relative">
                <button type="button" class="relative rounded-lg p-2" :class="dark ? 'hover:bg-white/15' : 'hover:bg-slate-100'" aria-label="Notifications" @click="toggle">
                    <Bell class="h-5 w-5" />
                    <span v-if="auth.unread" class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">
                        {{ auth.unread }}
                    </span>
                </button>
                <div v-if="open" class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl bg-white text-slate-900 shadow-2xl ring-1 ring-slate-200">
                    <div class="flex items-center justify-between border-b px-4 py-3">
                        <p class="font-semibold">Notifications</p>
                        <button v-if="auth.unread" type="button" class="text-xs font-medium text-indigo-600 hover:underline" @click="auth.markAllRead">Mark all read</button>
                    </div>
                    <ul class="max-h-96 divide-y overflow-y-auto">
                        <li v-for="n in auth.notifications" :key="n.id">
                            <button type="button" class="flex w-full gap-3 px-4 py-3 text-left text-sm hover:bg-slate-50" @click="go(n)">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="n.read ? 'bg-transparent' : 'bg-indigo-500'" />
                                <span class="flex-1">
                                    {{ n.message }}
                                    <span class="mt-0.5 block text-xs text-slate-400">{{ ago(n.created_at) }}</span>
                                </span>
                            </button>
                        </li>
                        <li v-if="!auth.notifications.length" class="px-4 py-8 text-center text-sm text-slate-400">You're all caught up ✨</li>
                    </ul>
                </div>
            </div>

            <UserAvatar v-if="auth.user" :user="auth.user" />
            <button type="button" class="rounded-lg p-2" :class="dark ? 'hover:bg-white/15' : 'hover:bg-slate-100'" aria-label="Log out" title="Log out" @click="logout">
                <LogOut class="h-5 w-5" />
            </button>
        </div>
    </header>
</template>
