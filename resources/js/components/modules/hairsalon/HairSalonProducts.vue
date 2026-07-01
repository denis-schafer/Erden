<template>
    <div class="hairsalon-products p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Productos</h4>
            <button class="btn btn-primary btn-sm" @click="openForm()"><i class="bi bi-plus"></i> Nuevo Producto</button>
        </div>
        <div class="row mb-3"><div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" id="lowStock" v-model="lowStock" @change="loadProducts">
            <label class="form-check-label" for="lowStock">Solo stock bajo</label></div></div></div>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <div v-else>
            <DataTable :data="displayProducts" :columns="columns" :per-page="15">
                <template #rowActions="{ row }">
                    <button class="btn btn-sm btn-outline-success me-1" @click="adjustStock(row, 'in')" title="Agregar stock"><i class="bi bi-plus-circle"></i></button>
                    <button class="btn btn-sm btn-outline-warning me-1" @click="adjustStock(row, 'out')" title="Quitar stock"><i class="bi bi-dash-circle"></i></button>
                    <button class="btn btn-sm btn-outline-primary me-1" @click="openForm(row)"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" @click="confirmDeleteProduct(row)"><i class="bi bi-trash"></i></button>
                </template>
            </DataTable>
        </div>

        <div v-if="showModal" class="modal d-block"><div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">{{ editing ? 'Editar' : 'Nuevo' }} Producto</h5><button class="btn-close" @click="showModal = false"></button></div>
            <form @submit.prevent="saveProduct"><div class="modal-body">
                <div class="mb-2"><label class="form-label">Nombre</label><input v-model="form.name" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label">Descripción</label><textarea v-model="form.description" class="form-control form-control-sm" rows="2"></textarea></div>
                <div class="row"><div class="col-4 mb-2"><label class="form-label">Cantidad</label><input v-model.number="form.quantity" class="form-control form-control-sm" type="number" step="0.01" min="0" required></div>
                <div class="col-4 mb-2"><label class="form-label">Stock Mín.</label><input v-model.number="form.min_stock" class="form-control form-control-sm" type="number" step="0.01" min="0" required></div>
                <div class="col-4 mb-2"><label class="form-label">Precio</label><input v-model.number="form.price" class="form-control form-control-sm" type="number" step="0.01" min="0" required></div></div>
                <div class="mb-2"><label class="form-label">Categoría</label>
                    <div class="position-relative"><input v-model="catSearch" class="form-control form-control-sm" placeholder="Buscar categoría..." @input="catDropdown = true" @focus="catDropdown = true" @blur="onCatBlur" ref="catInputRef">
                    <div v-if="selectedCatName" class="mt-1 p-1 border rounded bg-light d-flex justify-content-between align-items-center">
                        <small><strong>{{ selectedCatName }}</strong></small><button class="btn btn-sm p-0 text-danger" @click="form.category_id = null; catSearch = ''; selectedCatName = null"><i class="bi bi-x"></i></button></div>
                    <div v-if="catDropdown && filteredCategories.length" class="position-absolute w-100 border rounded bg-white shadow-sm" style="max-height:180px;overflow-y:auto;z-index:1050">
                        <div v-for="c in filteredCategories" :key="c.id" class="px-2 py-1" @mousedown.prevent="selectCategory(c)" style="cursor:pointer;border-bottom:1px solid #f0f0f0" @mouseover="catHover = c.id" :class="catHover === c.id ? 'bg-primary text-white' : ''">{{ c.name }}</div></div></div>
                </div>
                <div class="mb-2"><label class="form-label">Servicio vinculado</label>
                    <div class="position-relative"><input v-model="svcSearch" class="form-control form-control-sm" placeholder="Buscar servicio..." @input="svcDropdown = true" @focus="svcDropdown = true" @blur="onSvcBlur" ref="svcInputRef">
                    <div v-if="selectedSvcName" class="mt-1 p-1 border rounded bg-light d-flex justify-content-between align-items-center">
                        <small><strong>{{ selectedSvcName }}</strong></small><button class="btn btn-sm p-0 text-danger" @click="form.service_id = null; svcSearch = ''; selectedSvcName = null"><i class="bi bi-x"></i></button></div>
                    <div v-if="svcDropdown && filteredServices.length" class="position-absolute w-100 border rounded bg-white shadow-sm" style="max-height:180px;overflow-y:auto;z-index:1050">
                        <div v-for="s in filteredServices" :key="s.id" class="px-2 py-1" @mousedown.prevent="selectService(s)" style="cursor:pointer;border-bottom:1px solid #f0f0f0" @mouseover="svcHover = s.id" :class="svcHover === s.id ? 'bg-primary text-white' : ''">{{ s.name }}</div></div></div>
                    <small class="text-muted">Al cobrar este servicio en Caja se descontará este producto</small>
                </div>
            </div><div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" @click="showModal = false">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ saving ? 'Guardando...' : 'Guardar' }}</button>
            </div></form>
        </div></div></div>
        <div v-if="showModal" class="modal-backdrop fade show"></div>

        <div v-if="showStockModal" class="modal d-block"><div class="modal-dialog modal-sm"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">{{ stockType === 'in' ? 'Agregar' : 'Quitar' }} Stock</h5><button class="btn-close" @click="showStockModal = false"></button></div>
            <form @submit.prevent="saveStock"><div class="modal-body">
                <p><strong>{{ stockProduct?.name }}</strong> - Stock actual: {{ stockProduct?.quantity }}</p>
                <div class="mb-2"><label class="form-label">Cantidad</label><input v-model.number="stockQuantity" class="form-control form-control-sm" type="number" step="0.01" min="0.01" required></div>
                <div class="mb-2"><label class="form-label">Motivo</label><input v-model="stockReason" class="form-control form-control-sm" placeholder="Ej: Compra a proveedor"></div>
            </div><div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" @click="showStockModal = false">Cancelar</button>
                <button type="submit" class="btn btn-sm" :class="stockType === 'in' ? 'btn-success' : 'btn-warning'">{{ savingStock ? 'Guardando...' : 'Confirmar' }}</button>
            </div></form>
        </div></div></div>
        <div v-if="showStockModal" class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, inject } from 'vue';
import api from '../../../services/api';
import DataTable from '../../../components/common/DataTable.vue';
import { toast } from '../../../utils/toast';

const confirmDialog = inject('confirmDialog', null);
const loading = ref(true);
const allProducts = ref([]);
const lowStock = ref(false);
const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const form = reactive({ name: '', description: '', quantity: 0, min_stock: 0, price: 0, category_id: null, service_id: null });
const showStockModal = ref(false);
const stockProduct = ref(null);
const stockType = ref('in');
const stockQuantity = ref(0);
const stockReason = ref('');
const savingStock = ref(false);
const categories = ref([]);
const services = ref([]);
const catSearch = ref('');
const catDropdown = ref(false);
const catHover = ref(null);
const catInputRef = ref(null);
const selectedCatName = ref(null);
const svcSearch = ref('');
const svcDropdown = ref(false);
const svcHover = ref(null);
const svcInputRef = ref(null);
const selectedSvcName = ref(null);

const filteredCategories = computed(() => {
    const s = catSearch.value.toLowerCase();
    return categories.value.filter(c => !s || c.name.toLowerCase().includes(s));
});
const filteredServices = computed(() => {
    const s = svcSearch.value.toLowerCase();
    return services.value.filter(sv => !s || sv.name.toLowerCase().includes(s));
});

const displayProducts = computed(() => {
    if (!lowStock.value) return allProducts.value;
    return allProducts.value.filter(p => Number(p.quantity) <= Number(p.min_stock));
});

const columns = [
    { key: 'name', label: 'Nombre' },
    { key: 'category_name', label: 'Categoría' },
    { key: 'service_name', label: 'Servicio' },
    { key: 'quantity', label: 'Stock' },
    { key: 'min_stock', label: 'Stock Mín.' },
    { key: 'price', label: 'Precio' },
];

const onCatBlur = () => { setTimeout(() => { catDropdown.value = false; }, 200); };
const onSvcBlur = () => { setTimeout(() => { svcDropdown.value = false; }, 200); };
const selectCategory = (c) => { form.category_id = c.id; selectedCatName.value = c.name; catSearch.value = c.name; catDropdown.value = false; };
const selectService = (s) => { form.service_id = s.id; selectedSvcName.value = s.name; svcSearch.value = s.name; svcDropdown.value = false; };

const loadProducts = async () => {
    loading.value = true;
    try {
        const res = await api.get('/hairsalon/products', { params: { per_page: 500 } });
        const data = res.data;
        allProducts.value = (data.products || data).data || data.data || [];
        categories.value = data.categories || [];
        services.value = data.services || [];
    } finally { loading.value = false; }
};

const openForm = (product) => {
    editing.value = product || null;
    form.name = product?.name || '';
    form.description = product?.description || '';
    form.quantity = product?.quantity || 0;
    form.min_stock = product?.min_stock || 0;
    form.price = product?.price || 0;
    form.category_id = product?.category_id || null;
    form.service_id = product?.service_id || null;
    const cat = (categories.value || []).find(c => c.id === product?.category_id);
    selectedCatName.value = cat?.name || null; catSearch.value = cat?.name || '';
    const svc = (services.value || []).find(s => s.id === product?.service_id);
    selectedSvcName.value = svc?.name || null; svcSearch.value = svc?.name || '';
    showModal.value = true;
};

const saveProduct = async () => {
    saving.value = true;
    try {
        if (editing.value) { await api.put('/hairsalon/products/' + editing.value.id, form); toast.success('Producto actualizado'); }
        else { await api.post('/hairsalon/products', form); toast.success('Producto creado'); }
        showModal.value = false;
        loadProducts();
    } catch (e) { toast.error(e.response?.data?.message || 'Error'); }
    finally { saving.value = false; }
};

const confirmDeleteProduct = async (product) => {
    if (confirmDialog && confirmDialog.value) {
        const confirmed = await confirmDialog.value.open({ title: 'Eliminar Producto', message: `¿Está seguro de eliminar "${product.name}"?`, confirmText: 'Eliminar', confirmClass: 'btn-danger' });
        if (!confirmed) return;
    } else { if (!confirm('¿Está seguro de eliminar este producto?')) return; }
    try { await api.delete('/hairsalon/products/' + product.id); toast.success('Producto eliminado'); loadProducts(); }
    catch (e) { toast.error(e.response?.data?.message || 'Error'); }
};

const adjustStock = (product, type) => { stockProduct.value = product; stockType.value = type; stockQuantity.value = 0; stockReason.value = ''; showStockModal.value = true; };

const saveStock = async () => {
    savingStock.value = true;
    try {
        await api.post('/hairsalon/products/' + stockProduct.value.id + '/stock', { type: stockType.value, quantity: stockQuantity.value, reason: stockReason.value });
        toast.success('Stock actualizado'); showStockModal.value = false; loadProducts();
    } catch (e) { toast.error(e.response?.data?.message || 'Error'); }
    finally { savingStock.value = false; }
};

const handleChanged = () => { loadProducts(); };
onMounted(() => { loadProducts(); window.addEventListener('hairsalon-product-changed', handleChanged); window.addEventListener('hairsalon-stock-changed', handleChanged); });
onUnmounted(() => { window.removeEventListener('hairsalon-product-changed', handleChanged); window.removeEventListener('hairsalon-stock-changed', handleChanged); });
</script>
