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
                    <tr v-if="loading">
                        <td colspan="6" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="user in users" :key="user.id" v-else>
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
                                    :disabled="loadingActions['toggle-' + user.id]"
                                    @change="toggleUserStatus(user.id, user)"
                                >
                            </div>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-primary" :disabled="loadingActions['edit-' + user.id]" @click="editUser(user)" title="Editar">
                                    <span v-if="loadingActions['edit-' + user.id]" class="spinner-border spinner-border-sm"></span>
                                    <i v-else class="bi bi-pencil-fill"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" :disabled="loadingActions['delete-' + user.id]" @click="deleteUser(user.id)" title="Eliminar">
                                    <span v-if="loadingActions['delete-' + user.id]" class="spinner-border spinner-border-sm"></span>
                                    <i v-else class="bi bi-trash-fill"></i>
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
                                
                                <div class="col-md-6" v-if="form.role_id != 3">
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
                                    <div class="mb-3" v-if="form.role_id == 2 && form.mercadopago_qr_enabled">
                                        <label class="form-label">ID Postnet (Point)</label>
                                        <div class="input-group">
                                            <input v-model="form.posnet_id" type="text" class="form-control" placeholder="NEWLAND_N950__N950NCB801293324">
                                            <button class="btn btn-outline-secondary" type="button" @click="fetchTerminals" :disabled="loadingTerminals">
                                                <i class="bi" :class="loadingTerminals ? 'bi-arrow-repeat spin' : 'bi-list-ul'"></i>
                                                Listar
                                            </button>
                                        </div>
                                        <div v-if="terminals.length > 0" class="mt-2 p-2 border rounded">
                                            <label class="form-label small">Seleccionar terminal:</label>
                                            <select class="form-select form-select-sm" @change="form.posnet_id = $event.target.value">
                                                <option value="">-- Elegir --</option>
                                                <option v-for="t in terminals" :key="t.id" :value="t.id">
                                                    {{ t.id }} ({{ t.model || 'N/A' }})
                                                </option>
                                            </select>
                                        </div>
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle"></i>
                                            ID del terminal Point de MercadoPago. Se envía el importe al postnet al crear cada orden.
                                            Usa el botón <strong>Listar</strong> para cargar los terminales disponibles en tu cuenta.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button @click="closeModal" type="button" class="btn btn-secondary">Cancelar</button>
                            <button type="submit" class="btn btn-primary" :disabled="saving">
                                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                                Guardar
                            </button>
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
import { useCache } from '../../../composables/useCache';

const { fetch, refresh } = useCache();

const users = ref([]);
const roles = ref([]);
const showModal = ref(false);
const editingUser = ref(null);
const confirmModal = ref(null);
const loading = ref(true);
const saving = ref(false);
const loadingActions = reactive({});

const withLoading = async (key, cb) => {
    loadingActions[key] = true;
    try {
        await cb();
    } finally {
        loadingActions[key] = false;
    }
};

const terminals = ref([]);
const loadingTerminals = ref(false);

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
    posnet_id: '',
});

const loadData = async () => {
    loading.value = true;
    try {
        const [usersData, rolesData] = await Promise.all([
            fetch('users', () => api.get('/pos/users').then(r => r.data)),
            fetch('roles', () => api.get('/pos/roles').then(r => r.data))
        ]);
        users.value = usersData;
        roles.value = rolesData;
    } catch (error) {
    } finally {
        loading.value = false;
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
    form.posnet_id = '';
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
    form.posnet_id = user.posnet_id || '';
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
    form.posnet_id = '';
};

const saveUser = async () => {
    saving.value = true;
    try {
        const data = {
            name: form.name,
            username: form.username,
            role_id: form.role_id,
            enable: form.enable,
        };

        if (form.role_id != 3) {
            data.printer_ip = form.printer_ip || null;
            data.printer_port = form.printer_port || 9100;
            data.printer_type = form.printer_type || 'raw';
            data.printer_width = parseInt(form.printer_width) || 80;
            data.enable_print = form.enable_print ? '1' : '0';
            data.mercadopago_qr_enabled = form.mercadopago_qr_enabled ? '1' : '0';
            data.posnet_id = form.posnet_id || null;
        }
        
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
        users.value = await refresh('users', () => api.get('/pos/users').then(r => r.data));
        roles.value = await refresh('roles', () => api.get('/pos/roles').then(r => r.data));
        toastify.success(editingUser.value ? 'Usuario actualizado correctamente' : 'Usuario creado correctamente');
    } catch (error) {
        toastify.error('Error al guardar usuario');
    } finally {
        saving.value = false;
    }
};

const fetchTerminals = async () => {
    loadingTerminals.value = true;
    terminals.value = [];
    try {
        const res = await api.get('/pos/terminals');
        if (res.data.success && res.data.terminals.length > 0) {
            terminals.value = res.data.terminals;
        } else {
            toastify.info('No se encontraron terminales Point en tu cuenta de MercadoPago.');
        }
    } catch (e) {
        toastify.error(e.response?.data?.message || 'Error al listar terminales');
    } finally {
        loadingTerminals.value = false;
    }
};

const toggleUserStatus = async (id, currentStatus) => {
    await withLoading('toggle-' + id, async () => {
        try {
            await api.post(`/pos/users/${id}/toggle-status`);
            users.value = await refresh('users', () => api.get('/pos/users').then(r => r.data));
            toastify.success(`Usuario ${currentStatus ? 'deshabilitado' : 'habilitado'} correctamente`);
        } catch (error) {
            toastify.error('Error al cambiar estado del usuario');
        }
    });
};

const deleteUser = async (id) => {
    confirmModal.value.open({
        title: 'Confirmar Eliminación',
        message: '¿Está seguro de eliminar este usuario?',
        confirmText: 'Eliminar',
        type: 'danger',
        onConfirm: () => withLoading('delete-' + id, async () => {
            try {
                await api.delete(`/pos/users/${id}`);
                users.value = await refresh('users', () => api.get('/pos/users').then(r => r.data));
                toastify.success('Usuario eliminado');
            } catch (error) {
                toastify.error('Error al eliminar usuario');
            }
        })
    });
};

onMounted(loadData);

window.addEventListener('pos-user-disabled', async () => {
    users.value = await refresh('users', () => api.get('/pos/users').then(r => r.data));
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

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.bi-arrow-repeat.spin {
    animation: spin 1s linear infinite;
}
</style>