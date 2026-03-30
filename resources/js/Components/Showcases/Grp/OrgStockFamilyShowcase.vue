<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faImage, faCheckCircle, faExclamationCircle, faTimesCircle, faPauseCircle,
    faDotCircle, faExclamationTriangle, faSkullCrossbones, faArrowAltCircleUp,
    faBox
} from '@fal'
import SalesAnalyticsCompact from '@/Components/Product/SalesAnalyticsCompact.vue'
import Image from '@/Components/Image.vue'
import { trans } from 'laravel-vue-i18n'

library.add(
    faImage, faCheckCircle, faExclamationCircle, faTimesCircle, faPauseCircle,
    faDotCircle, faExclamationTriangle, faSkullCrossbones, faArrowAltCircleUp,
    faBox
)

const props = defineProps<{
    data: {
        family_data: {
            name: string
            code: string
            description?: string | null
            image?: any
            state: { label: string; icon: string; class: string; tooltip: string }
        }
        stock_counts: { label: string; count: number; icon: string; class: string }[]
        quantity_status: { label: string; count: number; icon: string; class: string }[]
        dispatched: {
            today: number
            last_week: number
            last_month: number
            last_year: number
            all: number
        }
    }
    salesData?: object
}>()
</script>

<template>
    <div class="px-4 pb-8 m-5">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-8 gap-4 mt-4">

            <!-- Left: Family card (image + details) -->
            <div class="col-span-1 md:col-span-1 lg:col-span-2">
                <div class="bg-white p-4 rounded-2xl shadow-md border border-gray-200">
                    <!-- Image -->
                    <div class="bg-white rounded-lg shadow mb-4 overflow-hidden">
                        <div class="w-full aspect-square" :class="data.family_data.image ? '' : 'h-32'">
                            <Image
                                v-if="data.family_data.image"
                                :src="data.family_data.image"
                                class="w-full h-full object-cover object-center rounded-t-lg"
                            />
                            <div v-else class="flex justify-center items-center bg-gray-100 w-full h-full">
                                <FontAwesomeIcon :icon="['fal', 'box']" class="w-10 h-10 text-gray-400" />
                            </div>
                        </div>
                    </div>

                    <!-- Name + Code + State -->
                    <div class="border-t pt-3 space-y-2">
                        <div class="text-lg font-semibold text-gray-800">{{ data.family_data.name }}</div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-mono bg-gray-100 text-gray-600 px-2 py-0.5 rounded">
                                {{ data.family_data.code }}
                            </span>
                            <span
                                v-if="data.family_data.state"
                                v-tooltip="data.family_data.state.tooltip"
                                class="flex items-center gap-1 text-xs px-2 py-0.5 rounded-full border"
                            >
                                <FontAwesomeIcon :icon="data.family_data.state.icon" :class="data.family_data.state.class" />
                                <span :class="data.family_data.state.class">{{ data.family_data.state.label }}</span>
                            </span>
                        </div>
                        <p v-if="data.family_data.description" class="text-sm text-gray-500 leading-relaxed line-clamp-3">
                            {{ data.family_data.description }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Middle: Stock stats -->
            <div class="col-span-1 md:col-span-2 lg:col-span-4 space-y-4">

                <!-- Stock state counts -->
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ trans('SKU Status') }}</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div v-for="item in data.stock_counts" :key="item.label"
                            class="flex items-center gap-2 p-2 rounded-lg bg-gray-50">
                            <FontAwesomeIcon :icon="item.icon" :class="item.class" class="text-xl" fixed-width />
                            <div>
                                <div class="text-xl font-bold text-gray-800 leading-none">{{ item.count }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ item.label }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quantity status -->
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ trans('Quantity Status') }}</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <div v-for="item in data.quantity_status" :key="item.label"
                            class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                            <div class="flex items-center gap-2">
                                <FontAwesomeIcon :icon="item.icon" :class="item.class" fixed-width />
                                <span class="text-sm text-gray-600">{{ item.label }}</span>
                            </div>
                            <span class="font-semibold text-gray-800 ml-2">{{ item.count }}</span>
                        </div>
                    </div>
                </div>

                <!-- Dispatched -->
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ trans('Dispatched') }}</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-center">
                        <div class="p-2 rounded-lg bg-gray-50">
                            <div class="text-lg font-bold text-gray-800">{{ data.dispatched.today }}</div>
                            <div class="text-xs text-gray-500">{{ trans('Today') }}</div>
                        </div>
                        <div class="p-2 rounded-lg bg-gray-50">
                            <div class="text-lg font-bold text-gray-800">{{ data.dispatched.last_week }}</div>
                            <div class="text-xs text-gray-500">{{ trans('Last week') }}</div>
                        </div>
                        <div class="p-2 rounded-lg bg-gray-50">
                            <div class="text-lg font-bold text-gray-800">{{ data.dispatched.last_month }}</div>
                            <div class="text-xs text-gray-500">{{ trans('Last month') }}</div>
                        </div>
                        <div class="p-2 rounded-lg bg-gray-50">
                            <div class="text-lg font-bold text-gray-800">{{ data.dispatched.last_year }}</div>
                            <div class="text-xs text-gray-500">{{ trans('Last year') }}</div>
                        </div>
                        <div class="p-2 rounded-lg bg-blue-50 border border-blue-100">
                            <div class="text-lg font-bold text-blue-700">{{ data.dispatched.all }}</div>
                            <div class="text-xs text-blue-500">{{ trans('All time') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Sales analytics (compact, on the right) -->
            <div class="col-span-1 md:col-span-3 lg:col-span-2">
                <SalesAnalyticsCompact v-if="salesData" :salesData="salesData" />
            </div>

        </div>
    </div>
</template>
