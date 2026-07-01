<template>
    <div class="hairsalon-users p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Usuarios</h4>
            <button class="btn btn-primary btn-sm" @click="openForm()"><i class="bi bi-plus"></i> Nuevo Usuario</button>
        </div>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <div v-else>
            <DataTable :data="users" :columns="columns" :per-page="15">
                <template #rowActions="{ row }">
                    <button class="btn btn-sm btn-outline-primary me-1" @click="openForm(row)"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm" :class="row.enable ? 'btn-outline-warning' : 'btn-outline-success'" @click="toggleStatus(row)">
                        <i :class="row.enable ? 'bi bi-pause-circle' : 'bi bi-play-circle'"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger ms-1" @click="confirmDeleteUser(row)" v-if="row.username !== 'admin'"><i class="bi bi-trash"></i></button>
                </template>
            </DataTable>
        </div>

        <div v-if="showModal" class="modal d-block"><div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">{{ editing ? 'Editar' : 'Nuevo' }} Usuario</h5><button class="btn-close" @click="showModal = false"></button></div>
            <form @submit.prevent="saveUser"><div class="modal-body">
                <div class="mb-2"><label class="form-label">Nombre</label><input v-model="form.name" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label">Usuario</label><input v-model="form.username" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label">Contraseña</label><input v-model="form.password" class="form-control form-control-sm" type="password" :required="!editing" :placeholder="editing ? 'Dejar vacío para no cambiar' : ''"></div>
                <div class="mb-2"><label class="form-label">Rol</label><select v-model="form.role_id" class="form-select form-select-sm"><option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option></select></div>
            </div><div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" @click="showModal = false">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ saving ? 'Guardando...' : 'Guardar' }}</button>
            </div></form>
        </div></div></div>
        <div v-if="showModal" class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, inject } from 'vue';
import api from '../../../services/api';
import DataTable from '../../../components/common/DataTable.vue';
import { toast } from '../../../utils/toast';

const confirmDialog = inject('confirmDialog', null);
const loading = ref(true);
const users = ref([]);
const roles = ref([]);
const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const form = reactive({ name: '', username: '', password: '', role_id: '' });

const columns = [
    { key: 'name', label: 'Nombre' },
    { key: 'username', label: 'Usuario' },
    { key: 'role_name', label: 'Rol' },
];

const loadUsers = async () => {
    loading.value = true;
    try {
        const [usersRes, rolesRes] = await Promise.all([
            api.get('/hairsalon/users', { params: { per_page: 500 } }).then(r => r.data),
            api.get('/hairsalon/users/roles').then(r => r.data),
        ]);
        users.value = usersRes.data || [];
        roles.value = rolesRes;
    } finally { loading.value = false; }
};

const openForm = (user) => {
    editing.value = user || null;
    form.name = user?.name || '';
    form.username = user?.username || '';
    form.password = '';
    form.role_id = user?.role_id || '';
    showModal.value = true;
};

const saveUser = async () => {
    saving.value = true;
    try {
        if (editing.value) {
            const payload = { ...form };
            if (!payload.password) delete payload.password;
            await api.put('/hairsalon/users/' + editing.value.id, payload);
            toast.success('Usuario actualizado');
        } else {
            await api.post('/hairsalon/users', form);
            toast.success('Usuario creado');
        }
        showModal.value = false;
        loadUsers();
    } catch (e) { toast.error(e.response?.data?.message || 'Error'); }
    finally { saving.value = false; }
};

const toggleStatus = async (user) => {
    try {
        await api.post('/hairsalon/users/' + user.id + '/toggle-status');
        toast.success('Estado cambiado');
        loadUsers();
    } catch (e) { toast.error(e.response?.data?.message || 'Error'); }
};

const confirmDeleteUser = async (user) => {
    if (confirmDialog && confirmDialog.value) {
        const confirmed = await confirmDialog.value.open({
            title: 'Eliminar Usuario',
            message: `¿Está seguro de eliminar a "${user.name}"?`,
            confirmText: 'Eliminar',
            confirmClass: 'btn-danger'
        });
        if (!confirmed) return;
    } else {
        if (!confirm('¿Está seguro de eliminar este usuario?')) return;
    }
    try {
        await api.delete('/hairsalon/users/' + user.id);
        toast.success('Usuario eliminado');
        loadUsers();
    } catch (e) { toast.error(e.response?.data?.message || 'Error'); }
};

onMounted(() => { loadUsers(); });
</script>
