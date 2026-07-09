<template>
    <div class="academy-documentation p-3">
        <h2 class="mb-4">Documentación del Módulo Academy</h2>
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="list-group">
                    <button v-for="(sec, i) in sections" :key="sec.id" class="list-group-item list-group-item-action" :class="{ active: activeSection === i }" @click="activeSection = i">
                        {{ sec.title }}
                    </button>
                </div>
            </div>
            <div class="col-md-9">
                <div class="card border-0 shadow-sm">
                    <div class="card-body" v-html="sections[activeSection]?.content || ''">
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import api from '../../../services/api';
const activeSection = ref(0);
const sections = ref([]);
onMounted(async () => {
    try { const { data } = await api.get('/academy/documentation'); sections.value = data.sections || []; } catch (e) { console.error(e); sections.value = [{ id: 'error', title: 'Error', content: '<p>No se pudo cargar la documentación.</p>' }]; }
});
</script>
<style scoped>
:deep(h4) { margin-top: 1rem; }
:deep(ol) { padding-left: 1.5rem; }
:deep(ul) { padding-left: 1.5rem; }
:deep(.alert) { padding: 0.75rem 1rem; border-radius: 0.5rem; }
:deep(.accordion-item) { margin-bottom: 0.5rem; }
:deep(.accordion-item h5) { cursor: pointer; color: var(--bs-primary); }
:deep(code) { background: #f4f4f4; padding: 0.15rem 0.3rem; border-radius: 3px; font-size: 0.9em; }
</style>