<template>
    <div class="pos-admin-container">
        <div class="pos-admin-header">
            <button v-if="isMobile" class="btn btn-outline-secondary me-2" @click="$emit('back')">
                <i class="bi bi-arrow-left"></i>
            </button>
            <h5 class="mb-0">Administración</h5>
        </div>

        <div class="pos-admin-tabs">
            <button 
                v-for="tab in tabs" 
                :key="tab.id"
                class="tab-btn"
                :class="{ 'active': activeTab === tab.id }"
                @click="activeTab = tab.id"
            >
                {{ tab.label }}
            </button>
        </div>

        <div class="pos-admin-content">
            <!-- Productos -->
            <div v-if="activeTab === 'products'" class="tab-content">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Productos</h6>
                    <button class="btn btn-sm btn-primary" @click="showProductModal = true">
                        <i class="bi bi-plus"></i> Nuevo
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Categoría</th>
                                <th>Precio</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="product in products" :key="product.id">
                                <td>{{ product.name }}</td>
                                <td>{{ product.category_name }}</td>
                                <td>${{ Number(product.amount).toFixed(2) }}</td>
                                <td>
                                    <span :class="product.enable ? 'badge bg-success' : 'badge bg-secondary'">
                                        {{ product.enable ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-primary" @click="editProduct(product)" title="Editar">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" @click="deleteProduct(product.id)" title="Eliminar">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Categorías -->
            <div v-if="activeTab === 'categories'" class="tab-content">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Categorías</h6>
                    <button class="btn btn-sm btn-primary" @click="showCategoryModal = true">
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
                                <td>
                                    <span :class="category.default ? 'badge bg-primary' : 'badge bg-secondary'">
                                        {{ category.default ? 'Sí' : 'No' }}
                                    </span>
                                </td>
                                <td>
                                    <span :class="category.enable ? 'badge bg-success' : 'badge bg-secondary'">
                                        {{ category.enable ? 'Activa' : 'Inactiva' }}
                                    </span>
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
            </div>

            <!-- Usuarios -->
            <div v-if="activeTab === 'users'" class="tab-content">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Usuarios</h6>
                    <button class="btn btn-sm btn-primary" @click="showUserModal = true">
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
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="u in users" :key="u.id">
                                <td>{{ u.name }}</td>
                                <td>{{ u.username }}</td>
                                <td>{{ u.role_name }}</td>
                                <td>
                                    <span :class="u.enable ? 'badge bg-success' : 'badge bg-secondary'">
                                        {{ u.enable ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-primary" @click="editUser(u)" title="Editar">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" @click="deleteUser(u.id)" title="Eliminar">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Producto -->
        <div v-if="showProductModal" class="modal d-block" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingProduct ? 'Editar' : 'Nuevo' }} Producto</h5>
                        <button @click="closeProductModal" type="button" class="btn-close"></button>
                    </div>
                    <form @submit.prevent="saveProduct">
                        <div class="modal-body">
                            <div class="mb-2">
                                <label class="form-label">Nombre</label>
                                <input v-model="productForm.name" type="text" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Categoría</label>
                                <select v-model="productForm.category_id" class="form-select form-select-sm" required>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Precio</label>
                                <input v-model="productForm.amount" type="number" step="0.01" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Descripción corta</label>
                                <input v-model="productForm.short_description" type="text" class="form-control form-control-sm">
                            </div>
                            <div class="form-check">
                                <input v-model="productForm.enable" type="checkbox" class="form-check-input" id="productEnable">
                                <label class="form-check-label" for="productEnable">Activo</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-secondary" @click="closeProductModal">Cancelar</button>
                            <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div v-if="showProductModal" class="modal-backdrop fade show"></div>

        <!-- Modal Categoría -->
        <div v-if="showCategoryModal" class="modal d-block" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingCategory ? 'Editar' : 'Nueva' }} Categoría</h5>
                        <button @click="closeCategoryModal" type="button" class="btn-close"></button>
                    </div>
                    <form @submit.prevent="saveCategory">
                        <div class="modal-body">
                            <div class="mb-2">
                                <label class="form-label">Nombre</label>
                                <input v-model="categoryForm.name" type="text" class="form-control form-control-sm" required>
                            </div>
                            <div class="form-check">
                                <input v-model="categoryForm.default" type="checkbox" class="form-check-input" id="categoryDefault">
                                <label class="form-check-label" for="categoryDefault">Por defecto</label>
                            </div>
                            <div class="form-check">
                                <input v-model="categoryForm.enable" type="checkbox" class="form-check-input" id="categoryEnable">
                                <label class="form-check-label" for="categoryEnable">Activa</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-secondary" @click="closeCategoryModal">Cancelar</button>
                            <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div v-if="showCategoryModal" class="modal-backdrop fade show"></div>

        <!-- Modal Usuario -->
        <div v-if="showUserModal" class="modal d-block" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingUser ? 'Editar' : 'Nuevo' }} Usuario</h5>
                        <button @click="closeUserModal" type="button" class="btn-close"></button>
                    </div>
                    <form @submit.prevent="saveUser">
                        <div class="modal-body">
                            <div class="mb-2">
                                <label class="form-label">Nombre</label>
                                <input v-model="userForm.name" type="text" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Usuario</label>
                                <input v-model="userForm.username" type="text" class="form-control form-control-sm" required>
                            </div>
                            <div v-if="!editingUser" class="mb-2">
                                <label class="form-label">Contraseña</label>
                                <input v-model="userForm.password" type="password" class="form-control form-control-sm" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Rol</label>
                                <select v-model="userForm.role_id" class="form-select form-select-sm" required>
                                    <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                                </select>
                            </div>
                            <div class="form-check">
                                <input v-model="userForm.enable" type="checkbox" class="form-check-input" id="userEnable">
                                <label class="form-check-label" for="userEnable">Activo</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-secondary" @click="closeUserModal">Cancelar</button>
                            <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div v-if="showUserModal" class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, inject } from 'vue';
import api from '../../../services/api';
import { useToastStore } from '../../../stores/toastify';
import { toastify as toastifyify } from '../../../utils/toastify';

const props = defineProps({
    initialTab: {
        type: String,
        default: 'products'
    }
});

const emit = defineEmits(['back']);

const isMobile = ref(window.innerWidth < 768);
const activeTab = ref(props.initialTab || 'products');
const tabs = [
    { id: 'products', label: 'Productos' },
    { id: 'categories', label: 'Categorías' },
    { id: 'users', label: 'Usuarios' },
    { id: 'orders', label: 'Órdenes' },
    { id: 'config', label: 'Configuración' }
];

const products = ref([]);
const categories = ref([]);
const roles = ref([]);
const users = ref([]);
const confirmDialog = inject('confirmDialog');
const toastifyStore = useToastStore();

const showProductModal = ref(false);
const showCategoryModal = ref(false);
const showUserModal = ref(false);
const editingProduct = ref(null);
const editingCategory = ref(null);
const editingUser = ref(null);

const productForm = reactive({
    name: '',
    category_id: '',
    amount: 0,
    short_description: '',
    enable: true
});

const categoryForm = reactive({
    name: '',
    default: false,
    enable: true
});

const userForm = reactive({
    name: '',
    username: '',
    password: '',
    role_id: '',
    enable: true
});

const loadData = async () => {
    try {
        const [productsRes, categoriesRes, rolesRes, usersRes] = await Promise.all([
            api.get('/pos/products'),
            api.get('/pos/categories'),
            api.get('/pos/roles'),
            api.get('/pos/users')
        ]);
        products.value = productsRes.data;
        categories.value = categoriesRes.data;
        roles.value = rolesRes.data;
        users.value = usersRes.data;
    } catch (error) {
        console.error('Error loading data:', error);
    }
};

const editProduct = (product) => {
    editingProduct.value = product;
    productForm.name = product.name;
    productForm.category_id = product.category_id;
    productForm.amount = product.amount;
    productForm.short_description = product.short_description || '';
    productForm.enable = product.enable;
    showProductModal.value = true;
};

const closeProductModal = () => {
    showProductModal.value = false;
    editingProduct.value = null;
    productForm.name = '';
    productForm.category_id = '';
    productForm.amount = 0;
    productForm.short_description = '';
    productForm.enable = true;
};

const saveProduct = async () => {
    try {
        if (editingProduct.value) {
            await api.put(`/pos/products/${editingProduct.value.id}`, productForm);
        } else {
            await api.post('/pos/products', productForm);
        }
        closeProductModal();
        loadData();
    } catch (error) {
        toastify.error('Error al guardar producto');
    }
};

const deleteProduct = async (id) => {
    const confirmed = await confirmDialog.open({
        title: 'Confirmar Eliminación',
        message: '¿Eliminar producto?',
        confirmText: 'Eliminar',
        confirmClass: 'btn-danger'
    });
    
    if (!confirmed) return;
    
    try {
        await api.delete(`/pos/products/${id}`);
        loadData();
        toastify.success('Producto eliminado');
    } catch (error) {
        toastify.error('Error al eliminar producto');
    }
};

const editCategory = (category) => {
    editingCategory.value = category;
    categoryForm.name = category.name;
    categoryForm.default = category.default;
    categoryForm.enable = category.enable;
    showCategoryModal.value = true;
};

const closeCategoryModal = () => {
    showCategoryModal.value = false;
    editingCategory.value = null;
    categoryForm.name = '';
    categoryForm.default = false;
    categoryForm.enable = true;
};

const saveCategory = async () => {
    try {
        if (editingCategory.value) {
            await api.put(`/pos/categories/${editingCategory.value.id}`, categoryForm);
        } else {
            await api.post('/pos/categories', categoryForm);
        }
        closeCategoryModal();
        loadData();
    } catch (error) {
        toastify.error('Error al guardar categoría');
    }
};

const deleteCategory = async (id) => {
    const confirmed = await confirmDialog.open({
        title: 'Confirmar Eliminación',
        message: '¿Eliminar categoría?',
        confirmText: 'Eliminar',
        confirmClass: 'btn-danger'
    });
    
    if (!confirmed) return;
    
    try {
        await api.delete(`/pos/categories/${id}`);
        loadData();
        toastify.success('Categoría eliminada');
    } catch (error) {
        toastify.error('Error al eliminar categoría');
    }
};

const editUser = (user) => {
    editingUser.value = user;
    userForm.name = user.name;
    userForm.username = user.username;
    userForm.role_id = user.role_id;
    userForm.enable = user.enable;
    showUserModal.value = true;
};

const closeUserModal = () => {
    showUserModal.value = false;
    editingUser.value = null;
    userForm.name = '';
    userForm.username = '';
    userForm.password = '';
    userForm.role_id = '';
    userForm.enable = true;
};

const saveUser = async () => {
    try {
        if (editingUser.value) {
            await api.put(`/pos/users/${editingUser.value.id}`, userForm);
        } else {
            await api.post('/pos/users', userForm);
        }
        closeUserModal();
        loadData();
        toastify.success('Usuario guardado');
    } catch (error) {
        toastify.error('Error al guardar usuario');
    }
};

const deleteUser = async (id) => {
    const confirmed = await confirmDialog.open({
        title: 'Confirmar Eliminación',
        message: '¿Eliminar usuario?',
        confirmText: 'Eliminar',
        confirmClass: 'btn-danger'
    });
    
    if (!confirmed) return;
    
    try {
        await api.delete(`/pos/users/${id}`);
        loadData();
        toastify.success('Usuario eliminado');
    } catch (error) {
        toastify.error('Error al eliminar usuario');
    }
};

onMounted(loadData);
</script>

<style scoped>
.pos-admin-container {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: #f8f9fa;
}

.pos-admin-header {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    background: white;
    border-bottom: 1px solid #dee2e6;
}

.pos-admin-tabs {
    display: flex;
    background: white;
    border-bottom: 1px solid #dee2e6;
    padding: 0 1rem;
}

.tab-btn {
    padding: 0.75rem 1rem;
    border: none;
    background: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
}

.tab-btn.active {
    border-bottom-color: #0d6efd;
    color: #0d6efd;
}

.pos-admin-content {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
}

.tab-content {
    background: white;
    border-radius: 0.5rem;
    padding: 1rem;
}
</style>
