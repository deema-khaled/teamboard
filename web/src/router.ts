import { useAuth } from '@/stores/auth';
import { createRouter, createWebHistory } from 'vue-router';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: '/boards' },
        { path: '/login', component: () => import('@/pages/LoginPage.vue'), meta: { guest: true } },
        { path: '/register', component: () => import('@/pages/RegisterPage.vue'), meta: { guest: true } },
        { path: '/boards', component: () => import('@/pages/BoardsPage.vue'), meta: { auth: true } },
        { path: '/boards/:id', component: () => import('@/pages/BoardPage.vue'), meta: { auth: true }, props: true },
        { path: '/invitations/:token', component: () => import('@/pages/InvitationPage.vue'), props: true },
        { path: '/:pathMatch(.*)*', component: () => import('@/pages/NotFoundPage.vue') },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuth();
    await auth.loadUser();

    if (to.meta.auth && !auth.isLoggedIn) {
        return { path: '/login', query: { redirect: to.fullPath } };
    }
    if (to.meta.guest && auth.isLoggedIn) {
        return '/boards';
    }
});

export default router;
