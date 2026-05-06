<template>
    <div 
        class="action-menu-wrapper" 
        v-if="actions.length > 0"
        :id="'action-menu-' + menuId"
    >
        <div v-if="actions.length <= 2" class="action-buttons">
            <button 
                v-for="(action, index) in actions" 
                :key="index"
                :class="action.class || 'btn btn-sm btn-outline-secondary'"
                :title="action.title"
                @click="action.handler"
            >
                <i :class="action.icon"></i>
            </button>
        </div>
        <div v-else class="action-dropdown">
            <button 
                class="btn btn-sm btn-outline-secondary action-burger" 
                type="button"
                @click="toggleMenu"
            >
                <i class="bi bi-three-dots-vertical"></i>
            </button>
        </div>
        
        <Teleport to="body">
            <div 
                v-if="showMenu" 
                class="action-menu-portal"
                :style="menuStyle"
            >
                <button 
                    v-for="(action, index) in actions" 
                    :key="index"
                    class="action-item"
                    @click="handleAction(action)"
                >
                    <i :class="action.icon"></i>
                    <span>{{ action.title }}</span>
                </button>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    actions: {
        type: Array,
        required: true
    }
});

const showMenu = ref(false);
const menuId = ref(Math.random().toString(36).substr(2, 9));
const buttonRef = ref(null);

const toggleMenu = () => {
    showMenu.value = !showMenu.value;
};

const handleAction = (action) => {
    showMenu.value = false;
    action.handler();
};

const closeMenu = () => {
    showMenu.value = false;
};

const menuStyle = computed(() => {
    if (!buttonRef.value) return {};
    
    const rect = buttonRef.value.getBoundingClientRect();
    
    return {
        position: 'fixed',
        top: (rect.bottom + 8) + 'px',
        right: (window.innerWidth - rect.right) + 'px',
        zIndex: '9999999999'
    };
});

const handleClickOutside = (e) => {
    const menuEl = document.getElementById('action-menu-' + menuId.value);
    if (menuEl && !menuEl.contains(e.target)) {
        closeMenu();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    
    const btn = document.querySelector(`#action-menu-${menuId.value} .action-burger`);
    if (btn) buttonRef.value = btn;
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<style>
.action-menu-wrapper {
    display: inline-flex;
    align-items: center;
    position: relative;
}

.action-buttons {
    display: flex;
    gap: 4px;
}

.action-buttons button {
    padding: 0.25rem 0.5rem;
    line-height: 1;
}

.action-burger {
    padding: 0.25rem 0.5rem;
    line-height: 1;
}

.action-dropdown {
    position: relative;
}

.action-menu-portal {
    position: fixed;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    min-width: 140px;
    overflow: hidden;
    animation: dropdownFadeIn 0.15s ease-out;
}

@keyframes dropdownFadeIn {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.action-item {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 10px 14px;
    border: none;
    background: none;
    cursor: pointer;
    text-align: left;
    font-size: 0.875rem;
    color: #212529;
    transition: background-color 0.15s;
}

.action-item:hover {
    background: #f8f9fa;
}

.action-item i {
    width: 18px;
    text-align: center;
}
</style>