<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faChild, faCube, faMicrophone, faBan, faMicrophoneSlash } from '@fas';
import { library } from '@fortawesome/fontawesome-svg-core';
import { computed } from 'vue';

library.add(faChild, faCube, faMicrophone, faBan, faMicrophoneSlash);

const props = defineProps<{
    stats: {
        number_products_state_in_process: number;
        number_products_state_active: number;
        number_products_state_discontinuing: number;
        number_products_state_discontinued: number;
        number_current_products: number;
        number_products_status_out_of_stock: number;
        number_products: number;
        number_products_status_not_for_sale: number;
    }
}>();

const outOfStockPercentage = computed(() => {
    if (props.stats.number_current_products === 0) return 0;
    return Math.round((props.stats.number_products_status_out_of_stock / props.stats.number_current_products) * 100);
});
</script>

<template>
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm w-full h-fit">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">{{ trans('Stats') }}</h3>
        </div>
        <div class="p-4">
             <!-- State Stats -->
             <div class="grid grid-cols-4 min-w-max divide-x divide-gray-200 border border-gray-200 rounded mb-4">
                <!-- In Process -->
                <div v-tooltip="trans('In Process')" class="flex justify-center items-center gap-2 p-2 cursor-pointer hover:bg-gray-50 bg-white first:rounded-l">
                     <FontAwesomeIcon :icon="faChild" class="text-gray-500" />
                     <span class="text-sm text-gray-900">{{ stats.number_products_state_in_process }}</span>
                </div>
                <!-- Active -->
                <div v-tooltip="trans('Active')" class="flex justify-center items-center gap-2 p-2 cursor-pointer hover:bg-gray-50 bg-white">
                     <FontAwesomeIcon :icon="faCube" class="text-gray-500" />
                     <span class="text-sm text-gray-900">{{ stats.number_products_state_active }}</span>
                </div>
                <!-- Discontinuing -->
                <div v-tooltip="trans('Discontinuing')" class="flex justify-center items-center gap-2 p-2 cursor-pointer hover:bg-gray-50 bg-white">
                     <FontAwesomeIcon :icon="faCube" class="text-orange-500" />
                     <span class="text-sm text-gray-900">{{ stats.number_products_state_discontinuing }}</span>
                </div>
                <!-- Discontinued -->
                <div v-tooltip="trans('Discontinued')" class="flex justify-center items-center gap-2 p-2 cursor-pointer hover:bg-gray-50 bg-white last:rounded-r">
                     <FontAwesomeIcon :icon="faCube" class="text-gray-300" />
                     <span class="text-sm text-gray-900">{{ stats.number_products_state_discontinued }}</span>
                </div>
            </div>

            <!-- Additional Stats -->
            <div class="grid grid-cols-3 min-w-max divide-x divide-gray-200 border border-gray-200 rounded">
                <!-- Product Online -->
                <div v-tooltip="trans('Product Online')" class="flex justify-center items-center gap-2 p-2 cursor-pointer hover:bg-gray-50 bg-white first:rounded-l">
                     <FontAwesomeIcon :icon="faMicrophone" class="text-blue-500" />
                     <span class="text-sm text-gray-900">{{ stats.number_current_products }}</span>
                </div>
                <!-- Out of Stock -->
                <div v-tooltip="trans('Out of Stock')" class="flex justify-center items-center gap-2 p-2 cursor-pointer hover:bg-gray-50 bg-white">
                     <FontAwesomeIcon :icon="faBan" class="text-red-500" />
                     <div class="flex items-baseline gap-1">
                        <span class="text-sm text-gray-900">{{ stats.number_products_status_out_of_stock }}</span>
                        <span class="text-xs text-gray-500">({{ outOfStockPercentage }}%)</span>
                     </div>
                </div>
                <!-- Product Offline -->
                <div v-tooltip="trans('Product Offline')" class="flex justify-center items-center gap-2 p-2 cursor-pointer hover:bg-gray-50 bg-white last:rounded-r">
                     <FontAwesomeIcon :icon="faMicrophoneSlash" class="text-gray-400" />
                     <span class="text-sm text-gray-900">{{ stats.number_products_status_not_for_sale }}</span>
                </div>
            </div>
        </div>
    </div>
</template>