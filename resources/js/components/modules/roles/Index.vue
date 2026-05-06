<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Roles</h2>
        </div>

        <div class="card">
            <div class="card-body">
                <DataTable 
                    :data="roles" 
                    :columns="columns"
                    :per-page="10"
                >
                    <template #actions>
                        <button 
                            v-if="authStore.hasPermission('roles_create')"
                            @click="showModal = true" 
                            class="btn btn-primary btn-circle"
                            title="Nuevo"
                        >
                            <i class="bi bi-plus"></i>
                        </button>
                    </template>
                    <template #rowActions="{ row }">
                        <ActionMenu :actions="getRoleActions(row)" />
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Modal de Rol -->
        <div v-if="showModal" class="modal d-block" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingRole ? 'Editar' : 'Nuevo' }} Rol</h5>
                        <button @click="closeModal" type="button" class="btn-close"></button>
                    </div>
                    <form @submit.prevent="saveRole">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input v-model="form.name" type="text" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Slug</label>
                                <input v-model="form.slug" type="text" class="form-control" required>
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

        <!-- Modal de Permisos -->
        <div v-if="showPermissionsModal" class="modal d-block" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Gestionar Permisos - {{ editingPermissionsRole?.name }}</h5>
                        <button @click="closePermissionsModal" type="button" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <div v-if="loadingPermissions" class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </div>
                        <div v-else>
                            <div v-for="module in groupedPermissions" :key="module.name" class="mb-4">
                                <h6 class="border-bottom pb-2 mb-2">{{ module.name }}</h6>
                                <div class="row">
                                    <div v-for="permission in module.permissions" :key="permission.id" class="col-md-6">
                                        <div class="form-check">
                                            <input 
                                                class="form-check-input" 
                                                type="checkbox" 
                                                :id="'perm-' + permission.id"
                                                :value="permission.id"
                                                v-model="selectedPermissions"
                                            >
                                            <label class="form-check-label" :for="'perm-' + permission.id">
                                                {{ permission.name }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button @click="closePermissionsModal" type="button" class="btn btn-secondary">Cancelar</button>
                        <button @click="savePermissions" type="button" class="btn btn-primary" :disabled="savingPermissions">
                            <span v-if="savingPermissions" class="spinner-border spinner-border-sm me-1"></span>
                            Guardar Permisos
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="showPermissionsModal" class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, inject } from 'vue';
import { useAuthStore } from '../../../stores/auth';
import { toast } from '../../../utils/toast';
import api from '../../../services/api';
import DataTable from '../../../components/common/DataTable.vue';
import ActionMenu from '../../../components/common/ActionMenu.vue';

const authStore = useAuthStore();
const confirmDialog = inject('confirmDialog', null);

const roles = ref([]);
const permissions = ref([]);
const rolePermissions = ref({});
const showModal = ref(false);
const showPermissionsModal = ref(false);
const editingRole = ref(null);
const editingPermissionsRole = ref(null);
const loadingPermissions = ref(false);
const savingPermissions = ref(false);
const selectedPermissions = ref([]);

const columns = [
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Nombre' },
    { key: 'slug', label: 'Slug' }
];

const getRoleActions = (role) => {
    const actions = [];
    
    if (authStore.hasPermission('roles_update')) {
        actions.push({
            icon: 'bi bi-shield',
            title: 'Permisos',
            class: 'btn btn-sm btn-outline-secondary',
            handler: () => openPermissionsModal(role)
        });
    }
    
    if (authStore.hasPermission('roles_update')) {
        actions.push({
            icon: 'bi bi-pencil',
            title: 'Editar',
            class: 'btn btn-sm btn-outline-primary',
            handler: () => editRole(role)
        });
    }
    
    if (authStore.hasPermission('roles_delete')) {
        actions.push({
            icon: 'bi bi-trash',
            title: 'Eliminar',
            class: 'btn btn-sm btn-outline-danger',
            handler: () => deleteRole(role.id)
        });
    }
    
    return actions;
};

const form = reactive({
    name: '',
    slug: ''
});

const groupedPermissions = computed(() => {
    const groups = {};
    permissions.value.forEach(perm => {
        if (!groups[perm.module]) {
            groups[perm.module] = {
                name: perm.module.charAt(0).toUpperCase() + perm.module.slice(1),
                permissions: []
            };
        }
        groups[perm.module].permissions.push(perm);
    });
    return Object.values(groups);
});

const loadRoles = async () => {
    const response = await api.get('/roles');
    roles.value = response.data;
    
    const permsResponse = await api.get('/permissions');
    permissions.value = permsResponse.data;
    
    for (const role of roles.value) {
        try {
            const rpResponse = await api.get(`/roles/${role.id}/permissions`);
            rolePermissions.value[role.id] = rpResponse.data.permissions.map(p => p.id);
        } catch (e) {
            rolePermissions.value[role.id] = [];
        }
    }
};

const editRole = (role) => {
    editingRole.value = role;
    form.name = role.name;
    form.slug = role.slug;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingRole.value = null;
    form.name = '';
    form.slug = '';
};

const saveRole = async () => {
    try {
        if (editingRole.value) {
            await api.put(`/roles/${editingRole.value.id}`, form);
        } else {
            await api.post('/roles', form);
        }
        closeModal();
        loadRoles();
        toast.success('Rol guardado correctamente');
    } catch (error) {
        toast.error(error.response?.data?.message || 'Error al guardar');
    }
};

const openPermissionsModal = async (role) => {
    editingPermissionsRole.value = role;
    showPermissionsModal.value = true;
    loadingPermissions.value = true;
    selectedPermissions.value = [];
    
    try {
        const response = await api.get(`/roles/${role.id}/permissions`);
        selectedPermissions.value = response.data.permissions.map(p => p.id);
    } catch (error) {
        toast.error('Error al cargar permisos');
    } finally {
        loadingPermissions.value = false;
    }
};

const closePermissionsModal = () => {
    showPermissionsModal.value = false;
    editingPermissionsRole.value = null;
    selectedPermissions.value = [];
};

const savePermissions = async () => {
    if (!editingPermissionsRole.value) return;
    
    savingPermissions.value = true;
    try {
        await api.put(`/roles/${editingPermissionsRole.value.id}/permissions`, {
            permission_ids: selectedPermissions.value
        });
        rolePermissions.value[editingPermissionsRole.value.id] = [...selectedPermissions.value];
        closePermissionsModal();
        toast.success('Permisos actualizados correctamente');
    } catch (error) {
        toast.error(error.response?.data?.message || 'Error al guardar permisos');
    } finally {
        savingPermissions.value = false;
    }
};

const deleteRole = async (id) => {
    let confirmed = true;
    if (confirmDialog.value) {
        confirmed = await confirmDialog.value.open({
            title: 'Eliminar Rol',
            message: '¿Está seguro de eliminar este rol?',
            confirmText: 'Eliminar',
            confirmClass: 'btn-danger'
        });
    }
    
    if (confirmed) {
        try {
            await api.delete(`/roles/${id}`);
            loadRoles();
            toast.success('Rol eliminado correctamente');
        } catch (error) {
            toast.error(error.response?.data?.message || 'Error al eliminar');
        }
    }
};

onMounted(loadRoles);
</script>