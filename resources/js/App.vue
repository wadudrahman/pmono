<template>
    <router-view/>
    <NotificationContainer />
</template>

<script setup>
import {onMounted} from 'vue';
import {useAuthStore} from './stores/auth';
import NotificationContainer from './components/NotificationContainer.vue';

const authStore = useAuthStore();

onMounted(async () => {
    // Check if user is authenticated and fetch user data
    if (authStore.isAuthenticated) {
        await authStore.fetchUser();
    }
});
</script>
