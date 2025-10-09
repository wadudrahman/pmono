import { createApp } from 'vue';

console.log('Test.js loaded!');

const app = createApp({
    data() {
        return {
            message: 'Vue is working!'
        }
    }
});

app.mount('#test');
console.log('Vue app mounted!');