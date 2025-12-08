<script setup lang="ts">
import Modal from "@/Components/Utils/Modal.vue";
import { useEchoGrpPersonal } from "@/Stores/echo-grp-personal";
import { computed } from "vue";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faSpinnerThird } from '@fad';
import { library } from '@fortawesome/fontawesome-svg-core';

library.add(faSpinnerThird);

const props = defineProps<{
    isOpen: boolean
}>();

const emit = defineEmits(['close']);

const echoPersonal = useEchoGrpPersonal();

const activeJobs = computed(() => {
    return echoPersonal.progressBars?.['clone_portfolio'] || {};
});

const hasActiveJobs = computed(() => Object.keys(activeJobs.value).length > 0);

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
                {{ trans('Cloning Your Portfolio') }}
            </h3>

            <div v-if="hasActiveJobs" class="space-y-6">
                <div v-for="(job, key) in activeJobs" :key="key" class="border-b pb-6 last:border-b-0 last:pb-0">

                    <div class="mb-2 flex justify-between items-end">
                        <span class="text-sm font-medium text-gray-700">{{ trans('Progress') }}</span>
                        <span class="text-2xl font-bold text-gray-900 tabular-nums">
                            {{ getPercent(job.done, job.total) }}%
                        </span>
                    </div>

                    <div class="w-full bg-gray-200 rounded-full h-3 flex overflow-hidden">
                        <div
                            class="bg-lime-600 h-full transition-all duration-300 ease-out"
                            :style="{ width: getWidth(job.data.number_success, job.total) }"
                        ></div>
                        <div
                            class="bg-red-500 h-full transition-all duration-300 ease-out"
                            :style="{ width: getWidth(job.data.number_fails, job.total) }"
                        ></div>
                    </div>

                    <div class="mt-2 text-sm flex justify-between items-start text-gray-500">
                        <div>
                            {{ job.done }} / {{ job.total }} {{ trans('items') }}
                        </div>

                        <div class="text-xs text-right space-x-3">
                            <span class="inline-flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-lime-600 inline-block"></span>
                                {{ trans('Success') }}: <span class="font-medium text-gray-700">{{ job.data.number_success }}</span>
                            </span>
                            <span class="border-r border-gray-300 mx-1"></span>
                            <span class="inline-flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
                                {{ trans('Failed') }}: <span class="font-medium text-gray-700">{{ job.data.number_fails }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="flex flex-col justify-center items-center py-8 text-gray-500 gap-y-3">
                <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin text-3xl text-indigo-500' aria-hidden='true' />
                <span class="text-sm font-medium">{{ trans('Please Wait For A Moment...') }}</span>
            </div>

            <div class="mt-6 flex justify-end">
                <button @click="$emit('close')" type="button" class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:text-sm">
                    {{ trans('Close') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
