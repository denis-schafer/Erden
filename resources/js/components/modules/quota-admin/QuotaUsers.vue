<template>
    <div class="quota-users p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Usuarios del Sistema</h4>
            <button class="btn btn-primary btn-sm" @click="openCreate">
                <i class="bi bi-plus-lg"></i> Nuevo Usuario
            </button>
        </div>

        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <template v-else>
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in users" :key="u.id">
                        <td>{{ u.username }}</td>
                        <td>{{ u.name }}</td>
                        <td>{{ u.role_name }}</td>
                        <td>
                            <span class="badge" :class="u.enable ? 'bg-success' : 'bg-secondary'">
                                {{ u.enable ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" @click="openEdit(u)" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(u)" title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!users.length">
                        <td colspan="5" class="text-center text-muted">No hay usuarios</td>
                    </tr>
                </tbody>
            </table>
        </template>

        <div v-if="showForm" class="modal fade show d-block" tabindex="-1"
             style="background: rgba(0,0,0,0.5); z-index: 1060;"
             @click.self="showForm = false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingUser ? 'Editar Usuario' : 'Nuevo Usuario' }}</h5>
                        <button type="button" class="btn-close" @click="showForm = false"></button>
                    </div>
                    <form @submit.prevent="saveUser">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Usuario</label>
                                <input class="form-control form-control-sm" v-model="form.username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input class="form-control form-control-sm" v-model="form.name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña</label>
                                <input class="form-control form-control-sm" type="password" v-model="form.password" :required="!editingUser">
                                <small v-if="editingUser" class="text-muted">Dejar en blanco para mantener la actual</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Rol</label>
                                <select class="form-select form-select-sm" v-model="form.role_id" required>
                                    <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enableCheck" v-model="form.enable">
                                    <label class="form-check-label" for="enableCheck">Activo</label>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-secondary" @click="showForm = false">Cancelar</button>
                            <button type="submit" class="btn btn-sm btn-primary" :disabled="saving">
                                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                                {{ editingUser ? 'Actualizar' : 'Crear' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <ConfirmModal ref="confirmModal" />
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { toast } from '../../../utils/toast';
import ConfirmModal from '../../../components/ConfirmModal.vue';

const users = ref([]);
const roles = ref([]);
const loading = ref(true);
const showForm = ref(false);
const editingUser = ref(null);
const saving = ref(false);
const confirmModal = ref(null);

const form = ref({
    username: '',
    name: '',
    password: '',
    role_id: '',
    enable: true,
});

const loadUsers = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/quota/users');
        users.value = data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
};

const loadRoles = async () => {
    try {
        const { data } = await axios.get('/quota/users/roles');
        roles.value = data;
    } catch (e) {
        console.error(e);
    }
};

const openCreate = () => {
    editingUser.value = null;
    form.value = { username: '', name: '', password: '', role_id: '', enable: true };
    showForm.value = true;
};

const openEdit = (u) => {
    editingUser.value = u;
    form.value = { username: u.username, name: u.name, password: '', role_id: u.role_id, enable: !!u.enable };
    showForm.value = true;
};

const saveUser = async () => {
    saving.value = true;
    try {
        if (editingUser.value) {
            await axios.put(`/quota/users/${editingUser.value.id}`, form.value);
        } else {
            await axios.post('/quota/users', form.value);
        }
        showForm.value = false;
        loadUsers();
        toast.success(editingUser.value ? 'Usuario actualizado' : 'Usuario creado');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al guardar');
    } finally {
        saving.value = false;
    }
};

const confirmDelete = async (u) => {
    if (confirmModal.value) {
        confirmModal.value.open({
            title: 'Eliminar Usuario',
            message: `¿Eliminar a ${u.name}?`,
            confirmText: 'Eliminar',
            type: 'danger',
            onConfirm: async () => {
                try {
                    await axios.delete(`/quota/users/${u.id}`);
                    loadUsers();
                    toast.success('Usuario eliminado');
                } catch (e) {
                    toast.error(e.response?.data?.message || 'Error al eliminar');
                }
            }
        });
    }
};

onMounted(() => {
    loadUsers();
    loadRoles();
});
</script>

<style scoped>
.modal-dialog {
    width: 550px;
    max-width: 90%;
}
</style>
