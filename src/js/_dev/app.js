

import App from '../../App.vue';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js'; // Import Bootstrap JS

import '../../main.css';
import '../../exported.css';

import { createApp } from 'vue';
import { createPluginPolyfill } from './plugin-polyfill';
import { createPinia } from 'pinia';
import router from './router';


setTimeout(() => {
    import('../../main.js').then((module) => {
        console.log('SpPS Plugin loaded successfully!');
    }, 1000);
});

createPluginPolyfill();
const app = createApp(App);

app.use(createPinia());
app.use(router);

app.mount('#app');
