<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Usuarios</h2>
        </div>

        <div class="card">
            <div class="card-body">
                <DataTable 
                    :data="users" 
                    :columns="columns"
                    :per-page="10"
                >
                    <template #actions>
                        <button 
                            v-if="authStore.hasPermission('users_create')"
                            @click="showModal = true" 
                            class="btn btn-primary btn-circle"
                            title="Nuevo"
                        >
                            <i class="bi bi-plus"></i>
                        </button>
                    </template>
                    <template #rowActions="{ row }">
                        <ActionMenu :actions="getUserActions(row)" />
                    </template>
                </DataTable>
            </div>
        </div>

        <div v-if="showModal" class="modal d-block" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingUser ? 'Editar' : 'Nuevo' }} Usuario</h5>
                        <button @click="closeModal" type="button" class="btn-close"></button>
                    </div>
                    <form @submit.prevent="saveUser">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Usuario</label>
                                <input v-model="form.username" type="text" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input v-model="form.name" type="text" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña</label>
                                <input v-model="form.password" type="password" class="form-control" :required="!editingUser">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Rol</label>
                                <select v-model="form.role_id" class="form-select" required>
                                    <option value="">Seleccionar rol</option>
                                    <option v-for="role in roles" :key="role.id" :value="role.id">
                                        {{ role.name }}
                                    </option>
                                </select>
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
import { ref, reactive, onMounted, inject } from 'vue';
import { useAuthStore } from '../../../stores/auth';
import { toast as toastify } from '../../../utils/toast';
import api from '../../../services/api';
import DataTable from '../../../components/common/DataTable.vue';
import ActionMenu from '../../../components/common/ActionMenu.vue';

const authStore = useAuthStore();
const confirmDialog = inject('confirmDialog');

const users = ref([]);
const roles = ref([]);
const showModal = ref(false);
const editingUser = ref(null);

const columns = [
    { key: 'id', label: 'ID' },
    { key: 'username', label: 'Usuario' },
    { key: 'name', label: 'Nombre' },
    { key: 'role_name', label: 'Rol' }
];

const getUserActions = (user) => {
    const actions = [];
    
    if (authStore.hasPermission('users_update')) {
        actions.push({
            icon: 'bi bi-pencil',
            title: 'Editar',
            class: 'btn btn-sm btn-outline-primary',
            handler: () => editUser(user)
        });
    }
    
    if (authStore.hasPermission('users_delete')) {
        actions.push({
            icon: 'bi bi-trash',
            title: 'Eliminar',
            class: 'btn btn-sm btn-outline-danger',
            handler: () => deleteUser(user.id)
        });
    }
    
    return actions;
};

const form = reactive({
    username: '',
    name: '',
    password: '',
    role_id: ''
});

const loadData = async () => {
    try {
        const [usersRes, rolesRes] = await Promise.all([
            api.get('/users'),
            api.get('/roles')
        ]);
        users.value = usersRes.data;
        roles.value = rolesRes.data;
    } catch (error) {
        toastify.error('Error al cargar datos');
    }
};

const editUser = (user) => {
    editingUser.value = user;
    form.username = user.username;
    form.name = user.name || '';
    form.password = '';
    form.role_id = user.role_id;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingUser.value = null;
    form.username = '';
    form.name = '';
    form.password = '';
    form.role_id = '';
};

const saveUser = async () => {
    try {
        if (editingUser.value) {
            await api.put(`/users/${editingUser.value.id}`, form);
        } else {
            await api.post('/users', form);
        }
        closeModal();
        loadData();
        toastify.success('Usuario guardado correctamente');
    } catch (error) {
        toastify.error(error.response?.data?.message || 'Error al guardar');
    }
};

const deleteUser = async (id) => {
    const confirmed = await confirmDialog.open({
        title: 'Confirmar Eliminación',
        message: '¿Está seguro de eliminar este usuario?',
        confirmText: 'Eliminar',
        confirmClass: 'btn-danger'
    });
    
    if (!confirmed) return;
    
    try {
        await api.delete(`/users/${id}`);
        loadData();
        toastify.success('Usuario eliminado correctamente');
    } catch (error) {
        toastify.error(error.response?.data?.message || 'Error al eliminar');
    }
};

onMounted(loadData);
</script>