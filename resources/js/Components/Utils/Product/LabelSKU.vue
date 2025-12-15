<script setup lang="ts">
import FractionDisplay from '@/Components/DataDisplay/FractionDisplay.vue';
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n'
import Modal from "@/Components/Utils/Modal.vue"
import { ref } from 'vue'

const props = defineProps<{
    product: {
        unit: string
        units: number
    }
    trade_units: {
        tradeUnit: {
            id: number
            code: string
            name: string
            slug: string
        }
        pick_fractional: {
            integer: number
            fraction_numerator: number
            fraction_denominator: number
        } | null
    }[]
    routeFunction?: Function
    keyPicking?: string
}>()

const key = props.keyPicking ?? 'pick_fractional'


const isOpenModal = ref(false)
</script>

<template>
    <div
        v-tooltip="trade_units.length > 1 ? trans('Click to view all trade units detail') : ''"
        class="border border-solid hover:opacity-80 py-1 px-3 rounded-md hover:cursor-pointer me-2 flex border-green-600"
        @click="isOpenModal = true"
    >
        <div v-if="trade_units.length == 1" class="text-teal-600 whitespace-nowrap w-full">
            <span class=""> &#8623; SKU </span>
            <span class="font-bold">
                <FractionDisplay v-if="trade_units[0][key]" :fractionData="trade_units[0][key]" />
            </span>
        </div>
        <div v-else class="text-teal-600 whitespace-nowrap w-full">
            <span class="">Multi Trade Units</span>
        </div>

        <div class="border-s border-green-600 text-gray-700 whitespace-nowrap font-bold ms-2 ps-2">
            {{ product.units + " " + product.unit }}
        </div>

        <Modal :isOpen="isOpenModal" @onClose="isOpenModal = false" width="max-w-3xl w-full">
            <div class="grid grid-cols-2 font-bold mb-4">
                <div class="text-left text-lg">
                    {{ trans('Trade Unit SKU Details') }}
                </div>
            </div>

            <div class="grid grid-cols-5 mt-3 text-sm font-bold">
                <div class="text-left">
                    Code
                </div>
                <div class="text-left col-span-3">
                    Name
                </div>
                <div class="text-right">
                    SKU
                </div>	
            </div>

            <div v-for="tUnit in trade_units" :key="tUnit.tradeUnit?.id" class="grid grid-cols-5 mt-3 text-sm min-h-8">
                <div class="text-left flex items-center">
                    <Link v-if="routeFunction" :href="routeFunction(tUnit.tradeUnit)" class="primaryLinkxx">
                        {{ tUnit.tradeUnit?.code }}
                    </Link>
                    <span v-else>
                        {{ tUnit.tradeUnit?.code }}
                    </span>
                </div>

                <div class="text-left col-span-3 flex items-center">
                    {{ tUnit.tradeUnit?.name }}
                </div>

                <div class="justify-items-end text-teal-600 whitespace-nowrap flex justify-end">
                    <span class="border border-solid hover:opacity-80 py-1 px-3 rounded-md hover:cursor-pointer flex border-green-600 w-fit">
                        <span> &#8623; SKU </span>
                        <span class="font-bold ms-2">
                            <FractionDisplay v-if="tUnit[key]" :fractionData="tUnit[key]" />
                        </span>
                    </span>
                </div>
            </div>
        </Modal>
    </div>
</template>