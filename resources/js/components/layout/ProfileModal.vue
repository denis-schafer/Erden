<template>
    <div class="modal-backdrop show" @click.self="$emit('close')"></div>
    <div class="modal show d-block" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Perfil de Usuario</h5>
                    <button type="button" class="btn-close" @click="$emit('close')"></button>
                </div>
                <form @submit.prevent="saveProfile">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Usuario</label>
                            <input type="text" class="form-control" :value="authStore.user?.username" disabled>
                            <div class="form-text text-muted">El usuario no puede ser modificado</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input 
                                v-model="form.name" 
                                type="text" 
                                class="form-control" 
                                required
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nueva Contraseña</label>
                            <input 
                                v-model="form.password" 
                                type="password" 
                                class="form-control"
                                placeholder="Dejar en blanco para mantener la actual"
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirmar Contraseña</label>
                            <input 
                                v-model="form.password_confirmation" 
                                type="password" 
                                class="form-control"
                                placeholder="Dejar en blanco para mantener la actual"
                            >
                        </div>
                        <div v-if="error" class="alert alert-danger">{{ error }}</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancelar</button>
                        <button type="submit" class="btn btn-primary" :disabled="loading">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { toast } from '../../utils/toast';
import api from '../../services/api';

const emit = defineEmits(['close']);

const authStore = useAuthStore();

const form = reactive({
    name: '',
    password: '',
    password_confirmation: ''
});

const loading = ref(false);
const error = ref('');

onMounted(() => {
    form.name = authStore.user?.name || authStore.user?.username || '';
});

const saveProfile = async () => {
    error.value = '';
    
    if (form.password && form.password !== form.password_confirmation) {
        error.value = 'Las contraseñas no coinciden';
        return;
    }
    
    if (form.password && form.password.length < 6) {
        error.value = 'La contraseña debe tener al menos 6 caracteres';
        return;
    }
    
    loading.value = true;
    
    try {
        const updateData = {
            name: form.name
        };
        
        if (form.password) {
            updateData.password = form.password;
            updateData.password_confirmation = form.password_confirmation;
        }
        
        await api.put('/profile', updateData);
        
        authStore.user.name = form.name;
        localStorage.setItem('user', JSON.stringify(authStore.user));
        
        toast.success('Perfil actualizado correctamente');
        emit('close');
    } catch (err) {
        error.value = err.response?.data?.message || 'Error al actualizar perfil';
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1040;
}

.modal {
    z-index: 1050;
}
</style>