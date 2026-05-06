<template>
    <div class="data-table-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="search-box">
                <input 
                    type="text" 
                    v-model="search" 
                    placeholder="Buscar..." 
                    class="form-control form-control-sm"
                    @input="onSearch"
                >
            </div>
            <div class="actions-box">
                <slot name="actions"></slot>
            </div>
        </div>
        <div class="table-wrapper">
            <table ref="tableRef" class="table table-hover table-bordered mb-0">
                <thead>
                    <tr>
                        <th 
                            v-for="col in columns" 
                            :key="col.key"
                            @click="sort(col.key)"
                            class="sortable"
                        >
                            {{ col.label }}
                            <span v-if="sortColumn === col.key" class="sort-icon">
                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                            </span>
                        </th>
                        <th v-if="$slots.rowActions" class="actions-col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in paginatedData" :key="index">
                        <td v-for="col in columns" :key="col.key">
                            {{ row[col.key] }}
                        </td>
                        <td v-if="$slots.rowActions" class="actions-col">
                            <slot name="rowActions" :row="row"></slot>
                        </td>
                    </tr>
                    <tr v-if="paginatedData.length === 0">
                        <td :colspan="columns.length + ($slots.rowActions ? 1 : 0)" class="text-center text-muted">
                            No hay datos disponibles
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="page-info">
                Mostrando {{ startRow }} a {{ endRow }} de {{ totalRecords }} registros
            </div>
            <div class="pagination">
                <button 
                    class="btn btn-sm btn-outline-secondary me-1" 
                    :disabled="currentPage === 1"
                    @click="goToPage(currentPage - 1)"
                >
                    Anterior
                </button>
                <button 
                    v-for="page in visiblePages" 
                    :key="page"
                    class="btn btn-sm me-1"
                    :class="page === currentPage ? 'btn-primary' : 'btn-outline-secondary'"
                    @click="goToPage(page)"
                >
                    {{ page }}
                </button>
                <button 
                    class="btn btn-sm btn-outline-secondary" 
                    :disabled="currentPage === totalPages"
                    @click="goToPage(currentPage + 1)"
                >
                    Siguiente
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';

const props = defineProps({
    data: {
        type: Array,
        default: () => []
    },
    columns: {
        type: Array,
        required: true
    },
    perPage: {
        type: Number,
        default: 10
    }
});

const search = ref('');
const sortColumn = ref('');
const sortDirection = ref('asc');
const currentPage = ref(1);

const filteredData = computed(() => {
    let result = [...props.data];
    
    if (search.value) {
        const searchLower = search.value.toLowerCase();
        result = result.filter(row => 
            Object.values(row).some(val => 
                String(val).toLowerCase().includes(searchLower)
            )
        );
    }
    
    if (sortColumn.value) {
        result.sort((a, b) => {
            const aVal = a[sortColumn.value];
            const bVal = b[sortColumn.value];
            
            if (typeof aVal === 'number' && typeof bVal === 'number') {
                return sortDirection.value === 'asc' ? aVal - bVal : bVal - aVal;
            }
            
            const comparison = String(aVal).localeCompare(String(bVal));
            return sortDirection.value === 'asc' ? comparison : -comparison;
        });
    }
    
    return result;
});

const totalRecords = computed(() => filteredData.value.length);
const totalPages = computed(() => Math.ceil(totalRecords.value / props.perPage));
const startRow = computed(() => totalRecords.value === 0 ? 0 : (currentPage.value - 1) * props.perPage + 1);
const endRow = computed(() => Math.min(currentPage.value * props.perPage, totalRecords.value));

const visiblePages = computed(() => {
    const pages = [];
    const start = Math.max(1, currentPage.value - 2);
    const end = Math.min(totalPages.value, currentPage.value + 2);
    
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    return pages;
});

const paginatedData = computed(() => {
    const start = (currentPage.value - 1) * props.perPage;
    return filteredData.value.slice(start, start + props.perPage);
});

function onSearch() {
    currentPage.value = 1;
}

function sort(column) {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
}

function goToPage(page) {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
}

watch(() => props.data, () => {
    currentPage.value = 1;
});
</script>

<style scoped>
.data-table-container {
    width: 100%;
}

.search-box {
    max-width: 300px;
}

.search-box input {
    width: 100%;
}

.sortable {
    cursor: pointer;
    user-select: none;
}

.sortable:hover {
    background-color: #f8f9fa;
}

.sort-icon {
    margin-left: 5px;
}

.page-info {
    color: #6c757d;
    font-size: 0.875rem;
}

.pagination {
    display: flex;
    gap: 2px;
}

.table-wrapper {
    overflow-x: auto;
    position: relative;
}

.table-wrapper .actions-col {
    position: sticky !important;
    right: 0 !important;
    background: white !important;
    z-index: 10 !important;
    min-width: 120px !important;
    text-align: center !important;
    box-shadow: -2px 0 5px rgba(0,0,0,0.1) !important;
}

.table-wrapper thead .actions-col {
    background: #e9ecef !important;
    z-index: 11 !important;
}

.table-wrapper tbody tr:hover .actions-col {
    background: #f8f9fa !important;
}
</style>