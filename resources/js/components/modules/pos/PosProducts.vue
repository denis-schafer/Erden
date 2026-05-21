<template>
    <div class="pos-products-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Productos</h5>
            <button class="btn btn-sm btn-primary" @click="openCreateModal">
                <i class="bi bi-plus"></i> Nuevo
            </button>
        </div>

        <div class="pos-categories-bar mb-3">
            <button 
                v-for="category in categories" 
                :key="category.id"
                class="category-tab"
                :class="{ 'active': selectedCategory === category.id }"
                @click="selectedCategory = category.id"
            >
                {{ category.name }}
            </button>
        </div>

        <div class="pos-products-list" style="position: relative;">
            <div v-if="loading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>
            <template v-else>
                <div 
                    v-for="(product, index) in filteredProducts" 
                    :key="product.id"
                    class="product-item"
                    draggable="true"
                    @dragstart="dragStart(index)"
                    @dragover.prevent
                    @drop="drop(index)"
                    @dragend="dragEnd"
                >
                    <div class="product-drag-handle">
                        <i class="bi bi-grip-vertical"></i>
                    </div>
                    <div class="product-info">
                        <div class="product-name">{{ product.name }}</div>
                        <div class="product-price">${{ Number(product.amount).toFixed(2) }}</div>
                    </div>
                    <div class="product-status">
                        <div class="form-check form-switch">
                            <input 
                                class="form-check-input" 
                                type="checkbox" 
                                :checked="product.enable === 1 || product.enable === true"
                                :disabled="loadingActions['toggle-' + product.id]"
                                @change="toggleProductStatus(product.id, product.enable)"
                            >
                        </div>
                    </div>
                    <div class="product-actions d-flex gap-1">
                        <button class="btn btn-sm btn-primary" :disabled="loadingActions['edit-' + product.id]" @click="editProduct(product)" title="Editar">
                            <span v-if="loadingActions['edit-' + product.id]" class="spinner-border spinner-border-sm"></span>
                            <i v-else class="bi bi-pencil-fill"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" :disabled="loadingActions['delete-' + product.id]" @click="deleteProduct(product.id)" title="Eliminar">
                            <span v-if="loadingActions['delete-' + product.id]" class="spinner-border spinner-border-sm"></span>
                            <i v-else class="bi bi-trash-fill"></i>
                        </button>
                    </div>
                </div>
                <div v-if="filteredProducts.length === 0" class="text-center text-muted py-4">
                    No hay productos en esta categoría
                </div>
            </template>
        </div>

        <ConfirmModal ref="confirmModal" />

        <div v-if="showModal" class="modal d-block" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingProduct ? 'Editar' : 'Nuevo' }} Producto</h5>
                        <button @click="closeModal" type="button" class="btn-close"></button>
                    </div>
                    <form @submit.prevent="saveProduct">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input v-model="form.name" type="text" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Categoría</label>
                                <select v-model="form.category_id" class="form-select" required>
                                    <option value="">Seleccionar...</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                        {{ cat.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Precio</label>
                                <input v-model.number="form.amount" type="number" step="0.01" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descripción corta</label>
                                <input v-model="form.short_description" type="text" class="form-control">
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
import { ref, reactive, onMounted, computed } from 'vue';
import api from '../../../services/api';
import { toast as toastify } from '../../../utils/toast';
import ConfirmModal from '../../../components/ConfirmModal.vue';

const products = ref([]);
const categories = ref([]);
const selectedCategory = ref(null);
const showModal = ref(false);
const editingProduct = ref(null);
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

const draggedIndex = ref(null);

const form = reactive({
    name: '',
    category_id: '',
    amount: 0,
    short_description: '',
    enable: true
});

const filteredProducts = computed(() => {
    if (!selectedCategory.value) return products.value;
    return products.value
        .filter(p => p.category_id === selectedCategory.value)
        .sort((a, b) => (a.order || 0) - (b.order || 0));
});

const loadData = async () => {
    loading.value = true;
    try {
        const [productsRes, categoriesRes] = await Promise.all([
            api.get('/pos/products'),
            api.get('/pos/categories')
        ]);
        products.value = productsRes.data;
        categories.value = categoriesRes.data.filter(c => c.enable);
        
        if (categories.value.length > 0 && !selectedCategory.value) {
            const defaultCategory = categories.value.find(c => c.default);
            selectedCategory.value = defaultCategory ? defaultCategory.id : categories.value[0].id;
        }
    } catch (error) {
    } finally {
        loading.value = false;
    }
};

const openCreateModal = () => {
    editingProduct.value = null;
    form.name = '';
    form.category_id = selectedCategory.value || '';
    form.amount = 0;
    form.short_description = '';
    form.enable = true;
    showModal.value = true;
};

const editProduct = (product) => {
    editingProduct.value = product;
    form.name = product.name;
    form.category_id = product.category_id;
    form.amount = product.amount;
    form.short_description = product.short_description || '';
    form.enable = product.enable;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingProduct.value = null;
    form.name = '';
    form.category_id = '';
    form.amount = 0;
    form.short_description = '';
    form.enable = true;
};

const saveProduct = async () => {
    saving.value = true;
    try {
        if (editingProduct.value) {
            await api.put(`/pos/products/${editingProduct.value.id}`, form);
        } else {
            const maxOrder = Math.max(...filteredProducts.value.map(p => p.order || 0), 0);
            await api.post('/pos/products', { ...form, order: maxOrder + 1 });
        }
        closeModal();
        loadData();
    } catch (error) {
    } finally {
        saving.value = false;
    }
};

const deleteProduct = async (id) => {
    confirmModal.value.open({
        title: 'Confirmar Eliminación',
        message: '¿Está seguro de eliminar este producto?',
        confirmText: 'Eliminar',
        type: 'danger',
        onConfirm: () => withLoading('delete-' + id, async () => {
            try {
                await api.delete(`/pos/products/${id}`);
                loadData();
                toastify.success('Producto eliminado');
            } catch (error) {
                toastify.error('Error al eliminar producto');
            }
        })
    });
};

const toggleProductStatus = async (id, currentStatus) => {
    await withLoading('toggle-' + id, async () => {
        try {
            await api.post(`/pos/products/${id}/toggle-status`);
            loadData();
            toastify.success(`Producto ${currentStatus ? 'deshabilitado' : 'habilitado'} correctamente`);
        } catch (error) {
            loadData();
            toastify.error('Error al cambiar estado del producto');
        }
    });
};

const dragStart = (index) => {
    draggedIndex.value = index;
};

const drop = async (dropIndex) => {
    if (draggedIndex.value === null || draggedIndex.value === dropIndex) return;
    
    const newProducts = [...filteredProducts.value];
    const [movedItem] = newProducts.splice(draggedIndex.value, 1);
    newProducts.splice(dropIndex, 0, movedItem);
    
    const reorderData = newProducts.map((p, index) => ({
        id: p.id,
        order: index
    }));
    
    products.value = newProducts;
    
    try {
        await api.post('/pos/products/reorder', { orders: reorderData });
    } catch (error) {
        loadData();
    }
    
    draggedIndex.value = null;
};

const dragEnd = () => {
    draggedIndex.value = null;
};

onMounted(loadData);

window.addEventListener('pos-product-changed', () => {
    loadData();
});

window.addEventListener('pos-product-reordered', () => {
    loadData();
});
</script>

<style scoped>
.pos-products-container {
    height: 100%;
    display: flex;
    flex-direction: column;
    background: #f8f9fa;
    padding: 1rem;
    overflow: auto;
}

.pos-categories-bar {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding: 0.5rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.category-tab {
    padding: 0.5rem 1rem;
    border: none;
    background: #e9ecef;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
}

.category-tab:hover {
    background: #dee2e6;
}

.category-tab.active {
    background: #0d6efd;
    color: white;
}

.pos-products-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.product-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    cursor: grab;
    transition: all 0.2s;
}

.product-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.product-item:active {
    cursor: grabbing;
}

.product-drag-handle {
    color: #adb5bd;
    margin-right: 0.75rem;
    cursor: grab;
}

.product-drag-handle:hover {
    color: #6c757d;
}

.product-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.product-name {
    font-weight: 500;
    color: #212529;
}

.product-price {
    color: #198754;
    font-weight: 600;
}

.product-status {
    margin: 0 1rem;
}

.product-actions {
    display: flex;
    gap: 0.25rem;
}

.product-actions .btn {
    padding: 0.25rem 0.5rem;
}
</style>