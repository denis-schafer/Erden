<template>
    <div class="page-container">
        <component :is="currentComponent" />
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useAuthStore } from '../../stores/auth';
import Dashboard from '../modules/Dashboard.vue';
import UsersIndex from '../modules/users/Index.vue';
import RolesIndex from '../modules/roles/Index.vue';
import ModulesIndex from '../modules/admin/ModulesIndex.vue';

const authStore = useAuthStore();

const currentView = ref('dashboard');

const componentsMap = {
    'dashboard': Dashboard,
    'users': UsersIndex,
    'roles': RolesIndex,
    'admin-modules': ModulesIndex
};

const currentComponent = computed(() => {
    return componentsMap[currentView.value] || Dashboard;
});

const setView = (view) => {
    currentView.value = view;
};

window.setView = setView;

watch(() => authStore.modules, (newModules) => {
    if (newModules && newModules.length > 0) {
        const firstAccessible = newModules.find(m => m.route !== 'menu');
        if (firstAccessible) {
            currentView.value = firstAccessible.route;
        }
    }
}, { immediate: true });

defineExpose({ setView, currentView });
</script>

<style scoped>
.page-container {
    width: 100%;
    height: 100%;
}
</style>