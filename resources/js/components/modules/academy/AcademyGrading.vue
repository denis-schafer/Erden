<template>
    <div class="academy-grading p-3">
        <h2 class="mb-3">Calificaciones</h2>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Alumno</th><th>Examen</th><th>Curso</th><th>Puntaje</th><th>Máx</th><th>%</th><th>Resultado</th><th>Fecha</th><th>Acciones</th></tr></thead>
                <tbody>
                    <tr v-for="a in attempts" :key="a.id">
                        <td>{{ a.first_name }} {{ a.last_name }}</td><td>{{ a.exam_name }}</td><td>{{ a.course_name }}</td>
                        <td>{{ a.score }}</td><td>{{ a.max_score }}</td>
                        <td>{{ a.max_score > 0 ? Math.round((a.score / a.max_score) * 100) : 0 }}%</td>
                        <td><span class="badge" :class="a.passed ? 'bg-success' : 'bg-danger'">{{ a.passed ? 'Aprobado' : 'Desaprobado' }}</span></td>
                        <td>{{ a.created_at ? new Date(a.created_at).toLocaleDateString() : '-' }}</td>
                        <td><button class="btn btn-sm btn-outline-info" @click="viewAttempt(a)"><i class="bi bi-eye"></i></button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="!attempts.length" class="text-center py-5 text-muted">No hay intentos de exámenes aún.</div>
        <div v-if="detail" class="modal-backdrop fade show" @click="detail = null"></div>
        <div v-if="detail" class="modal fade show d-block" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Intento: {{ detail.student?.first_name }} {{ detail.student?.last_name }} - {{ detail.exam?.name }}</h5>
                        <button type="button" class="btn-close" @click="detail = null"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Puntaje:</strong> {{ detail.score }} / {{ detail.max_score }} ({{ detail.max_score > 0 ? Math.round((detail.score / detail.max_score) * 100) : 0 }}%) - 
                        <span class="badge" :class="detail.passed ? 'bg-success' : 'bg-danger'">{{ detail.passed ? 'Aprobado' : 'Desaprobado' }}</span></p>
                        <hr>
                        <div v-for="(ans, i) in detail.answers" :key="ans.id" class="mb-3">
                            <p class="fw-bold">{{ i + 1 }}. {{ ans.question_text }} <small class="text-muted">({{ ans.max_points }} pts)</small></p>
                            <div v-for="opt in ans.all_options" :key="opt.id" class="d-flex align-items-center mb-1">
                                <span :class="{
                                    'text-success fw-bold': opt.is_correct,
                                    'text-danger': opt.id === ans.selected_option_id && !ans.is_correct
                                }">{{ opt.option_text }}
                                <i v-if="opt.is_correct" class="bi bi-check-circle-fill text-success ms-1"></i>
                                <i v-if="opt.id === ans.selected_option_id && !opt.is_correct" class="bi bi-x-circle-fill text-danger ms-1"></i>
                                </span>
                            </div>
                            <small class="text-muted">{{ ans.is_correct ? '✓ Correcta' : '✗ Incorrecta' }} - {{ ans.points_earned }} pts</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="detail = null">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import api from '../../../services/api';
const attempts = ref([]); const detail = ref(null);
async function loadAttempts() { try { const { data } = await api.get('/academy/grading'); attempts.value = data.data || data; } catch (e) { console.error(e); } }
async function viewAttempt(a) { try { const { data } = await api.get('/academy/grading/' + a.id); detail.value = data; } catch (e) { console.error(e); } }
onMounted(loadAttempts);
</script>