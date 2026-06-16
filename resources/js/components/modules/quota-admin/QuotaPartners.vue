<template>
    <div class="quota-partners p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Socios</h4>
            <div>
                <button v-if="authStore.hasPermission('quota-plans_generate')" class="btn btn-outline-success btn-sm me-2" @click="generateAllForYear" :disabled="generatingAll">
                    <span v-if="generatingAll" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="bi bi-calendar-plus"></i> {{ generatingAll ? 'Generando...' : 'Generar cuotas del año' }}
                </button>
                <button class="btn btn-outline-success btn-sm me-2" @click="sendWhatsAppAll">
                    <i class="bi bi-whatsapp"></i> Notificar
                </button>
                <button class="btn btn-outline-primary btn-sm me-2" @click="showImport = true">
                    <i class="bi bi-upload"></i> Importar
                </button>
                <button class="btn btn-primary btn-sm" @click="openCreate">
                    <i class="bi bi-plus-lg"></i> Nuevo Socio
                </button>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4">
                <input class="form-control form-control-sm" v-model="filters.search" placeholder="Buscar socio..." @input="loadPartners">
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" v-model="filters.status" @change="loadPartners">
                    <option value="">Todos</option>
                    <option value="active">Activos</option>
                    <option value="inactive">Inactivos</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" v-model="filters.has_debt" @change="loadPartners">
                    <option value="">Todos</option>
                    <option value="true">Con Deuda</option>
                </select>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <template v-else>
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>DNI</th>
                        <th>Apellido</th>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Plan</th>
                        <th>Cuotas</th>
                        <th>Pagadas</th>
                        <th>Deuda</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in partners.data" :key="p.id">
                        <td>{{ p.dni }}</td>
                        <td>{{ p.last_name }}</td>
                        <td>{{ p.first_name }}</td>
                        <td>{{ p.phone || '-' }}</td>
                        <td>
                            <span v-if="p.plan_name" class="badge bg-info">{{ p.plan_name }}</span>
                            <span v-else class="text-muted">-</span>
                        </td>
                        <td>{{ p.quotas_count }}</td>
                        <td class="text-success">{{ p.paid_quotas }}</td>
                        <td class="text-danger">${{ formatNumber(p.total_debt) }}</td>
                        <td>
                            <span class="badge" :class="p.enable ? 'bg-success' : 'bg-secondary'">
                                {{ p.enable ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" @click="openEdit(p)" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success me-1" @click="openGenerate(p)" title="Generar cuotas">
                                <i class="bi bi-calendar-plus"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning me-1" @click="resetPassword(p)" title="Resetear contraseña">
                                <i class="bi bi-key"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success me-1" @click="sendWhatsApp(p)" title="Enviar link portal por WhatsApp">
                                <i class="bi bi-whatsapp"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(p)" title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!partners.data?.length">
                        <td colspan="10" class="text-center text-muted">No hay socios</td>
                    </tr>
                </tbody>
            </table>
            <nav v-if="partners.last_page > 1">
                <ul class="pagination pagination-sm">
                    <li class="page-item" :class="{ disabled: partners.current_page === 1 }">
                        <button class="page-link" @click="changePage(partners.current_page - 1)">Anterior</button>
                    </li>
                    <li class="page-item" :class="{ active: page === partners.current_page }" v-for="page in partners.last_page" :key="page">
                        <button class="page-link" @click="changePage(page)">{{ page }}</button>
                    </li>
                    <li class="page-item" :class="{ disabled: partners.current_page === partners.last_page }">
                        <button class="page-link" @click="changePage(partners.current_page + 1)">Siguiente</button>
                    </li>
                </ul>
            </nav>
        </template>

        <QuotaPartnerForm
            v-if="showForm"
            :partner="editingPartner"
            @close="showForm = false"
            @saved="onSaved"
        />
        <QuotaPartnerImport
            v-if="showImport"
            @close="showImport = false"
            @imported="onImported"
        />
        <QuotaPartnerGenerateQuotas
            v-if="showGenerate"
            :partner="generatingPartner"
            @close="showGenerate = false"
            @generated="onGenerated"
        />
    </div>

    <div v-if="showYearModal" class="modal fade show d-block" tabindex="-1"
         style="background: rgba(0,0,0,0.5); z-index: 1060;"
         @click.self="showYearModal = false">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generar cuotas del año</h5>
                    <button type="button" class="btn-close" @click="showYearModal = false"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Año *</label>
                        <input type="number" class="form-control" v-model.number="yearInput" min="2020" max="2099" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showYearModal = false">Cancelar</button>
                    <button class="btn btn-primary" @click="confirmYearModal">Generar</button>
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
import { useAuthStore } from '../../../stores/auth';
import ConfirmModal from '../../../components/ConfirmModal.vue';
import QuotaPartnerForm from './QuotaPartnerForm.vue';
import QuotaPartnerImport from './QuotaPartnerImport.vue';
import QuotaPartnerGenerateQuotas from './QuotaPartnerGenerateQuotas.vue';

const authStore = useAuthStore();
const partners = ref({ data: [], current_page: 1, last_page: 1 });
const loading = ref(true);
const showForm = ref(false);
const showImport = ref(false);
const showGenerate = ref(false);
const editingPartner = ref(null);
const generatingPartner = ref(null);
const generatingAll = ref(false);
const filters = ref({ search: '', status: '', has_debt: '' });
const confirmModal = ref(null);

const showYearModal = ref(false);
const yearInput = ref(new Date().getFullYear());

const formatNumber = (n) => parseFloat(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 });

const loadPartners = async () => {
    loading.value = true;
    try {
        const params = { ...filters.value };
        if (!params.search) delete params.search;
        if (!params.status) delete params.status;
        if (!params.has_debt) delete params.has_debt;
        const { data } = await axios.get('/quota/partners', { params });
        partners.value = data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
};

const changePage = (page) => {
    if (page < 1 || page > partners.value.last_page) return;
    loadPartners();
};

const openCreate = () => {
    editingPartner.value = null;
    showForm.value = true;
};

const openEdit = (p) => {
    editingPartner.value = { ...p };
    showForm.value = true;
};

const openGenerate = (p) => {
    generatingPartner.value = p;
    showGenerate.value = true;
};

const resetPassword = async (p) => {
    if (confirmModal.value) {
        confirmModal.value.open({
            title: 'Resetear Contraseña',
            message: `¿Resetear contraseña de ${p.first_name} ${p.last_name}?`,
            confirmText: 'Resetear',
            type: 'warning',
            onConfirm: async () => {
                try {
                    const { data } = await axios.post(`/quota/partners/${p.id}/reset-password`);
                    toast.success(`Contraseña reseteada: ${data.new_password}`);
                } catch (e) {
                    toast.error('Error al resetear contraseña');
                }
            }
        });
    }
};

const confirmDelete = async (p) => {
    if (confirmModal.value) {
        confirmModal.value.open({
            title: 'Eliminar Socio',
            message: `¿Eliminar a ${p.first_name} ${p.last_name}?`,
            confirmText: 'Eliminar',
            type: 'danger',
            onConfirm: async () => {
                try {
                    await axios.delete(`/quota/partners/${p.id}`);
                    loadPartners();
                    toast.success('Socio eliminado');
                } catch (e) {
                    toast.error(e.response?.data?.message || 'Error al eliminar');
                }
            }
        });
    }
};

const onSaved = () => {
    showForm.value = false;
    loadPartners();
};

const onImported = () => {
    showImport.value = false;
    loadPartners();
};

const sendWhatsApp = (partner) => {
    const origin = window.location.origin;
    const companyName = authStore.company?.name || '';
    const msg = encodeURIComponent(
        `Hola, ingresá al portal de socios para gestionar tus cuotas: ${origin}/asociados/${companyName}/${partner.dni}`
    );
    window.open(`https://web.whatsapp.com/send?text=${msg}`, '_blank');
};

const sendWhatsAppAll = () => {
    const origin = window.location.origin;
    const companyName = authStore.company?.name || '';
    const enabled = partners.value.data?.filter(p => p.enable) || [];
    if (enabled.length === 0) {
        toast.warning('No hay socios activos para notificar');
        return;
    }
    const msg = encodeURIComponent(
        `Estimados socios, recuerden que pueden ingresar al portal de socios para gestionar sus cuotas.\n\nIngresen con su DNI como usuario y contraseña en:\n${origin}/asociados/${companyName}`
    );
    window.open(`https://web.whatsapp.com/send?text=${msg}`, '_blank');
};

const onGenerated = () => {
    showGenerate.value = false;
    loadPartners();
};

const generateAllForYear = () => {
    yearInput.value = new Date().getFullYear();
    showYearModal.value = true;
};

const confirmYearModal = () => {
    const year = yearInput.value;
    if (!year || isNaN(year) || year < 2020 || year > 2099) return;
    showYearModal.value = false;
    if (!confirmModal.value) return;
    confirmModal.value.open({
        title: 'Generar Cuotas',
        message: `¿Generar cuotas del año ${year} para TODOS los socios con plan asignado?`,
        confirmText: 'Generar',
        type: 'primary',
        onConfirm: async () => {
            generatingAll.value = true;
            try {
                const { data } = await axios.post('/quota/partners/generate-all', { year: parseInt(year) });
                toast.success(data.message);
                loadPartners();
            } catch (e) {
                toast.error(e.response?.data?.message || 'Error al generar');
            } finally { generatingAll.value = false; }
        }
    });
};

onMounted(loadPartners);
</script>
