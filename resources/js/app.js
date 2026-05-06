import { createApp } from 'vue';
import { createPinia } from 'pinia';
import Vue3Toasity from 'vue3-toastify';
import App from './App.vue';
import '../sass/app.scss';
import './bootstrap';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(Vue3Toasity, {
    position: 'top-right',
    timeout: 5000,
    slide: {
        enter: 'slideInFromLeft',
        exit: 'slideOutToRight'
    }
});

app.mount('#app');