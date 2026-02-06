<script setup lang="ts">
import FractionDisplay from '@/Components/DataDisplay/FractionDisplay.vue';
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n'
import Modal from "@/Components/Utils/Modal.vue"
import { computed, ref } from 'vue'

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
    hideUnit?: boolean
    forceOpenModal?: boolean
    hoverTooltip?: string
    routeFunction?: Function
    keyPicking?: string
}>()

const key = props.keyPicking ?? 'pick_fractional'


const isOpenModal = ref(false)

const textTooltip = computed(() => {
    if(props.hoverTooltip) return props.hoverTooltip;
    return props.trade_units.length > 1  ? trans('Click to view all trade units detail') : ''
})

const openModal = () => {
    if(props.trade_units.length >= 1 || props.forceOpenModal) {
        isOpenModal.value = true
    };
}

const getAdditionalClass = () => {
    let multiTradeUnit = props.trade_units.length >= 1
    let className = multiTradeUnit ? 'border-green-600' : 'border-red-600';

    if(props.forceOpenModal || multiTradeUnit) {
        className += ' hover:cursor-pointer hover:opacity-80';
    }

    return className;
}
</script>

<template>
    <div
        v-tooltip="textTooltip"
        class="border border-solid py-1 px-3 rounded-md me-2 flex"
        :class="getAdditionalClass()"
        @click="openModal"
    >
        <div v-if="trade_units.length == 1" class="text-teal-600 whitespace-nowrap w-full">
            <span class=""> &#8623; SKU </span>
            <span class="font-bold">
                <FractionDisplay v-if="trade_units[0][key]" :fractionData="trade_units[0][key]" />
            </span>
        </div>
        <div v-else-if="trade_units.length > 1" class="text-teal-600 whitespace-nowrap w-full">
            <span class="">{{ trans('Multi Trade Units') }}</span>
        </div>
        <div v-else class="text-red-500 whitespace-nowrap w-full">
            <span class="">{{ trans('No Trade Units') }}</span>
        </div>

        <div 
            v-if="!hideUnit"
            class="border-s text-gray-700 whitespace-nowrap font-bold ms-2 ps-2"
            :class="trade_units.length >= 1 ? 'border-green-600' : 'border-red-600'"
        >
            {{ product.units + " " + product.unit }}
        </div>

        <Modal :isOpen="isOpenModal" @onClose="isOpenModal = false" width="max-w-3xl w-full">
            <slot name="modalBody">
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
                        <slot name="col_code" :data="tUnit">
                            <Link v-if="routeFunction" :href="routeFunction(tUnit.tradeUnit)" class="primaryLinkxx">
                                {{ tUnit.tradeUnit?.code }}
                            </Link>
                            <span v-else>
                                {{ tUnit.tradeUnit?.code }}
                            </span>
                        </slot>
                    </div>
    
                    <div class="text-left col-span-3 flex items-center">
                        <slot name="col_name" :data="tUnit">
                            {{ tUnit.tradeUnit?.name }}
                        </slot>
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
            </slot>
        </Modal>
    </div>
</template>