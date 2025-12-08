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

const uploads = computed(() => {
    return echoPersonal.progressBars?.Upload || {};
});

const hasUploads = computed(() => Object.keys(uploads.value).length > 0);

</script>

<template>
    <Modal :isOpen="isOpen" @onClose="$emit('close')" width="w-full max-w-lg">
        <div class="p-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">
                {{ trans('Cloning Portfolios') }}
            </h3>

            <div v-if="hasUploads" class="space-y-4">
                <div v-for="(upload, key) in uploads" :key="key" class="border-b pb-4 last:border-b-0 last:pb-0">
                    <div class="mb-2 flex justify-between text-sm text-gray-600">
                        <span>{{ trans('Progress') }}</span>
                        <span>{{ Math.round((upload.done / upload.total) * 100) }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-lime-600 h-2.5 rounded-full transition-all duration-300" :style="{ width: (upload.done / upload.total) * 100 + '%' }"></div>
                    </div>
                    <div class="mt-2 text-sm text-gray-500 flex justify-between">
                        <span>{{ upload.done }} / {{ upload.total }} {{ trans('items') }}</span>
                        <span class="text-xs text-gray-400">
                            {{ trans('Success') }}: <span class="text-lime-600">{{ upload.data.number_success }}</span> |
                            {{ trans('Failed') }}: <span class="text-red-500">{{ upload.data.number_fails }}</span>
                        </span>
                    </div>
                </div>
            </div>
            <div v-else class="flex flex-col justify-center items-center py-8 text-gray-500 gap-y-2">
                <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin text-2xl' aria-hidden='true' />
                <span>{{ trans('Waiting for progress...') }}</span>
            </div>

            <div class="mt-6 flex justify-end">
                <button @click="$emit('close')" type="button" class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:text-sm">
                    {{ trans('Close') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
