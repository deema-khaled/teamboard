import { api, TOKEN_KEY } from '@/lib/api';
import { connectEcho, disconnectEcho } from '@/lib/echo';
import type { AppNotification, Me } from '@/types';
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

export const useAuth = defineStore('auth', () => {
    const user = ref<Me | null>(null);
    const notifications = ref<AppNotification[]>([]);
    const unread = ref(0);
    const isLoggedIn = computed(() => user.value !== null);

    async function loadUser(): Promise<void> {
        if (!localStorage.getItem(TOKEN_KEY) || user.value) return;
        try {
            const { data } = await api.get('/auth/me');
            user.value = data.data;
            unread.value = data.data.unread_notifications ?? 0;
            listen();
        } catch {
            localStorage.removeItem(TOKEN_KEY);
        }
    }

    async function login(email: string, password: string): Promise<void> {
        const { data } = await api.post('/auth/login', { email, password });
        start(data);
    }

    async function register(payload: { name: string; email: string; password: string; password_confirmation: string }): Promise<void> {
        const { data } = await api.post('/auth/register', payload);
        start(data);
    }

    async function logout(): Promise<void> {
        await api.post('/auth/logout').catch(() => undefined);
        localStorage.removeItem(TOKEN_KEY);
        disconnectEcho();
        user.value = null;
        notifications.value = [];
        unread.value = 0;
    }

    async function loadNotifications(): Promise<void> {
        const { data } = await api.get('/notifications');
        notifications.value = data.data;
        unread.value = data.unread;
    }

    async function markAllRead(): Promise<void> {
        await api.post('/notifications/read-all');
        notifications.value = notifications.value.map((n) => ({ ...n, read: true }));
        unread.value = 0;
    }

    function start(data: { token: string; user: Me }): void {
        localStorage.setItem(TOKEN_KEY, data.token);
        user.value = data.user;
        listen();
    }

    /** Live notifications on the user's private channel. */
    function listen(): void {
        if (!user.value) return;
        connectEcho()
            .private(`App.Models.User.${user.value.id}`)
            .notification((n: Omit<AppNotification, 'read' | 'created_at'>) => {
                notifications.value.unshift({ ...n, read: false, created_at: new Date().toISOString() });
                unread.value++;
            });
    }

    return { user, notifications, unread, isLoggedIn, loadUser, login, register, logout, loadNotifications, markAllRead };
});
