<script setup lang='ts'>
import { trans } from "laravel-vue-i18n"
import BoxStatPallet from "@/Components/Pallet/BoxStatPallet.vue"
import DatePicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import { useFormatTime, retinaUseDaysLeftFromToday } from '@/Composables/useFormatTime'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'

import Popover from '@/Components/Popover.vue'
import { PalletDelivery, BoxStats, PalletReturn, PDRNotes } from '@/types/Pallet'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { inject, ref } from 'vue'
import { routeType } from "@/types/route"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import RetinaBoxNote from "@/Components/Retina/Storage/RetinaBoxNote.vue"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import PalletEditCustomerReference from "@/Components/Pallet/PalletEditCustomerReference.vue"


const props = defineProps<{
    box_stats: BoxStats
    data_pallet: PalletDelivery | PalletReturn
    updateRoute: {
        route: routeType
    }
    notes_data: PDRNotes[]
}>()

const layout = inject('layout', layoutStructure)
const deliveryListError = inject('deliveryListError', [])
const isLoadingSetEstimatedDate = ref<string | boolean>(false)


// Method: On change estimated date
const onChangeEstimateDate = async (close: Function) => {
    try {
        router.patch(
            route(props.updateRoute.route.name, props.updateRoute.route.parameters),
            {
                estimated_delivery_date: props.data_pallet.estimated_delivery_date
            },
            {
                onStart: () => isLoadingSetEstimatedDate.value = true,
                onError: () => {
                    notify({
                        title: "Failed",
                        text: "Failed to update the Delivery date, try again.",
                        type: "error",
                    })
                },
                onSuccess: () => {
                    const index = deliveryListError?.indexOf('estimated_delivery_date');
                    if (index > -1) {
                        deliveryListError?.splice(index, 1);
                    }
                    close()
                },
                onFinish: () => isLoadingSetEstimatedDate.value = false,
            })
    } catch (error) {
        console.log(error)
        notify({
            title: "Failed",
            text: "Failed to update the Delivery date, try again.",
            type: "error",
        })
    }
}

const disableBeforeToday = (date: Date) => {
    const today = new Date()
    // Set time to 00:00:00 for comparison purposes
    today.setHours(0, 0, 0, 0)
    return date < today
}
</script>

<template>
    <div class="h-min grid md:grid-cols-4 border-b border-gray-200 divide-y md:divide-y-0 divide-x divide-gray-200">
        <!-- Box: Status -->
        <BoxStatPallet :color="{ bgColor: layout.app.theme[0], textColor: layout.app.theme[1] }" class=" pb-2 py-2 px-3"
            :tooltip="trans('Detail')" :label="data_pallet.state" icon="fal fa-truck-couch">
            <div class="flex items-center w-full flex-none gap-x-2 mb-2" :class="box_stats.delivery_state.class">
                <dt class="flex-none">
                    <span class="sr-only">{{ box_stats.delivery_state.tooltip }}</span>
                    <FontAwesomeIcon :icon='box_stats.delivery_state.icon'
                        fixed-width aria-hidden='true' />
                </dt>
                <dd class="">{{ box_stats.delivery_state.tooltip }}</dd>
            </div>

            <div class="flex items-center w-full flex-none gap-x-2" :class="deliveryListError.includes('estimated_delivery_date') ? 'errorShake' : ''">
                <dt class="flex-none">
                    <span class="sr-only">{{ box_stats.delivery_state.tooltip }}</span>
                    <FontAwesomeIcon :icon="['fal', 'calendar-day']" :class='box_stats?.delivery_status?.class'
                        fixed-width aria-hidden='true' />
                </dt>

                <Popover v-if="data_pallet.state === 'in_process'" position="">
                    <template #button>
                        <div v-if="data_pallet.estimated_delivery_date"
                            v-tooltip="retinaUseDaysLeftFromToday(data_pallet.estimated_delivery_date)"
                            class="group text-sm text-gray-500">
                            {{ useFormatTime(data_pallet.estimated_delivery_date) }}
                            <FontAwesomeIcon icon='fal fa-pencil' size="sm"
                                class='text-gray-400 group-hover:text-gray-600' fixed-width aria-hidden='true' />
                        </div>

                        <div v-else class="text-sm text-gray-500 hover:text-gray-600 underline">
                            {{ trans('Set estimated delivery') }}
                        </div>
                    </template>

                    <template #content="{ close }">
                        <DatePicker v-model="data_pallet.estimated_delivery_date"
                            @update:modelValue="() => onChangeEstimateDate(close)" inline auto-apply
                            :xdisabled-dates="disableBeforeToday" :enable-time-picker="false" />
                        
                        <div v-if="isLoadingSetEstimatedDate" class="absolute inset-0 bg-white/70 flex items-center justify-center">
                            <LoadingIcon class="text-5xl" />
                        </div>
                    </template>
                </Popover>

                <div v-else>
                    <dd class="text-sm text-gray-500">{{ data_pallet.estimated_delivery_date ?
                        useFormatTime(data_pallet.estimated_delivery_date) : trans('Not Set') }}</dd>
                </div>

            </div>
        </BoxStatPallet>


        <!-- Box: Notes -->
        <BoxStatPallet :color="{ bgColor: layout.app.theme[0], textColor: layout.app.theme[1] }" class="pb-2 pt-2 px-3"
            :tooltip="trans('Notes')" :percentage="0">
            <!-- Customer reference -->
            <div class="mb-1">
                <PalletEditCustomerReference
                    :dataPalletDelivery="data_pallet"
                    :updateRoute="updateRoute.route"
					:disabled="data_pallet?.state !== 'in_process' && data_pallet?.state !== 'submitted'"
                />
            </div>

            <div class="grid gap-y-3">
                <RetinaBoxNote
                    v-for="(note, index) in notes_data"
                    :key="index+note.label"
                    :noteData="note"
                    :updateRoute="updateRoute.route"
                />

            </div>
            
            <!-- <div class="flex items-end gap-x-3">
                <dt class="flex-none">
                    <span class="sr-only">Total pallet</span>
                    <FontAwesomeIcon icon='fal fa-pallet' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class="text-gray-600 leading-none text-3xl font-medium">{{ data_pallet.number_pallets }}</dd>
            </div> -->
        </BoxStatPallet>
        

        <!-- Box: Order summary -->
        <BoxStatPallet class="sm:col-span-2 border-t sm:border-t-0 border-gray-300">
            <section aria-labelledby="summary-heading" class="rounded-lg px-4 py-4 sm:px-6 lg:mt-0">

                <OrderSummary :order_summary="box_stats.order_summary" :currency_code="box_stats?.order_summary?.currency?.data?.code"/>

                <!-- <div class="mt-6">
                    <button type="submit"
                        class="w-full rounded-md border border-transparent bg-indigo-600 px-4 py-3 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-50">Checkout</button>
                </div> -->
            </section>
        </BoxStatPallet>
    </div>
</template>