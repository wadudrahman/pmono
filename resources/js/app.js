import './bootstrap';
import {createApp} from 'vue';
import {createPinia} from 'pinia';
import router from './router';
import App from './App.vue';

console.log('App.js starting...');

try {
    const app = createApp(App);
    console.log('Vue app created');

    const pinia = createPinia();
    console.log('Pinia created');

    app.use(pinia);
    console.log('Pinia installed');

    app.use(router);
    console.log('Router installed');

    app.mount('#app');
    console.log('App mounted successfully!');
} catch (error) {
    console.error('Failed to initialize app:', error);
    document.getElementById('app').innerHTML = '<h1>Error loading app: ' + error.message + '</h1>';
}
