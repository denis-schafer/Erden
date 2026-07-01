<template>
    <div class="hairsalon-appointments p-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h4 class="mb-0">Turnos</h4>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <select v-model="filterOperator" class="form-select form-select-sm" style="width:160px" @change="loadAppointments">
                    <option :value="null">Todos los operadores</option>
                    <option v-for="o in operators" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary" @click="prevWeek">&laquo;</button>
                <strong>{{ weekLabel }}</strong>
                <button class="btn btn-sm btn-outline-secondary" @click="nextWeek">&raquo;</button>
                <button class="btn btn-sm btn-outline-primary ms-1" @click="today">Hoy</button>
            </div>
        </div>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border"></div></div>
        <template v-else>
            <div class="calendar-wrapper" ref="calendarWrapperRef">
                <div class="calendar-grid" ref="calendarGridRef" :style="{ gridTemplateColumns: '60px repeat(' + (viewMode === 'weekly' ? 7 : 1) + ', 1fr)' }">
                    <div class="cal-header time-col"></div>
                    <div v-for="day in visibleDays" :key="day.date" class="cal-header day-header" :class="{ today: day.isToday }">
                        <strong>{{ day.label }}</strong><br><small>{{ day.dateDisplay }}</small>
                    </div>
                    <template v-for="hour in hours" :key="hour">
                        <div class="cal-cell time-col time-label">{{ hour }}:00</div>
                        <div v-for="day in visibleDays" :key="day.date + '-' + hour" class="cal-cell slot-cell" @click="openCreate(day.date, hour)"></div>
                    </template>
                </div>
                <div class="appointments-overlay" :style="{ marginTop: headerHeight + 'px' }">
                    <div v-for="day in visibleDays" :key="'overlay-' + day.date" class="day-column"
                        :style="{ left: day.columnLeft + '%', width: day.columnWidth + '%' }">
                        <div v-for="apt in dayAppointments(day.date)" :key="apt.id" class="apt-block"
                            :style="{ top: apt.topPct + '%', height: apt.heightPct + '%', background: apt.color || '#0d6efd' }"
                            @mousedown="startDrag(apt, $event)" @touchstart="startDrag(apt, $event)">
                            <div class="apt-time">{{ apt.dayLabel }} {{ formatTime(apt.start_time) }} - {{ apt.endTime }}</div>
                            <div class="apt-client">{{ apt.client_name }}</div>
                            <div class="apt-op">{{ apt.operator_name }}</div>
                            <div class="apt-resize-handle" @mousedown.stop="startResize(apt, $event)" @touchstart="startResize(apt, $event)"></div>
                        </div>
                    </div>
                </div>
                <div v-if="dragGhost" class="drag-ghost" :style="{ top: dragGhost.top + 'px', left: dragGhost.left + 'px', width: dragGhost.width + 'px', height: dragGhost.height + 'px', background: dragGhost.color }">
                    <div class="apt-time">{{ dragGhost.dayLabel }} {{ dragGhost.time }} - {{ dragGhost.endTime }}</div>
                    <div class="apt-client">{{ dragGhost.client }}</div>
                </div>
            </div>
        </template>
        <div v-if="showModal" class="modal d-block"><div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">{{ editing ? 'Editar Turno' : 'Nuevo Turno' }}</h5><button class="btn-close" @click="showModal=false"></button></div>
            <form @submit.prevent="save"><div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-2"><label class="form-label">Cliente</label>
                        <div class="position-relative"><input v-model="form.client_name" class="form-control form-control-sm" placeholder="Nombre..." required @focus="clientDropdown=true" @input="clientDropdown=true" @blur="onClientBlur" ref="clientInputRef">
                            <div v-if="clientDropdown && filteredClients.length" class="position-absolute w-100 border rounded bg-white shadow-sm" style="max-height:150px;overflow-y:auto;z-index:1050">
                                <div v-for="c in filteredClients" :key="c.id" class="px-2 py-1" @mousedown.prevent="selectExistingClient(c)" style="cursor:pointer;border-bottom:1px solid #f0f0f0" @mouseover="clientHover=c.id" :class="clientHover===c.id?'bg-primary text-white':''">{{ c.name }} <small class="text-muted">{{ c.phone }}</small></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2"><label class="form-label">Operador</label><select v-model="form.operator_id" class="form-select form-select-sm" required><option v-for="o in operators" :key="o.id" :value="o.id">{{ o.name }}</option></select></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Color</label><input type="color" v-model="form.color" class="form-control form-control-color" style="width:100%;height:31px"></div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-2"><label class="form-label">Fecha</label><input type="date" v-model="form.date" class="form-control form-control-sm" required></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Hora</label><input type="time" v-model="form.time" class="form-control form-control-sm" required></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Duración</label><input type="number" v-model.number="form.duration_min" class="form-control form-control-sm" min="0"></div>
                    <div class="col-md-2 mb-2"><label class="form-label">Estado</label><select v-model="form.status" class="form-select form-select-sm"><option value="scheduled">Pendiente</option><option value="in_progress">En Progreso</option><option value="completed">Completado</option><option value="cancelled">Cancelado</option></select></div>
                </div>
                <div class="mb-2"><label class="form-label">Servicios existentes</label>
                    <div class="d-flex flex-wrap gap-1 mb-1"><span v-for="sid in form.service_ids" :key="sid" class="badge bg-primary">{{ (services.find(s=>s.id===sid)?.name||'') }}${{ services.find(s=>s.id===sid)?.price||0 }}<button class="btn btn-sm p-0 ms-1 text-white" @click="removeService(sid)" type="button"><i class="bi bi-x"></i></button></span></div>
                    <div class="position-relative"><input v-model="svcSearch" class="form-control form-control-sm" placeholder="Buscar servicio..." @focus="svcDropdown=true" @input="svcDropdown=true" @blur="onSvcBlur" ref="svcInputRef">
                        <div v-if="svcDropdown&&filteredSvcList.length" class="position-absolute w-100 border rounded bg-white shadow-sm" style="max-height:180px;overflow-y:auto;z-index:1050">
                            <div v-for="s in filteredSvcList" :key="s.id" class="px-2 py-1" @mousedown.prevent="addService(s)" style="cursor:pointer;border-bottom:1px solid #f0f0f0" @mouseover="svcHover=s.id" :class="svcHover===s.id?'bg-primary text-white':''">{{ s.name }} <small class="text-muted">${{ s.price }} / {{ s.duration_min||'-' }}min</small></div>
                        </div>
                    </div>
                </div>
                <div class="mb-2"><label class="form-label">Servicios personalizados <button class="btn btn-sm btn-outline-success ms-2" @click="addCustomSvc" type="button"><i class="bi bi-plus"></i></button></label>
                    <div v-if="form.custom_services.length" class="d-flex gap-1 mb-1 px-1"><span class="small text-muted" style="flex:1">Nombre</span><span class="small text-muted" style="width:80px">Precio</span><span class="small text-muted" style="width:60px">Duración</span><span style="width:31px"></span></div>
                    <div v-for="(cs,i) in form.custom_services" :key="i" class="d-flex gap-1 mb-1"><input v-model="cs.name" class="form-control form-control-sm" placeholder="Nombre" style="flex:1"><input v-model.number="cs.price" class="form-control form-control-sm" type="number" step="0.01" min="0" placeholder="$" style="width:80px"><input v-model.number="cs.duration_min" class="form-control form-control-sm" type="number" min="0" placeholder="min" style="width:60px"><button class="btn btn-sm btn-outline-danger" @click="removeCustomSvc(i)" type="button"><i class="bi bi-x"></i></button></div>
                </div>
                <div class="mb-2"><label class="form-label">Notas</label><textarea v-model="form.notes" class="form-control form-control-sm" rows="2"></textarea></div>
            </div><div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" @click="showModal=false">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm" @click="cancelAppointment" v-if="editing">Cancelar Turno</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ saving?'Guardando...':'Guardar' }}</button>
            </div></form>
        </div></div></div>
        <div v-if="showModal" class="modal-backdrop fade show"></div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import api from '../../../services/api';
import { toast } from '../../../utils/toast';

const loading = ref(true);
const appointments = ref([]);
const operators = ref([]);
const services = ref([]);
const allClients = ref([]);
const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const viewMode = ref('weekly');
const calStartTime = ref(8);
const calEndTime = ref(20);
const defaultOperatorId = ref(null);
const filterOperator = ref(null);
const calendarWrapperRef = ref(null);
const calendarGridRef = ref(null);
const headerHeight = ref(44);
const weekOffset = ref(0);
const currentDate = ref(new Date().toISOString().split('T')[0]);
const clientDropdown = ref(false);
const clientHover = ref(null);
const clientInputRef = ref(null);
const svcSearch = ref('');
const svcDropdown = ref(false);
const svcHover = ref(null);
const svcInputRef = ref(null);
const dragGhost = ref(null);
const resizeAptId = ref(null);
const resizeHolder = ref(0);
const skipNextReload = ref(false);
const hoveredAptId = ref(null);

const form = reactive({
    client_name: '', client_id: null, operator_id: '', date: '', time: '',
    duration_min: 60, service_ids: [], custom_services: [], notes: '', status: 'scheduled', color: '#0d6efd',
});

const weekStart = computed(() => {
    const d = new Date(); d.setDate(d.getDate() + weekOffset.value * 7);
    const day = d.getDay(); const diff = d.getDate() - day + (day === 0 ? -6 : 1);
    d.setDate(diff); d.setHours(0, 0, 0, 0); return d;
});
const totalHours = computed(() => Math.max(1, calEndTime.value - calStartTime.value));
const dayLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
const dayFromDate = (ds) => { const d = new Date(ds + 'T12:00:00'); return dayLabels[d.getDay() === 0 ? 6 : d.getDay() - 1] || ''; };

const visibleDays = computed(() => {
    const days = []; const start = new Date(weekStart.value);
    const today = new Date().toISOString().split('T')[0];
    const count = viewMode.value === 'weekly' ? 7 : 1; const colW = 100 / count;
    for (let i = 0; i < count; i++) { const d = new Date(start); d.setDate(start.getDate() + i); const ds = d.toISOString().split('T')[0]; days.push({ date: ds, label: dayLabels[i], dateDisplay: d.getDate().toString(), isToday: ds === today, columnLeft: i * colW, columnWidth: colW }); }
    return days;
});
const hours = computed(() => { const h = []; for (let i = calStartTime.value; i < calEndTime.value; i++) h.push(i); return h; });
const weekLabel = computed(() => {
    const s = visibleDays.value[0]; const e = visibleDays.value[visibleDays.value.length - 1];
    if (!s || !e) return '';
    return new Date(s.date).toLocaleDateString('es-AR', { day:'numeric', month:'long' }) + ' - ' + new Date(e.date).toLocaleDateString('es-AR', { day:'numeric', month:'long' });
});
const filteredClients = computed(() => {
    if (!form.client_name) return allClients.value; const s = form.client_name.toLowerCase();
    return allClients.value.filter(c => c.name.toLowerCase().includes(s) || (c.phone && c.phone.includes(s)));
});
const filteredSvcList = computed(() => {
    const s = svcSearch.value.toLowerCase();
    return services.value.filter(svc => !s || svc.name.toLowerCase().includes(s));
});

const formatTime = (t) => { if (!t) return ''; return new Date(t).toLocaleTimeString('es-AR', { hour:'2-digit', minute:'2-digit' }); };
const formatEndTime = (st, dur) => { if (!st || !dur) return ''; const d = new Date(st); d.setMinutes(d.getMinutes() + dur); return d.toLocaleTimeString('es-AR', { hour:'2-digit', minute:'2-digit' }); };

const snapMin = 5;
const snapTime = (hf) => { const h = Math.floor(hf); const m = Math.round((hf - h) * 60); const s = Math.round(m / snapMin) * snapMin; const ah = h + Math.floor(s / 60); return { h: Math.max(calStartTime.value, Math.min(calEndTime.value - 1, ah)), m: s % 60 }; };

const cardTopToTime = (cardTopY) => {
    const wrapper = calendarWrapperRef.value; const gridEl = calendarGridRef.value;
    if (!wrapper || !gridEl) return null;
    const wr = wrapper.getBoundingClientRect();
    const fh = gridEl.querySelector('.cal-header');
    const hdr = fh ? fh.offsetHeight : 44;
    const relY = Math.max(0, Math.min(1, (cardTopY - wr.top - hdr) / (wr.height - hdr)));
    const hf = calStartTime.value + relY * totalHours.value;
    return snapTime(hf);
};

const dayAppointments = (date) => {
    const dl = dayFromDate(date);
    return appointments.value.filter(a => {
        if (!a.start_time || a.status === 'cancelled') return false;
        return new Date(a.start_time).toISOString().split('T')[0] === date;
    }).filter(a => !dragGhost.value || a.id !== dragGhost.value.id).map(a => {
        const d = new Date(a.start_time);
        const startHour = d.getHours() + d.getMinutes() / 60;
        const topPct = ((startHour - calStartTime.value) / totalHours.value) * 100;
        const dur = resizeAptId.value === a.id ? resizeHolder.value : (a.duration_min || 60);
        const heightPct = Math.max((dur / 60 / totalHours.value) * 100, 3);
        return { ...a, topPct, heightPct, endTime: formatEndTime(a.start_time, dur), dayLabel: dl };
    });
};

const prevWeek = () => { weekOffset.value--; loadAppointments(); };
const nextWeek = () => { weekOffset.value++; loadAppointments(); };
const today = () => { weekOffset.value = 0; loadAppointments(); };

const updateLocalAppointment = (id, updates) => {
    const idx = appointments.value.findIndex(a => a.id === id);
    if (idx >= 0) Object.assign(appointments.value[idx], updates);
};

const getEventPos = (e) => {
    if (e.touches) return { x: e.touches[0].clientX, y: e.touches[0].clientY };
    return { x: e.clientX, y: e.clientY };
};

// Drag — with threshold to distinguish click from drag
const startDrag = (apt, event) => {
    if (event.target.closest('.apt-resize-handle')) return;
    event.preventDefault();
    const wrapper = calendarWrapperRef.value; const gridEl = calendarGridRef.value;
    if (!wrapper || !gridEl) return;
    const wr = wrapper.getBoundingClientRect();
    const firstHeader = gridEl.querySelector('.cal-header');
    const hdr = firstHeader ? firstHeader.offsetHeight : 44;
    headerHeight.value = hdr;
    const aptRect = event.currentTarget.getBoundingClientRect();
    const pos = getEventPos(event);
    const offsetY = pos.y - aptRect.top;
    const offsetX = pos.x - aptRect.left;
    const startX = pos.x;
    const startY = pos.y;
    let hasMoved = false;
    const dragThreshold = 5; // px

    const ghostData = {
        id: apt.id, client: apt.client_name, time: formatTime(apt.start_time),
        endTime: formatEndTime(apt.start_time, apt.duration_min), dur: apt.duration_min || 60,
        color: apt.color || '#0d6efd', top: 0, left: 0, width: aptRect.width, height: aptRect.height,
        offsetX: offsetX, offsetY: offsetY, apt: apt, startDate: new Date(apt.start_time).toISOString().split('T')[0],
        dayLabel: '',
    };

    const onMove = (e) => {
        const p = getEventPos(e);
        const dx = p.x - startX;
        const dy = p.y - startY;
        if (!hasMoved && Math.sqrt(dx*dx + dy*dy) < dragThreshold) return;
        if (!hasMoved) {
            hasMoved = true;
            dragGhost.value = reactive(ghostData);
        }
        if (!dragGhost.value) return;
        const cardTop = p.y - offsetY;
        const snapped = cardTopToTime(cardTop);
        if (!snapped) return;
        const relY = ((snapped.h + snapped.m / 60) - calStartTime.value) / totalHours.value;
        const snappedTop = hdr + relY * (wr.height - hdr);
        dragGhost.value.top = snappedTop;
        dragGhost.value.left = p.x - wr.left - offsetX;
        const colArea = wr.width - 60;
        const dayIdx = viewMode.value === 'weekly' ? Math.min(6, Math.max(0, Math.floor(((p.x - wr.left - 60) / colArea) * 7))) : 0;
        const day = visibleDays.value[dayIdx];
        dragGhost.value.dayLabel = day?.label || '';
        dragGhost.value.time = `${String(snapped.h).padStart(2, '0')}:${String(snapped.m).padStart(2, '0')}`;
        const endD = new Date(); endD.setHours(snapped.h, snapped.m + (dragGhost.value.dur || 60), 0, 0);
        dragGhost.value.endTime = endD.toLocaleTimeString('es-AR', { hour:'2-digit', minute:'2-digit' });
    };

    const onUp = async (e) => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        document.removeEventListener('touchmove', onMove);
        document.removeEventListener('touchend', onUp);
        // If didn't move past threshold, treat as click → open edit modal
        if (!hasMoved) { openEdit(apt); return; }
        if (!dragGhost.value) return;
        const p = getEventPos(e);
        const cardTop = p.y - offsetY;
        const snapped = cardTopToTime(cardTop);
        const ghost = dragGhost.value;
        if (!snapped) { dragGhost.value = null; return; }
        const colArea = (wrapper?.getBoundingClientRect().width || 600) - 60;
        const dayIdx = viewMode.value === 'weekly' ? Math.min(6, Math.max(0, Math.floor(((p.x - (wrapper?.getBoundingClientRect().left || 0) - 60) / colArea) * 7))) : 0;
        const day = visibleDays.value[dayIdx];
        const newStartTime = `${day?.date || ghost.startDate} ${String(snapped.h).padStart(2, '0')}:${String(snapped.m).padStart(2, '0')}:00`;
        const oldStartTime = ghost.apt.start_time;
        updateLocalAppointment(ghost.apt.id, { start_time: newStartTime });
        dragGhost.value = null;
        skipNextReload.value = true;
        try {
            await api.put('/hairsalon/appointments/' + ghost.apt.id, {
                client_name: ghost.apt.client_name, client_id: ghost.apt.client_id,
                operator_id: ghost.apt.operator_id, start_time: newStartTime,
                duration_min: ghost.apt.duration_min || 60,
                notes: ghost.apt.notes, status: ghost.apt.status, color: ghost.apt.color,
            });
        } catch (e) { updateLocalAppointment(ghost.apt.id, { start_time: oldStartTime }); toast.error('Error al mover turno'); }
    };

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
    document.addEventListener('touchmove', onMove, { passive: false });
    document.addEventListener('touchend', onUp);
};

// Resize — simple reactive refs for smooth real-time feedback
const startResize = (apt, event) => {
    event.preventDefault(); event.stopPropagation();
    resizeAptId.value = apt.id;
    resizeHolder.value = apt.duration_min || 60;
    const pos = getEventPos(event);
    const startY = pos.y;
    const startDur = apt.duration_min || 60;
    const wrapper = calendarWrapperRef.value; const gridEl = calendarGridRef.value;
    if (!wrapper || !gridEl) return;
    const wr = wrapper.getBoundingClientRect();
    const fh = gridEl.querySelector('.cal-header');
    const hdr = fh ? fh.offsetHeight : 44;
    const gh = wr.height - hdr;

    const onMove = (e) => {
        if (!resizeAptId.value) return;
        const p = getEventPos(e);
        const deltaY = p.y - startY;
        resizeHolder.value = Math.max(15, Math.round(startDur + (deltaY / gh) * totalHours.value * 60));
    };

    const onUp = async (e) => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        document.removeEventListener('touchmove', onMove);
        document.removeEventListener('touchend', onUp);
        if (!resizeAptId.value) return;
        const finalDur = resizeHolder.value;
        const id = resizeAptId.value;
        resizeAptId.value = null;
        skipNextReload.value = true;
        const aptData = appointments.value.find(a => a.id === id);
        if (aptData) aptData.duration_min = finalDur;
        try {
            await api.put('/hairsalon/appointments/' + id, { client_name: aptData?.client_name || '', client_id: aptData?.client_id || null, operator_id: aptData?.operator_id || 1, start_time: aptData?.start_time || '', duration_min: finalDur, notes: aptData?.notes || '', status: aptData?.status || 'scheduled', color: aptData?.color || '#0d6efd' });
        } catch (e) { toast.error('Error al ajustar duración'); }
    };
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
    document.addEventListener('touchmove', onMove, { passive: false });
    document.addEventListener('touchend', onUp);
};

const onSvcBlur = () => { setTimeout(() => { svcDropdown.value = false; }, 200); };
const addService = (svc) => { if (!form.service_ids.includes(svc.id)) { form.service_ids.push(svc.id); } svcSearch.value = ''; svcDropdown.value = false; };
const removeService = (sid) => { const idx = form.service_ids.indexOf(sid); if (idx >= 0) form.service_ids.splice(idx, 1); };
const addCustomSvc = () => { form.custom_services.push({ name: '', price: 0, duration_min: 0 }); };
const removeCustomSvc = (idx) => { form.custom_services.splice(idx, 1); };
const onClientBlur = () => { setTimeout(() => { clientDropdown.value = false; }, 200); };
const selectExistingClient = (c) => { form.client_name = c.name; form.client_id = c.id; clientDropdown.value = false; };

const openCreate = (date, hour) => {
    editing.value = null; form.client_name = ''; form.client_id = null;
    form.operator_id = defaultOperatorId.value || operators.value[0]?.id || '';
    form.date = date; form.time = String(hour).padStart(2, '0') + ':00';
    form.duration_min = 60; form.service_ids = []; form.custom_services = [];
    form.notes = ''; form.status = 'scheduled'; form.color = '#0d6efd'; showModal.value = true;
};

const openEdit = (apt, overrides = {}) => {
    editing.value = apt;
    form.client_name = overrides.client_name ?? apt.client_name;
    form.client_id = overrides.client_id ?? apt.client_id;
    form.operator_id = overrides.operator_id ?? apt.operator_id;
    const src = overrides.start_time || apt.start_time;
    if (src) { const d = new Date(src); form.date = d.toISOString().split('T')[0]; form.time = d.toTimeString().slice(0, 5); }
    else { form.date = ''; form.time = ''; }
    form.duration_min = overrides.duration_min ?? (apt.duration_min || 60);
    form.service_ids = apt.service_ids ? JSON.parse(apt.service_ids) : [];
    form.custom_services = apt.custom_services ? JSON.parse(apt.custom_services) : [];
    form.notes = apt.notes || '';
    form.status = overrides.status ?? (apt.status || 'scheduled');
    form.color = overrides.color ?? (apt.color || '#0d6efd');
    showModal.value = true;
};

const save = async () => {
    saving.value = true;
    try {
        const st = form.date + ' ' + form.time + ':00';
        const data = { client_name: form.client_name, client_id: form.client_id || null, operator_id: form.operator_id,
            service_ids: form.service_ids.length ? form.service_ids : null,
            custom_services: form.custom_services.length ? form.custom_services : null,
            start_time: st, duration_min: form.duration_min, notes: form.notes, status: form.status, color: form.color };
        skipNextReload.value = true;
        if (editing.value) { await api.put('/hairsalon/appointments/' + editing.value.id, data); toast.success('Turno actualizado'); updateLocalAppointment(editing.value.id, { start_time: st, duration_min: form.duration_min, status: form.status, notes: form.notes, color: form.color, client_name: form.client_name, operator_id: form.operator_id }); }
        else { const res = await api.post('/hairsalon/appointments', data); toast.success('Turno creado'); if (res.data.appointment) appointments.value.push(res.data.appointment); }
        showModal.value = false;
    } catch (e) { toast.error(e.response?.data?.message || 'Error'); }
    finally { saving.value = false; }
};

const cancelAppointment = async () => {
    if (!editing.value) return;
    skipNextReload.value = true;
    try { await api.patch('/hairsalon/appointments/' + editing.value.id + '/status', { status: 'cancelled' }); toast.success('Turno cancelado'); updateLocalAppointment(editing.value.id, { status: 'cancelled' }); showModal.value = false; }
    catch (e) { toast.error('Error al cancelar'); }
};

const loadAppointments = async () => {
    loading.value = true;
    try {
        const params = {}; if (filterOperator.value) params.operator_id = filterOperator.value;
        const [aptRes, cfgRes, svcRes, cliRes] = await Promise.all([
            api.get('/hairsalon/appointments', { params }), api.get('/hairsalon/config'),
            api.get('/hairsalon/services'), api.get('/hairsalon/clients', { params: { per_page: 500 } }),
        ]);
        appointments.value = aptRes.data.appointments || [];
        operators.value = aptRes.data.operators || [];
        services.value = (svcRes.data.services || []).filter(s => s.is_active);
        allClients.value = cliRes.data.data || [];
        const configs = cfgRes.data || [];
        const startCfg = configs.find(c => c.name === 'calendar_start_time');
        const endCfg = configs.find(c => c.name === 'calendar_end_time');
        const modeCfg = configs.find(c => c.name === 'calendar_view_mode');
        const defaultOpCfg = configs.find(c => c.name === 'default_operator_id');
        if (startCfg) calStartTime.value = parseInt(startCfg.value?.split(':')[0] || '8');
        if (endCfg) calEndTime.value = parseInt(endCfg.value?.split(':')[0] || '20');
        viewMode.value = modeCfg?.value || 'weekly';
        defaultOperatorId.value = defaultOpCfg?.value ? parseInt(defaultOpCfg.value) : null;
        setTimeout(() => { const ge = calendarGridRef.value; if (ge) { const fh = ge.querySelector('.cal-header'); if (fh) headerHeight.value = fh.offsetHeight; } }, 50);
    } finally { loading.value = false; }
};

const handleChanged = () => { if (skipNextReload.value) { skipNextReload.value = false; return; } loadAppointments(); };
const handleUserChanged = () => { if (skipNextReload.value) { skipNextReload.value = false; return; } loadAppointments(); };
const handleConfigChanged = () => { loadAppointments(); };
onMounted(() => { loadAppointments(); window.addEventListener('hairsalon-appointment-changed', handleChanged); window.addEventListener('hairsalon-user-changed', handleUserChanged); window.addEventListener('hairsalon-config-updated', handleConfigChanged); });
onUnmounted(() => { window.removeEventListener('hairsalon-appointment-changed', handleChanged); window.removeEventListener('hairsalon-user-changed', handleUserChanged); window.removeEventListener('hairsalon-config-updated', handleConfigChanged); });
</script>

<style scoped>
.calendar-wrapper { position: relative; }
.calendar-grid { display: grid; border: 1px solid #dee2e6; border-radius: 0.375rem; overflow: hidden; }
.cal-header { background: #f8f9fa; padding: 6px 4px; text-align: center; border-bottom: 1px solid #dee2e6; font-size: 0.8rem; }
.cal-header.today { background: #e3f2fd; }
.cal-cell { border-bottom: 1px solid #f0f0f0; border-right: 1px solid #f0f0f0; min-height: 50px; }
.time-col { border-right: 1px solid #dee2e6; display: flex; align-items: center; justify-content: center; }
.time-label { font-size: 0.7rem; color: #6c757d; min-height: 50px; }
.slot-cell { cursor: pointer; }
.slot-cell:hover { background: #f8f9ff; }
.appointments-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; pointer-events: none; margin-left: 60px; }
.day-column { position: absolute; top: 0; bottom: 0; pointer-events: none; }
.apt-block { position: absolute; left: 3px; right: 3px; border-radius: 6px; padding: 4px 6px; color: white; font-size: 0.7rem; cursor: grab; pointer-events: auto; overflow: hidden; line-height: 1.3; box-shadow: 0 2px 6px rgba(0,0,0,.25); opacity: 0.92; user-select: none; }
.apt-block:active { cursor: grabbing; opacity: 0.7; }
.apt-block:hover { opacity: 1; z-index: 100; }
.apt-time { font-weight: 600; font-size: 0.65rem; }
.apt-client { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }
.apt-op { font-size: 0.6rem; opacity: 0.85; }
.apt-resize-handle { position: absolute; bottom: 0; left: 0; right: 0; height: 8px; cursor: ns-resize; background: rgba(255,255,255,0.25); border-radius: 0 0 6px 6px; }
.apt-resize-handle:hover { background: rgba(255,255,255,0.45); height: 10px; }
.drag-ghost { position: absolute; z-index: 9999; border-radius: 6px; padding: 4px 6px; color: white; font-size: 0.7rem; pointer-events: none; opacity: 0.8; box-shadow: 0 4px 12px rgba(0,0,0,.3); line-height: 1.3; }
</style>
