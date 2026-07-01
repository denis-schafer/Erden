<template>
    <div class="hairsalon-services p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Servicios</h4>
            <div><button class="btn btn-outline-secondary btn-sm me-2" @click="showCategoryModal = true; categoryForm.name = ''"><i class="bi bi-folder-plus"></i> Categoría</button>
            <button class="btn btn-primary btn-sm" @click="openServiceForm()"><i class="bi bi-plus"></i> Nuevo Servicio</button></div>
        </div>

        <!-- Category badges -->
        <div class="mb-3 d-flex flex-wrap gap-1">
            <span v-for="cat in categories" :key="cat.id" class="badge bg-secondary me-1">
                {{ cat.name }}
                <button class="btn btn-sm p-0 ms-1 text-white" @click="editCategory(cat)"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm p-0 ms-1 text-white" @click="confirmDeleteCategory(cat)"><i class="bi bi-trash"></i></button>
            </span>
        </div>

        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <div v-else>
            <DataTable :data="services" :columns="columns" :per-page="15">
                <template #rowActions="{ row }">
                    <button class="btn btn-sm btn-outline-primary me-1" @click="openServiceForm(row)"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" @click="confirmDeleteService(row)"><i class="bi bi-trash"></i></button>
                </template>
            </DataTable>
        </div>

        <!-- Service Modal -->
        <div v-if="showServiceModal" class="modal d-block"><div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">{{ editingService ? 'Editar' : 'Nuevo' }} Servicio</h5><button class="btn-close" @click="showServiceModal = false"></button></div>
            <form @submit.prevent="saveService"><div class="modal-body">
                <div class="mb-2"><label class="form-label">Nombre</label><input v-model="serviceForm.name" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label">Descripción</label><textarea v-model="serviceForm.description" class="form-control form-control-sm" rows="2"></textarea></div>
                <div class="mb-2"><label class="form-label">Precio</label><input v-model.number="serviceForm.price" class="form-control form-control-sm" type="number" step="0.01" min="0" required></div>
                <div class="mb-2"><label class="form-label">Duración (min)</label><input v-model.number="serviceForm.duration_min" class="form-control form-control-sm" type="number" min="0"></div>
                <div class="mb-2"><label class="form-label">Categoría</label>
                    <div class="position-relative"><input v-model="catSearchSvc" class="form-control form-control-sm" placeholder="Buscar categoría..." @input="catSvcDropdown = true" @focus="catSvcDropdown = true" @blur="onCatSvcBlur" ref="catSvcInputRef">
                    <div v-if="selectedCatSvcName" class="mt-1 p-1 border rounded bg-light d-flex justify-content-between align-items-center">
                        <small><strong>{{ selectedCatSvcName }}</strong></small><button class="btn btn-sm p-0 text-danger" @click="serviceForm.category_id = null; catSearchSvc = ''; selectedCatSvcName = null"><i class="bi bi-x"></i></button></div>
                    <div v-if="catSvcDropdown && filteredCatSvc.length" class="position-absolute w-100 border rounded bg-white shadow-sm" style="max-height:180px;overflow-y:auto;z-index:1050">
                        <div v-for="c in filteredCatSvc" :key="c.id" class="px-2 py-1" @mousedown.prevent="selectCatSvc(c)" style="cursor:pointer;border-bottom:1px solid #f0f0f0" @mouseover="catSvcHover = c.id" :class="catSvcHover === c.id ? 'bg-primary text-white' : ''">{{ c.name }}</div></div></div>
                </div>
                <div class="mb-2"><label class="form-label">Producto a descontar del stock</label>
                    <div class="position-relative"><input v-model="prodSearchSvc" class="form-control form-control-sm" placeholder="Buscar producto..." @input="prodSvcDropdown = true" @focus="prodSvcDropdown = true" @blur="onProdSvcBlur" ref="prodSvcInputRef">
                    <div v-if="selectedProdSvcName" class="mt-1 p-1 border rounded bg-light d-flex justify-content-between align-items-center">
                        <small><strong>{{ selectedProdSvcName }}</strong></small><button class="btn btn-sm p-0 text-danger" @click="serviceForm.product_id = null; prodSearchSvc = ''; selectedProdSvcName = null"><i class="bi bi-x"></i></button></div>
                    <div v-if="prodSvcDropdown && filteredProdSvc.length" class="position-absolute w-100 border rounded bg-white shadow-sm" style="max-height:180px;overflow-y:auto;z-index:1050">
                        <div v-for="p in filteredProdSvc" :key="p.id" class="px-2 py-1" @mousedown.prevent="selectProdSvc(p)" style="cursor:pointer;border-bottom:1px solid #f0f0f0" @mouseover="prodSvcHover = p.id" :class="prodSvcHover === p.id ? 'bg-primary text-white' : ''">{{ p.name }}</div></div></div>
                    <small class="text-muted">Al cobrar este servicio se descontará stock de este producto</small>
                </div>
            </div><div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" @click="showServiceModal = false">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ savingService ? 'Guardando...' : 'Guardar' }}</button>
            </div></form>
        </div></div></div>
        <div v-if="showServiceModal" class="modal-backdrop fade show"></div>

        <!-- Category Modal -->
        <div v-if="showCategoryModal" class="modal d-block"><div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">{{ editingCategory ? 'Editar' : 'Nueva' }} Categoría</h5><button class="btn-close" @click="showCategoryModal = false"></button></div>
            <form @submit.prevent="saveCategory"><div class="modal-body">
                <div class="mb-2"><label class="form-label">Nombre</label><input v-model="categoryForm.name" class="form-control form-control-sm" required></div>
            </div><div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" @click="showCategoryModal = false">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ savingCategory ? 'Guardando...' : 'Guardar' }}</button>
            </div></form>
        </div></div></div>
        <div v-if="showCategoryModal" class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted, onUnmounted, inject } from 'vue';
import api from '../../../services/api';
import DataTable from '../../../components/common/DataTable.vue';
import { useCache } from '../../../composables/useCache';
import { toast } from '../../../utils/toast';

const { fetch, refresh } = useCache();
const confirmDialog = inject('confirmDialog', null);
const loading = ref(true);
const categories = ref([]);
const services = ref([]);
const products = ref([]);

const showServiceModal = ref(false);
const editingService = ref(null);
const savingService = ref(false);
const serviceForm = reactive({ name: '', description: '', price: 0, duration_min: null, category_id: null, product_id: null });
const showCategoryModal = ref(false);
const editingCategory = ref(null);
const savingCategory = ref(false);
const categoryForm = reactive({ name: '' });

const prodSearchSvc = ref('');
const catSearchSvc = ref('');
const catSvcDropdown = ref(false);
const catSvcHover = ref(null);
const catSvcInputRef = ref(null);
const selectedCatSvcName = ref(null);
const prodSvcDropdown = ref(false);
const prodSvcHover = ref(null);
const prodSvcInputRef = ref(null);
const selectedProdSvcName = ref(null);

const filteredCatSvc = computed(() => {
    const s = catSearchSvc.value.toLowerCase();
    return categories.value.filter(c => !s || c.name.toLowerCase().includes(s));
});
const filteredProdSvc = computed(() => {
    const s = prodSearchSvc.value.toLowerCase();
    return products.value.filter(p => !s || p.name.toLowerCase().includes(s));
});

const onCatSvcBlur = () => { setTimeout(() => { catSvcDropdown.value = false; }, 200); };
const onProdSvcBlur = () => { setTimeout(() => { prodSvcDropdown.value = false; }, 200); };
const selectCatSvc = (c) => { serviceForm.category_id = c.id; selectedCatSvcName.value = c.name; catSearchSvc.value = c.name; catSvcDropdown.value = false; };
const selectProdSvc = (p) => { serviceForm.product_id = p.id; selectedProdSvcName.value = p.name; prodSearchSvc.value = p.name; prodSvcDropdown.value = false; };

const columns = [
    { key: 'name', label: 'Nombre' },
    { key: 'category_name', label: 'Categoría' },
    { key: 'price', label: 'Precio' },
    { key: 'duration_min', label: 'Duración (min)' },
];

const cacheKey = 'hairsalon-services';
const fetcher = () => api.get('/hairsalon/services').then(r => r.data);

const loadData = async () => {
    loading.value = true;
    try {
        const data = await fetch(cacheKey, fetcher);
        categories.value = data.categories || [];
        services.value = data.services || [];
        products.value = data.products || [];
    } finally { loading.value = false; }
};

const refreshData = async () => {
    try {
        const data = await refresh(cacheKey, fetcher);
        categories.value = data.categories || [];
        services.value = data.services || [];
        products.value = data.products || [];
    } finally { /* silent */ }
};

const formatNumber = (n) => Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const openServiceForm = async (svc) => {
    const data = await refresh(cacheKey, () => api.get('/hairsalon/services').then(r => r.data));
    categories.value = data.categories || []; products.value = data.products || [];
    editingService.value = svc || null;
    serviceForm.name = svc?.name || ''; serviceForm.description = svc?.description || '';
    serviceForm.price = svc?.price || 0; serviceForm.duration_min = svc?.duration_min || null;
    serviceForm.category_id = svc?.category_id || null; serviceForm.product_id = svc?.product_id || null;
    const cat = categories.value.find(c => c.id === svc?.category_id);
    selectedCatSvcName.value = cat?.name || null; catSearchSvc.value = cat?.name || '';
    const prod = products.value.find(p => p.id === svc?.product_id);
    selectedProdSvcName.value = prod?.name || null; prodSearchSvc.value = prod?.name || '';
    showServiceModal.value = true;
};

const saveService = async () => {
    savingService.value = true;
    try {
        if (editingService.value) { await api.put('/hairsalon/services/' + editingService.value.id, serviceForm); toast.success('Servicio actualizado'); }
        else { await api.post('/hairsalon/services', serviceForm); toast.success('Servicio creado'); }
        showServiceModal.value = false; await refreshData();
    } catch (e) { toast.error(e.response?.data?.message || 'Error'); }
    finally { savingService.value = false; }
};

const confirmDeleteService = async (svc) => {
    if (confirmDialog && confirmDialog.value) {
        const confirmed = await confirmDialog.value.open({ title: 'Eliminar Servicio', message: `¿Está seguro de eliminar el servicio "${svc.name}"?`, confirmText: 'Eliminar', confirmClass: 'btn-danger' });
        if (!confirmed) return;
    } else { if (!confirm('¿Está seguro de eliminar este servicio?')) return; }
    try { await api.delete('/hairsalon/services/' + svc.id); toast.success('Servicio eliminado'); await refreshData(); }
    catch (e) { toast.error(e.response?.data?.message || 'Error'); }
};

const editCategory = (cat) => { editingCategory.value = cat; categoryForm.name = cat.name; showCategoryModal.value = true; };

const saveCategory = async () => {
    savingCategory.value = true;
    try {
        if (editingCategory.value) { await api.put('/hairsalon/services/categories/' + editingCategory.value.id, categoryForm); toast.success('Categoría actualizada'); }
        else { await api.post('/hairsalon/services/categories', categoryForm); toast.success('Categoría creada'); }
        showCategoryModal.value = false; await refreshData();
    } catch (e) { toast.error(e.response?.data?.message || 'Error'); }
    finally { savingCategory.value = false; }
};

const confirmDeleteCategory = async (cat) => {
    if (confirmDialog && confirmDialog.value) {
        const confirmed = await confirmDialog.value.open({ title: 'Eliminar Categoría', message: `¿Está seguro de eliminar la categoría "${cat.name}"?`, confirmText: 'Eliminar', confirmClass: 'btn-danger' });
        if (!confirmed) return;
    } else { if (!confirm('¿Está seguro de eliminar esta categoría?')) return; }
    try { await api.delete('/hairsalon/services/categories/' + cat.id); toast.success('Categoría eliminada'); await refreshData(); }
    catch (e) { toast.error(e.response?.data?.message || 'Error'); }
};

const handleChanged = () => { refreshData(); };
onMounted(() => { loadData(); window.addEventListener('hairsalon-service-changed', handleChanged); window.addEventListener('hairsalon-product-changed', handleChanged); });
onUnmounted(() => { window.removeEventListener('hairsalon-service-changed', handleChanged); window.removeEventListener('hairsalon-product-changed', handleChanged); });
</script>
