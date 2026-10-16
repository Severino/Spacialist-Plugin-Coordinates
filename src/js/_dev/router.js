import { createRouter, createWebHistory } from 'vue-router';
import DevTabs from '../../components/_dev/DevTabs.vue';

const routes = [
    {
        path: '/',
        name: 'Home',
        component: DevTabs,
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;