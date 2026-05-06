<template>
    <div class="pos-users-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Usuarios</h5>
            <button class="btn btn-sm btn-primary" @click="openCreateModal">
                <i class="bi bi-plus"></i> Nuevo
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Impresora</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id">
                        <td>{{ user.name }}</td>
                        <td>{{ user.username }}</td>
                        <td>{{ user.role_name }}</td>
                        <td>
                            <span v-if="user.printer_ip" class="badge bg-info">
                                <i class="bi bi-printer"></i> {{ user.printer_ip }}
                            </span>
                            <span v-else class="badge bg-secondary">Sin configurar</span>
                        </td>
                        <td>
                            <div class="form-check form-switch">
                                <input 
                                    class="form-check-input" 
                                    type="checkbox" 
                                    :checked="user.enable === 1 || user.enable === true"
                                    @change="toggleUserStatus(user.id, user)"
                                >
                            </div>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-primary" @click="editUser(user)" title="Editar">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" @click="deleteUser(user.id)" title="Eliminar">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ConfirmModal ref="confirmModal" />

        <div v-if="showModal" class="modal d-block" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingUser ? 'Editar' : 'Nuevo' }} Usuario</h5>
                        <button @click="closeModal" type="button" class="btn-close"></button>
                    </div>
                    <form @submit.prevent="saveUser">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nombre</label>
                                        <input v-model="form.name" type="text" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Usuario</label>
                                        <input v-model="form.username" type="text" class="form-control" required>
                                    </div>
                                    <div class="mb-3" v-if="!editingUser">
                                        <label class="form-label">Contraseña</label>
                                        <input v-model="form.password" type="password" class="form-control">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Rol</label>
                                        <select v-model="form.role_id" class="form-select" required>
                                            <option value="">Seleccionar...</option>
                                            <option v-for="role in roles" :key="role.id" :value="role.id">
                                                {{ role.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6 class="border-bottom pb-2 mb-3">
                                        <i class="bi bi-printer"></i> Configuración de Impresora
                                    </h6>
                                    <div class="mb-3">
                                        <label class="form-label">IP de Impresora</label>
                                        <input v-model="form.printer_ip" type="text" class="form-control" placeholder="192.168.1.100">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Puerto</label>
                                        <input v-model="form.printer_port" type="number" class="form-control" placeholder="9100">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Tipo de Impresora</label>
                                        <select v-model="form.printer_type" class="form-select">
                                            <option value="raw">Raw</option>
                                            <option value="network">Network</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Ancho de Papel</label>
                                        <select v-model="form.printer_width" class="form-select">
                                            <option :value="80">80mm (48 caracteres)</option>
                                            <option :value="50">50mm (32 caracteres)</option>
                                        </select>
                                    </div>
                                    
                                    <h6 class="border-bottom pb-2 mb-3 mt-4">
                                        <i class="bi bi-paypal"></i> Opciones de Pago
                                    </h6>
                                    <div class="mb-3">
    <div class="form-check">
        <input 
            v-model="form.enable_print" 
            class="form-check-input" 
            type="checkbox" 
            id="enable_print"
        >
        <label class="form-check-label" for="enable_print">
            Habilitar impresión automática de tickets
        </label>
        <small v-if="!form.printer_ip || !form.printer_port" class="text-muted d-block mt-1">
            <i class="bi bi-exclamation-circle"></i> 
            Si no configura la impresora, esta opción se guardará como desactivada
        </small>
    </div>
</div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input v-model="form.mercadopago_qr_enabled" class="form-check-input" type="checkbox" id="mercadopago_qr">
                                            <label class="form-check-label" for="mercadopago_qr">Habilitar QR MercadoPago</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button @click="closeModal" type="button" class="btn btn-secondary">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div v-if="showModal" class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../../services/api';
import { toast as toastify } from '../../../utils/toast';
import ConfirmModal from '../../../components/ConfirmModal.vue';

const users = ref([]);
const roles = ref([]);
const showModal = ref(false);
const editingUser = ref(null);
const confirmModal = ref(null);

const form = reactive({
    name: '',
    username: '',
    password: '',
    role_id: '',
    enable: true,
    printer_ip: '',
    printer_port: 9100,
    printer_type: 'raw',
    printer_width: 80,
    enable_print: false,
    mercadopago_qr_enabled: false,
});

const loadData = async () => {
    try {
        const [usersRes, rolesRes] = await Promise.all([
            api.get('/pos/users'),
            api.get('/pos/roles')
        ]);
        users.value = usersRes.data;
        roles.value = rolesRes.data;
    } catch (error) {
        console.error('Error loading data:', error);
    }
};

const openCreateModal = () => {
    editingUser.value = null;
    form.name = '';
    form.username = '';
    form.password = '';
    form.role_id = '';
    form.enable = true;
    form.printer_ip = '';
    form.printer_port = 9100;
    form.printer_type = 'raw';
    form.printer_width = 80;
    form.enable_print = false;
    form.mercadopago_qr_enabled = false;
    showModal.value = true;
};

const editUser = (user) => {
    editingUser.value = user;
    form.name = user.name;
    form.username = user.username;
    form.role_id = user.role_id;
    form.enable = user.enable === 1 || user.enable === true;
    form.password = '';
    form.printer_ip = user.printer_ip || '';
    form.printer_port = user.printer_port || 9100;
    form.printer_type = user.printer_type || 'raw';
    form.printer_width = parseInt(user.printer_width) || 80;
    form.enable_print = user.enable_print === 1 || user.enable_print === true || user.enable_print === '1';
    form.mercadopago_qr_enabled = user.mercadopago_qr_enabled === 1 || user.mercadopago_qr_enabled === true || user.mercadopago_qr_enabled === '1';
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingUser.value = null;
    form.name = '';
    form.username = '';
    form.password = '';
    form.role_id = '';
    form.enable = true;
    form.printer_ip = '';
    form.printer_port = 9100;
    form.printer_type = 'raw';
    form.printer_width = 80;
    form.enable_print = false;
    form.mercadopago_qr_enabled = false;
};

const saveUser = async () => {
    try {
        const data = {
            name: form.name,
            username: form.username,
            role_id: form.role_id,
            enable: form.enable,
            printer_ip: form.printer_ip || null,
            printer_port: form.printer_port || 9100,
            printer_type: form.printer_type || 'raw',
            printer_width: parseInt(form.printer_width) || 80,
            enable_print: form.enable_print ? '1' : '0',
            mercadopago_qr_enabled: form.mercadopago_qr_enabled ? '1' : '0',
        };
        
        if (editingUser.value) {
            if (form.password) {
                data.password = form.password;
            }
            await api.put(`/pos/users/${editingUser.value.id}`, data);
        } else {
            data.password = form.password;
            await api.post('/pos/users', data);
        }
        closeModal();
        loadData();
        toastify.success(editingUser.value ? 'Usuario actualizado correctamente' : 'Usuario creado correctamente');
    } catch (error) {
        console.error('Error saving user:', error);
        toastify.error('Error al guardar usuario');
    }
};

const toggleUserStatus = async (id, currentStatus) => {
    try {
        const response = await api.post(`/pos/users/${id}/toggle-status`);
        loadData();
        toastify.success(`Usuario ${currentStatus ? 'deshabilitado' : 'habilitado'} correctamente`);
    } catch (error) {
        loadData();
        toastify.error('Error al cambiar estado del usuario');
    }
};

const deleteUser = async (id) => {
    confirmModal.value.open({
        title: 'Confirmar Eliminación',
        message: '¿Está seguro de eliminar este usuario?',
        confirmText: 'Eliminar',
        type: 'danger',
        onConfirm: async () => {
            try {
                await api.delete(`/pos/users/${id}`);
                loadData();
                toastify.success('Usuario eliminado');
            } catch (error) {
                toastify.error('Error al eliminar usuario');
            }
        }
    });
};

onMounted(loadData);

window.addEventListener('pos-user-disabled', () => {
    loadData();
});
</script>

<style scoped>
.pos-users-container {
    height: 100%;
    display: flex;
    flex-direction: column;
    background-color: #f8f9fa;
    padding: 1rem;
    overflow-y: auto;
}
</style>