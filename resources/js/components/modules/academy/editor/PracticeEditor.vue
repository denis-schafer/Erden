<template>
    <div class="practice-editor mb-3">
        <div v-if="type === 'fill_blank'" class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="fw-bold">Completá el código:</p>
                <p v-html="highlightedCode"></p>
                <div class="mb-2">
                    <input v-model="userAnswer" class="form-control font-monospace" :placeholder="'Escribí el código faltante...'" @keyup.enter="checkAnswer">
                </div>
                <div v-if="feedback" class="alert py-2 small" :class="feedbackCorrect ? 'alert-success' : 'alert-danger'">
                    {{ feedback }}
                </div>
                <button class="btn btn-sm" :class="feedback ? 'btn-outline-secondary' : 'btn-primary'" @click="feedback ? resetExercise() : checkAnswer()">
                    {{ feedback ? 'Reintentar' : 'Verificar' }}
                </button>
            </div>
        </div>
        <div v-else-if="type === 'multiple_choice'" class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="fw-bold">Elegí la opción correcta:</p>
                <p>{{ question }}</p>
                <div v-for="(opt, j) in options" :key="j" class="form-check mb-2">
                    <input type="radio" :value="j" v-model="selectedOption" class="form-check-input" :id="'pe' + j" :name="'pe_' + uid" :disabled="feedback">
                    <label class="form-check-label" :for="'pe' + j">{{ String.fromCharCode(65 + j) }}. {{ opt.text }}</label>
                </div>
                <div v-if="feedback" class="alert py-2 small" :class="feedbackCorrect ? 'alert-success' : 'alert-danger'">
                    {{ feedback }}
                </div>
                <button v-if="!feedback" class="btn btn-sm btn-primary" @click="checkMC" :disabled="selectedOption === null">Verificar</button>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    type: { type: String, default: 'fill_blank' },
    code: { type: String, default: '' },
    blank: { type: String, default: '___' },
    answer: { type: String, default: '' },
    question: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    correctIndex: { type: Number, default: 0 },
});

const uid = computed(() => Math.random().toString(36).substring(2, 8));

const userAnswer = ref('');
const selectedOption = ref(null);
const feedback = ref('');
const feedbackCorrect = ref(false);

const highlightedCode = computed(() => {
    if (!props.code) return '';
    return props.code.replace(props.blank, '<span class="bg-warning px-1 rounded">' + props.blank + '</span>');
});

const checkAnswer = () => {
    if (userAnswer.value.trim().toLowerCase() === props.answer.toLowerCase()) {
        feedback.value = '¡Correcto! Muy bien.';
        feedbackCorrect.value = true;
    } else {
        feedback.value = 'Incorrecto. La respuesta es: ' + props.answer;
        feedbackCorrect.value = false;
    }
};

const checkMC = () => {
    if (selectedOption.value === props.correctIndex) {
        feedback.value = '¡Correcto!';
        feedbackCorrect.value = true;
    } else {
        const correctText = props.options[props.correctIndex]?.text || '';
        feedback.value = 'Incorrecto. La respuesta correcta es: ' + correctText;
        feedbackCorrect.value = false;
    }
};

const resetExercise = () => {
    feedback.value = '';
    feedbackCorrect.value = false;
    userAnswer.value = '';
    selectedOption.value = null;
};
</script>