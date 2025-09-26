<script setup lang='ts'>
import { ref, onMounted } from 'vue'
import JsBarcode from 'jsbarcode'
import TabSelector from '@/Components/Elements/TabSelector.vue'
import { routeType } from '@/types/route'

const props = defineProps<{
    data: {
        updateRoute: routeType
        radioTabs: {
            [key: string]: boolean
        }
        stats: {
            max_volume?: number
            max_weight?: number
            number_org_stock_slots: number
            empty_stock_slots: number
        }
    }
}>()

// Tabs radio: v-model
const radioValue = ref<string[]>(Object.keys(props.data.radioTabs).filter(key => props.data.radioTabs[key]))
// const radioValue = ref<string[]>(props.data?.radioTabs ? Object.keys(props.data.radioTabs).filter(key => props.data.radioTabs[key]) : ['allow_stocks', 'allow_fulfilment', 'allow_dropshipping'])

// Tabs radio: options
const optionRadio = [
    {
        value: 'allow_stocks',
        label: 'Allow stocks'
    },
    {
        value: 'allow_fulfilment',
        label: 'Allow fulfilment'
    },
    {
        value: 'allow_dropshipping',
        label: 'Allow dropshipping'
    },
]

// Blueprint: data
const blueprint = [
    {
        name: 'Max Volume',
        value: props.data.stats.max_volume || 0
    },
    {
        name: 'Max Weight',
        value: props.data.stats.max_weight || 0
    },
    {
        name: 'Stock Slots',
        value: props.data.stats.number_org_stock_slots
    },
    {
        name: 'Empty Stock Slots',
        value: props.data.stats.empty_stock_slots
    },
]

onMounted(() => {
    JsBarcode('#locationBarcode', route().routeParams.location, {
        lineColor: "rgb(41 37 36)",
        width: 2,
        height: 70,
        displayValue: true
    });
})


</script>

<template>

    <div class="px-8 grid max-w-2xl grid-cols-1 gap-x-2 gap-y-8 lg:max-w-7xl lg:grid-cols-3 pt-4">
        <!-- Section: data -->
        <div class="w-full space-y-4 col-span-2">
            <!-- Section: Radio -->
            <TabSelector :optionRadio="optionRadio" :radioValue="radioValue" :updateRoute="data?.updateRoute"/>
        
            <!-- Section: Stats -->
            <dl class="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 sm:gap-y-16 lg:gap-x-8">
                <div v-for="feature in blueprint" :key="feature.name" class="px-2 border-t border-gray-200 pt-4">
                    <dt class="font-medium">{{ feature.name }}</dt>
                    <dd class="mt-2 text-sm text-gray-500">{{ feature.value }}</dd>
                </div>
            </dl>
        </div>

        <!-- Section: Barcode -->
        <div class="flex justify-end gap-4 sm:gap-6 lg:gap-8">
            <svg id="locationBarcode" class="bg-gray-100"></svg>
        </div>
    </div>
</template>