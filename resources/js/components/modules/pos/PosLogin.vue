<template>
    <div class="pos-login-container">
        <div class="pos-login-box">
            <div class="text-center mb-4">
                <h1 class="h4 mb-2">POS</h1>
                <p class="text-muted small">{{ companyName }}</p>
            </div>
            <form @submit.prevent="handleLogin">
                <div class="mb-3">
                    <label class="form-label">Usuario</label>
                    <input 
                        v-model="form.username" 
                        type="text" 
                        class="form-control" 
                        required
                        autocomplete="username"
                    >
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input 
                        v-model="form.password" 
                        type="password" 
                        class="form-control" 
                        required
                        autocomplete="current-password"
                    >
                </div>
                <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>
                <button type="submit" class="btn btn-primary w-100" :disabled="loading">
                    <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                    Iniciar Sesión
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../../services/api';

const emit = defineEmits(['login-success']);

const props = defineProps({
    companyDb: {
        type: String,
        required: true
    },
    companyName: {
        type: String,
        default: 'POS'
    }
});

const form = reactive({
    username: '',
    password: ''
});

const loading = ref(false);
const error = ref('');

const handleLogin = async () => {
    loading.value = true;
    error.value = '';
    
    try {
        const response = await api.post('/pos/login', form);
        
        localStorage.setItem('pos_token', response.data.token);
        localStorage.setItem('pos_user', JSON.stringify(response.data.user));
        
        emit('login-success', response.data);
    } catch (err) {
        error.value = err.response?.data?.message || 'Credenciales incorrectas';
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    const token = localStorage.getItem('pos_token');
    if (token) {
        emit('login-success', { user: JSON.parse(localStorage.getItem('pos_user')), token });
    }
});
</script>

<style scoped>
.pos-login-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #212529;
}

.pos-login-box {
    width: 100%;
    max-width: 350px;
    padding: 2rem;
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
}
</style>
