<template>
    <router-view/>
</template>

<script setup>
import {onMounted} from 'vue';
import {useAuthStore} from './stores/auth';

const authStore = useAuthStore();

onMounted(async () => {
    // Check if user is authenticated and fetch user data
    if (authStore.isAuthenticated) {
        await authStore.fetchUser();
    }
});
</script>
