<script setup lang='ts'>
import { onMounted } from 'vue'
import { trans } from 'laravel-vue-i18n'
/* import CountUp from 'vue-countup-v3' */
import { Pie } from 'vue-chartjs'
import { useLayoutStore } from "@/Stores/layout"
/* import TableStoredItemEdit from '@/Components/StoredItemMovement/TableStoredItemEdit.vue' */
import { inject, ref } from 'vue'
import { Link, router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
/* import Message from 'primevue/message'; */
import JsBarcode from 'jsbarcode'

import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from 'chart.js'
import { routeType } from "@/types/route";
import Icon from '@/Components/Icon.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { useFormatTime } from '@/Composables/useFormatTime'
import { printBarcode } from '@/Composables/printBarcode'

import { faEmptySet } from '@fas'

ChartJS.register(ArcElement, Tooltip, Legend, Colors)

const props = defineProps<{
    data: {
        stored_item: {
            total_quantity: number,
            slug: string,
        },
        pieData: {
            stats: {
                label: string,
                value: number,
            }[]
        },
        route_pallets: routeType,
        pallets: Array<{ label: string, location: string, value: number }>
        route_update_stored_item: routeType
    }
}>()

const locale = inject('locale', aikuLocaleStructure)

const environment = useLayoutStore().app.environment
const isLoading = ref(false)
const _editTable = ref(null)
const totalQty = props.data.pallets.reduce((total, item) => total + item.quantity, 0)
const options = {
    responsive: true,
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            titleFont: {
                size: 10,
                weight: 'lighter'
            },
            bodyFont: {
                size: 11,
                weight: 'bold'
            }
        },
    }
}

const onChangeStoredItem = (data) => {
    router.patch(
        route(props.data.route_update_stored_item.name, props.data.route_update_stored_item.parameters),
        { pallets: data },
        {
            onBefore: () => {
                isLoading.value = true
            },
            onSuccess: () => {
                if (_editTable.value) _editTable.value.editable = false
            },
            onError: (error: {} | string) => {
                isLoading.value = false
                notify({
                    title: 'Something went wrong.',
                    text: 'Failed to save',
                    type: 'error',
                })
            }
        })
}

// Generate link to pallet
const generateLinkPallet = () => {

    switch (route().current()) {
        case 'grp.org.fulfilments.show.crm.customers.show.stored-items.show':
            return route(
                'grp.org.fulfilments.show.crm.customers.show.stored-items.show',
                {
                    ...route().params,
                    tab: 'pallets',
                });
        default:
            null
    }
}

// Generate link to audit
const generateLinkAudit = (data) => {
    switch (route().current()) {
      
        default:
            return null
    }
}

const printBarcodePallet = (id: string, code: string) => {
    printBarcode(id, code)
};


onMounted(() => {
    if (props.data.stored_item.slug) {
        JsBarcode('#palletBarcode', props.data.stored_item.slug, {
            lineColor: "rgb(41 37 36)",
            width: 2,
            height: 70,
            background: "#F9FAFB",
            displayValue: true
        })
    }
})


</script>

<template>
    <div class="px-8 py-6 grid grid-cols-2 gap-x-4">

        <div class="max-w-xl mt-1 grid grid-cols-1 gap-x-6 gap-y-8 xl:gap-x-8 h-fit ">
            <div class="flex flex-col items-start">
                <div class="relative  border rounded-lg p-4 shadow-sm bg-gray-50 group">
                    <div class="text-sm font-medium text-center mb-2">Barcode</div>
                    <div class="relative">
                        <div v-if="props.data.stored_item.slug" class="relative hover:bg-black/30 rounded-lg p-2">
                            <svg id="palletBarcode" class="mx-auto group-hover:fill-black"></svg>
                        </div>
                        <div v-else
                            class="text-sm italic text-gray-400 flex flex-col justify-center items-center space-y-2">
                            <div>{{ trans("No customer reference barcode") }}</div>
                            <div>
                                <FontAwesomeIcon :icon="faEmptySet" class="text-3xl" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full overflow-hidden rounded-xl border border-gray-300">
                <div class=" flex flex-col justify-center gap-x-4 border-b border-gray-900/5 bg-gray-50 p-6">
                    <div class="space-x-1">
                        <Icon :data="data.stored_item?.state_icon" />
                        <span v-if="data.stored_item?.name">{{ data.stored_item.name }}</span>
                        <span v-else class="text-gray-500 italic">({{ trans('No name') }})</span>
                    </div>
                    <div v-tooltip="trans('Date created')" class="text-sm/6 text-gray-500 w-fit">{{
                        useFormatTime(data.stored_item?.created_at) }}</div>
                </div>

                <dl class="-my-3 divide-y divide-gray-100 px-6 py-4 text-sm/6">
                    <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ trans("Reference") }}</dt>
                        <dd class="flex items-start gap-x-2">
                            <div class="font-medium">{{ data.stored_item.reference || '-' }}</div>
                        </dd>
                    </div>

                    <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ trans("Customer") }}</dt>
                        <dd class="flex items-start gap-x-2">
                            <div class="font-medium">{{ data.stored_item?.customer_name || '-' }}</div>
                        </dd>
                    </div>

                    <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ trans("Quantity warehouse") }}</dt>
                        <dd class="flex items-start gap-x-2">
                            <div class="font-medium">{{ locale.number(data.stored_item?.total_quantity || 0) }}</div>
                        </dd>
                    </div>

                    <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ trans("Pallet") }}</dt>
                        <dd class="flex items-start gap-x-2">
                            <Link v-if="generateLinkPallet()" :href="generateLinkPallet()" class="primaryLink">
                            {{ locale.number(data.stored_item?.pallets?.length || 0) }}
                            </Link>
                            <div v-else class="font-medium">{{ locale.number(data.stored_item?.pallets?.length || 0) }}
                            </div>
                        </dd>
                    </div>

                    <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ trans("Last audit") }}</dt>
                        <dd class="flex items-start gap-x-2">
                      
                            <Link v-if="data.stored_item?.last_audit_at"
                                :href="generateLinkAudit(data)" class="primaryLink">
                            {{ useFormatTime(data.stored_item?.last_audit_at) }}
                            </Link>
                            <div v-else class="font-medium">{{ useFormatTime(data.stored_item?.last_audit_at) }}</div>
                        </dd>
                    </div>

                    <!-- <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">Stok saat ini</dt>
                        <dd class="flex items-start gap-x-2">
                            <div class="font-medium">{{ useFormatNumber(data.stored_item.quantity || 0) }} pcs</div>
                        </dd>
                    </div> -->
                </dl>
            </div>
        </div>
        <!-- <pre>{{ data.stored_item }}</pre> -->


        <!-- Box: Pie chart -->
        <div v-if="false"
            class="h-fit flex flex-col col-span-2 justify-between px-5 py-3 rounded-lg border border-gray-100 shadow tabular-nums">
            <div class="sm:flex sm:items-center">
                <div class="sm:flex-auto">
                    <h1 class="font-semibold leading-6">
                        Pallet that contain this item <span class="font-light">({{ data.pieData.stats.length }})</span>
                    </h1>
                </div>
            </div>

            <!-- Pie -->
            <div class="w-full flex justify-center items-center">
                <div class="w-40 mx-auto my-5">
                    <Pie :data="{
                        labels: data.pieData.stats.map(stat => stat.label),
                        datasets: [{
                            data: data.pieData.stats.map(stat => stat.value),
                            hoverOffset: 4
                        }]
                    }" :options="options" />
                </div>
            </div>

            <!-- Total Stored Item -->
            <div class="sm:flex sm:items-center mt-4">
                <div class="sm:flex-auto">
                    <h1 class="font-semibold leading-6">
                        Total Stored Item Quantity: <span class="font-light">{{ totalQty }}</span>
                    </h1>
                </div>
            </div>
        </div>

        <!-- Mini Table -->
        <!-- <div v-if="false" class="flex flex-col col-span-4 gap-x-5 border border-gray-100 shadow rounded-md px-5 py-3 text-gray-500">
            <TableStoredItemEdit 
                :data="data.pallets" 
                :route_pallets="data.route_pallets" 
                @Save="onChangeStoredItem" 
                :loading="isLoading"
                ref="_editTable"
            />
        </div> -->
    </div>


</template>
