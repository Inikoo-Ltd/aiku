<script setup lang="ts">


import Modal from "@/Components/Utils/Modal.vue";
import { useEchoRetinaPersonal } from "@/Stores/echo-retina-personal";
import { computed, watch, ref } from "vue";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faSpinnerThird } from '@fad';
import { faCheck } from '@fas';
import { library } from '@fortawesome/fontawesome-svg-core';

library.add(faSpinnerThird, faCheck);

const props = defineProps<{
    isOpen: boolean
}>();

const emit = defineEmits(['close', 'finished']);

const echoPersonal = useEchoRetinaPersonal();

const isFinished = ref(false);
const completedJobs = ref({});

const activeJobs = computed(() => {
    const jobs = echoPersonal.progressBars?.['clone_portfolio'] || {};
    const keys = Object.keys(jobs);
    if (keys.length === 0) return {};
    const lastKey = keys[keys.length - 1];
    return { [lastKey]: jobs[lastKey] };
});

const displayJobs = computed(() => {
    if (isFinished.value) return completedJobs.value;
    return activeJobs.value;
});

const hasActiveJobs = computed(() => Object.keys(displayJobs.value).length > 0);

watch(activeJobs, (newJobs) => {
    const jobs = Object.values(newJobs);
    if (jobs.length > 0) {
        const allFinished = jobs.every((job: any) => job.done >= job.total && job.total > 0);
        if (allFinished) {
            isFinished.value = true;
            completedJobs.value = newJobs;
        } else {
            isFinished.value = false;
        }
    }
}, { deep: true });

watch(() => props.isOpen, (val) => {
    if (!val) {
        isFinished.value = false;
        completedJobs.value = {};
    }
});

const getPercent = (done: number, total: number) => {
    if (!total) return 0;
    return Math.round((done / total) * 100);
};

const getWidth = (count: number, total: number) => {
    if (!total) return '0%';
    return (count / total) * 100 + '%';
};
</script>

<template>
    <Modal :isOpen="isOpen" @onClose="$emit('close')" width="w-full max-w-lg">
        <div class="p-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">
                {{ trans('Cloning Portfolios') }}
            </h3>

            <div v-if="hasActiveJobs" class="space-y-6">
                <div v-if="isFinished" class="bg-green-100 text-green-800 p-3 rounded-md flex items-center gap-2">
                    <FontAwesomeIcon icon="fas fa-check" />
                    <span>{{ trans('Cloning process is complete.') }}</span>
                </div>
                <div v-for="(job, key) in displayJobs" :key="key" class="border-b pb-6 last:border-b-0 last:pb-0">
                    <div class="mb-2 flex justify-between items-end">
                        <span class="text-sm font-medium text-gray-700">{{ trans('Progress') }}</span>
                        <span class="text-2xl font-bold text-gray-900 tabular-nums">
                            {{ getPercent(job.done, job.total) }}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 flex overflow-hidden">
                        <div class="bg-lime-600 h-full transition-all duration-300 ease-out" :style="{ width: getWidth(job.data.number_success, job.total) }"></div>
                        <div class="bg-red-500 h-full transition-all duration-300 ease-out" :style="{ width: getWidth(job.data.number_fails, job.total) }"></div>
                    </div>
                    <div class="mt-2 text-sm flex justify-between items-start text-gray-500">
                        <div>{{ job.done }} / {{ job.total }} {{ trans('items') }}</div>
                        <div class="text-xs text-right space-x-3">
                            <span class="inline-flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-lime-600 inline-block"></span>
                                {{ trans('Success') }}: {{ job.data.number_success }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
                                {{ trans('Failed') }}: {{ job.data.number_fails }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="py-6 space-y-6">
                <div class="mb-2 flex justify-between items-end">
                    <div class="flex items-center gap-2">
                         <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin text-indigo-500' aria-hidden='true' />
                         <span class="text-sm font-medium text-gray-700">{{ trans('Please Wait For A Moment...') }}</span>
                    </div>
                    <span class="text-2xl font-bold text-gray-300 tabular-nums">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden"></div>
                <div class="mt-2 text-sm text-gray-400 flex justify-between">
                    <span>{{ trans('Connecting...') }}</span>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button @click="$emit('close')" type="button" class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 sm:text-sm">
                    {{ trans('Close') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
