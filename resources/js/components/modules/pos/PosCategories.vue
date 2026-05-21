<template>
    <div class="pos-categories-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Categorías</h5>
            <button class="btn btn-sm btn-primary" @click="showModal = true">
                <i class="bi bi-plus"></i> Nueva
            </button>
        </div>
        
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Por defecto</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="category in categories" :key="category.id">
                        <td>{{ category.name }}</td>
                        <td>{{ category.default ? 'Sí' : 'No' }}</td>
                        <td>
                            <div class="form-check form-switch">
                                <input 
                                    class="form-check-input" 
                                    type="checkbox" 
                                    :checked="category.enable === 1 || category.enable === true"
                                    @change="toggleCategoryStatus(category.id, category.enable)"
                                >
                            </div>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-primary" @click="editCategory(category)" title="Editar">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" @click="deleteCategory(category.id)" title="Eliminar">
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
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingCategory ? 'Editar' : 'Nueva' }} Categoría</h5>
                        <button @click="closeModal" type="button" class="btn-close"></button>
                    </div>
                    <form @submit.prevent="saveCategory">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input v-model="form.name" type="text" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input v-model="form.default" class="form-check-input" type="checkbox" id="default">
                                    <label class="form-check-label" for="default">Por defecto</label>
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

const categories = ref([]);
const showModal = ref(false);
const editingCategory = ref(null);
const confirmModal = ref(null);

const form = reactive({
    name: '',
    default: false,
    enable: true
});

const loadData = async () => {
    try {
        const response = await api.get('/pos/categories');
        categories.value = response.data;
    } catch (error) {
    }
};

const editCategory = (category) => {
    editingCategory.value = category;
    form.name = category.name;
    form.default = Boolean(category.default);
    form.enable = category.enable;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingCategory.value = null;
    form.name = '';
    form.default = false;
    form.enable = true;
};

const saveCategory = async () => {
    try {
        if (editingCategory.value) {
            await api.put(`/pos/categories/${editingCategory.value.id}`, form);
        } else {
            await api.post('/pos/categories', form);
        }
        closeModal();
        loadData();
    } catch (error) {
    }
};

const deleteCategory = async (id) => {
    confirmModal.value.open({
        title: 'Confirmar Eliminación',
        message: '¿Está seguro de eliminar esta categoría?',
        confirmText: 'Eliminar',
        type: 'danger',
        onConfirm: async () => {
            try {
                await api.delete(`/pos/categories/${id}`);
                loadData();
                toastify.success('Categoría eliminada');
            } catch (error) {
                toastify.error('Error al eliminar categoría');
            }
        }
    });
};

const toggleCategoryStatus = async (id, currentStatus) => {
    try {
        await api.post(`/pos/categories/${id}/toggle-status`);
        loadData();
        toastify.success(`Categoría ${currentStatus ? 'deshabilitada' : 'habilitada'} correctamente`);
    } catch (error) {
        loadData();
        toastify.error('Error al cambiar estado de la categoría');
    }
};

onMounted(loadData);

window.addEventListener('pos-category-changed', () => {
    loadData();
});
</script>

<style scoped>
.pos-categories-container {
    height: 100%;
    display: flex;
    flex-direction: column;
    background: #f8f9fa;
    padding: 1rem;
    overflow: auto;
}
</style>
