<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import DatePicker from "@vuepic/vue-datepicker"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, inject, ref } from 'vue'
import type { Component } from 'vue'
import Popover from '@/Components/Popover.vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { get } from 'lodash-es'
import { notify } from '@kyvg/vue3-notification'
import PureInput from '@/Components/Pure/PureInput.vue'

import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'

import StartEndDate from '@/Components/Utils/StartEndDate.vue'
import RecurringBillTransactions from '@/Pages/Grp/Org/Fulfilment/RecurringBillTransactions.vue'
import BoxStatsRecurringBills from '@/Components/Fulfilment/BoxStatsRecurringBills.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import axios from 'axios'
import { useDaysLeftFromToday, useFormatTime } from '@/Composables/useFormatTime'
import { BoxStats } from '@/types/Pallet'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faWaveSine } from '@far'
import { faDiamond } from '@fas'
import { faCalendarAlt } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import TablePalletDeliveries from "@/Components/Tables/Grp/Org/Fulfilment/TablePalletDeliveries.vue"
import TablePalletReturns from "@/Components/Tables/Grp/Org/Fulfilment/TablePalletReturns.vue"
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
library.add(faDiamond, faWaveSine, faCalendarAlt)


const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    transactions: {}
    pallet_deliveries: {}
    pallet_returns: {}
    status_rb: string
    updateRoute: routeType
    timeline_rb: {
        start_date: string
        end_date: string
    }
    box_stats: BoxStats
    consolidateRoute: routeType
    history: {}
    currency:{}
    service_lists?: [],
    service_list_route: routeType

    physical_good_lists?: []
    physical_good_list_route: routeType

    pallet_list_route: routeType  // to attach Service to pallet

}>()

const locale = inject('locale', aikuLocaleStructure)

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        transactions: RecurringBillTransactions,
        history: TableHistories,
        pallet_deliveries: TablePalletDeliveries,
        pallet_returns: TablePalletReturns,
    }

    return components[currentTab.value]
})

const formAddService = useForm({ service_id: null, quantity: 1, pallet_id: null, handle_date: null, historic_asset_id: null })
const formAddPhysicalGood = useForm({ outer_id: '', quantity: 1, historic_asset_id: null })
const isLoadingButton = ref<string | boolean>(false)
const isLoadingData = ref<string | boolean>(false)
const dataServiceList = ref<{ is_pallet_handling: boolean, id: number }[]>([])
const dataPalletList = ref([])

const onSubmitAddService = (data: Action, closedPopover: Function) => {
    const selectedHistoricAssetId = dataServiceList.value.filter(service => service.id == formAddService.service_id)[0]?.historic_asset_id
    // console.log('hhh', dataServiceList.value)
    formAddService.historic_asset_id = selectedHistoricAssetId
    isLoadingButton.value = 'addService'

    formAddService.post(
        route(data.route?.name, {...data.route?.parameters, historicAsset : formAddService.historic_asset_id }),
        {
            preserveScroll: true,
            onSuccess: () => {
                closedPopover()
                formAddService.reset()
            },
            onError: (errors) => {
                notify({
                    title: 'Something went wrong.',
                    text: 'Failed to add service, please try again.',
                    type: 'error',
                })
            },
            onFinish: () => {
                isLoadingButton.value = false
            }
        }
    )
}

// Tabs: Physical Goods
const dataPGoodList = ref([])
const onOpenModalAddPGood = async () => {
    isLoadingData.value = 'addPGood'
    try {
        const xxx = await axios.get(
            route(props.physical_good_list_route.name, props.physical_good_list_route.parameters)
        )
        dataPGoodList.value = xxx.data.data
    } catch (error) {
        notify({
            title: 'Something went wrong.',
            text: 'Failed to fetch Physical Goods list',
            type: 'error',
        })
    }
    isLoadingData.value = false
}
const onSubmitAddPhysicalGood = (data: Action, closedPopover: Function) => {
    const selectedHistoricAssetId = dataPGoodList.value.filter(pgood => pgood.id == formAddPhysicalGood.outer_id)[0].historic_asset_id
    formAddPhysicalGood.historic_asset_id = selectedHistoricAssetId

    isLoadingButton.value = 'addPGood'
    formAddPhysicalGood.post(
        route(data.route?.name, {...data.route?.parameters,historicAsset : formAddPhysicalGood.historic_asset_id}),
        {
            preserveScroll: true,
            onSuccess: () => {
                closedPopover()
                formAddPhysicalGood.reset()
            },
            onError: (errors) => {
                notify({
                    title: 'Something went wrong.',
                    text: 'Failed to add physical good, please try again.',
                    type: 'error',
                })
            },
            onFinish: () => {
                isLoadingButton.value = false
            }
        }
    )
}


// Section 'Consolidate Now'
const isLoading = ref(false)

</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <!-- Button: Add service -->
        <template #button-add-service="{ action }">
            <div class="relative">
                <Popover>
                    <template #button="{open}">
                        <Button
                            :style="action.style"
                            :label="action.label"
                            :icon="action.icon"
                            :key="`ActionButton${action.label}${action.style}`"
                            :tooltip="action.tooltip"
                        />
                    </template>
                    <template #content="{ close: closed }">
                        <div class="w-[350px]">
                            <!-- Select Services -->
                            <div>
                                <span class="text-xs px-1 my-2">{{ trans('Services') }}: </span>
                                <div class="">
                                    <PureMultiselectInfiniteScroll
                                        v-model="formAddService.service_id"
                                        :fetchRoute="props.service_list_route"
                                        :placeholder="trans('Select Services')"
                                        valueProp="id"
                                        @optionsList="(options) => dataServiceList = options"
                                    >
                                        <template #singlelabel="{ value }">
                                            <div class="w-full text-left pl-4">{{ value.name }} <span class="text-sm text-gray-400">({{ locale.currencyFormat(value.currency_code, value.price) }}/{{ value.unit }})</span></div>
                                        </template>
                                        <template #option="{ option, isSelected, isPointed }">
                                            <div class="">
                                                <FontAwesomeIcon v-if="option?.is_pallet_handling" v-tooltip="trans('Special service')" icon="fas fa-diamond" class="text-teal-500 text-sm" fixed-width aria-hidden="true" />
                                                {{ option.name }}
                                                <span class="text-sm text-gray-400">({{ locale.currencyFormat(option.currency_code, option.price) }}/{{ option.unit }})</span>
                                            </div>
                                        </template>
                                    </PureMultiselectInfiniteScroll>
                                    <p v-if="get(formAddService, ['errors', 'service_id'])" class="mt-2 text-sm text-red-500">
                                        {{ formAddService.errors.service_id }}
                                    </p>
                                </div>
                            </div>

                            <!-- Pallet -->
                            <div v-if="dataServiceList?.find(list => list.id === formAddService.service_id)?.is_pallet_handling" class="mt-3">
                                <span class="text-xs px-1 my-2">{{ trans('Pallet to attach') }}: </span>
                                <PureMultiselectInfiniteScroll
                                    v-model="formAddService.pallet_id"
                                    :fetchRoute="props.pallet_list_route"
                                    :placeholder="trans('Select pallet')"
                                    required
                                    valueProp="id"
                                    @optionsList="(options) => dataPalletList = options"
                                >
                                    <template #singlelabel="{ value }">
                                        <div class="w-full text-left pl-4">{{ value.reference }} <span class="text-sm text-gray-400">({{ value.type }})</span></div>
                                    </template>

                                    <template #option="{ option, isSelected, isPointed }">
                                        <div class="">
                                            {{ option.reference }}
                                            <span class="text-sm text-gray-400 capitalize">({{ option.type }})</span>
                                        </div>
                                    </template>
                                </PureMultiselectInfiniteScroll>

                                <p v-if="get(formAddService, ['errors', 'pallet_id'])" class="mt-2 text-sm text-red-600">
                                    {{ formAddService.errors.pallet_id }}
                                </p>
                            </div>

                            <!-- Date -->
                            <Popover v-if="dataServiceList?.find(list => list.id === formAddService.service_id)?.is_pallet_handling" position="" style="z-index: 20" class="mt-3 ">
                                <template #button>
                                    <div class="text-left">
                                        <span class="text-xs px-1 my-2">{{ trans('Date') }}: </span>
                                        <div
                                            xxv-tooltip="'useDaysLeftFromToday(dataPalletDelivery.estimated_delivery_date)'"
                                            class="text-left border border-gray-300 py-2 text-sm rounded px-3 cursor-pointer"
                                            :class="formAddService.handle_date ? '' : 'text-gray-400 '"
                                        >
                                            <FontAwesomeIcon icon="fal fa-calendar-alt" class="text-base" fixed-width aria-hidden="true" />
                                            {{ formAddService.handle_date ? useFormatTime(formAddService.handle_date, { formatTime: 'ddmy' }) : trans("Select date") }}
                                        </div>
                                    </div>
                                </template>

                                <template #content="{ close }">
                                    <DatePicker
                                        v-model="formAddService.handle_date"
                                        @update:modelValue="() => close()"
                                        inline
                                        auto-apply
                                        :enable-time-picker="false"
                                    />
                                </template>
                            </Popover>

                            <!-- Quantity -->
                            <div class="mt-3">
                                <span class="text-xs px-1 my-2">{{ trans('Quantity') }}: </span>
                                <PureInput
                                    v-model="formAddService.quantity"
                                    :placeholder="trans('Quantity')"
                                    @keydown.enter="() => onSubmitAddService(action, closed)"
                                />
                                <p v-if="get(formAddService, ['errors', 'quantity'])" class="mt-2 text-sm text-red-600">
                                    {{ formAddService.errors.quantity }}
                                </p>
                            </div>

                            <div class="flex justify-end mt-3">
                                <Button
                                    @click="() => onSubmitAddService(action, closed)"
                                    :style="'save'"
                                    :loading="isLoadingButton == 'addService'"
                                    :disabled="
                                        !formAddService.service_id
                                        || !(formAddService.quantity > 0)
                                        || dataServiceList?.find(list => list.id === formAddService.service_id)?.is_pallet_handling ? (!formAddService.pallet_id || !formAddService.handle_date) : false"
                                    label="Save"
                                    full
                                />
                            </div>

                            <div v-if="isLoadingData === 'addService'" class="bg-white/50 absolute inset-0 flex place-content-center items-center">
                                <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin text-5xl' fixed-width aria-hidden='true' />
                            </div>
                        </div>
                    </template>
                </Popover>
            </div>
        </template>

        <!-- Button: Add physical good -->
        <template #button-add-physical-good="{ action }">
            <div class="relative">
                <Popover>
                    <template #button="{ open }">
                        <Button
                            @click="open ? false : onOpenModalAddPGood()"
                            :key="`ActionButton${action.label}${action.style}`"
                            :style="action.style"
                            :label="action.label"
                            :icon="action.icon"
                            :tooltip="action.tooltip"
                        />
                    </template>
                    <template #content="{ close: closed }">
                        <div class="w-[350px]">
                            <span class="text-xs px-1 my-2">{{ trans('Physical Goods') }}: </span>
                            <div>
                                <PureMultiselectInfiniteScroll
                                    v-model="formAddPhysicalGood.outer_id"
                                    :fetchRoute="physical_good_list_route"
                                    :placeholder="trans('Select Physical Goods')"
                                    valueProp="id"
                                />

                                <p v-if="get(formAddPhysicalGood, ['errors', 'outer_id'])" class="mt-2 text-sm text-red-600">
                                    {{ formAddPhysicalGood.errors.outer_id }}
                                </p>
                            </div>
                            <div class="mt-3">
                                <span class="text-xs px-1 my-2">{{ trans('Quantity') }}: </span>
                                <PureInput
                                    v-model="formAddPhysicalGood.quantity"
                                    placeholder="Quantity"
                                    @keydown.enter="() => onSubmitAddPhysicalGood(action, closed)"
                                />
                                <p v-if="get(formAddPhysicalGood, ['errors', 'quantity'])"
                                    class="mt-2 text-sm text-red-600">
                                    {{ formAddPhysicalGood.errors.quantity }}
                                </p>
                            </div>
                            <div class="flex justify-end mt-3">
                                <Button
                                    :key="'button' + formAddPhysicalGood.outer_id + formAddPhysicalGood.quantity"
                                    :style="'save'"
                                    :loading="isLoadingButton == 'addPGood'"
                                    :disabled="!formAddPhysicalGood.outer_id || !(formAddPhysicalGood.quantity > 0)"
                                    :label="'save'"
                                    full
                                    @click="() => onSubmitAddPhysicalGood(action, closed)"
                                />
                            </div>
                            <div v-if="isLoadingData === 'addPGood'" class="bg-white/50 absolute inset-0 flex place-content-center items-center">
                                <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin text-5xl' fixed-width aria-hidden='true' />
                            </div>
                        </div>
                    </template>
                </Popover>
            </div>
        </template>
    </PageHeading>


    <div class="py-4 px-3">
        <div class="grid sm:grid-cols-2 sm:divide-x divide-gray-500/30 gap-y-6 h-full w-full rounded-md px-4 py-2"
            :class="[status_rb === 'current' ? 'bg-green-100 ring-1 ring-green-500 text-green-700' : 'bg-gray-200 ring-1 ring-gray-500 text-gray-700']"
        >
            <div class="flex flex-col lg:flex-row w-full justify-start lg:justify-between items-start lg:items-center gap-y-2 pr-4">
                <div class="flex flex-col justify-center ">
                    <!-- <div class="text-xs">Status</div> -->
                    <div class="font-semibold capitalize">
                        {{ status_rb === 'current' ? trans('On going') : trans('Expired') }}
                        <FontAwesomeIcon icon='far fa-wave-sine' class='' fixed-width aria-hidden='true' />
                    </div>
                    <div v-if="status_rb === 'current'" class="flex gap-x-1 text-xs italic text-green-700/70">
                        <div>Today is invoice day</div>
                        <div>
                            <Transition name="spin-to-down">
                                <span :key="timeline_rb.end_date">{{ useDaysLeftFromToday(timeline_rb.end_date) }}</span>
                            </Transition>
                        </div>
                    </div>
                </div>

<!--                    v-if="compareAsc(new Date(timeline_rb.end_date), new Date()) === 1 && status_rb === 'current'" class=""-->
                <component
                    v-if="timeline_rb.is_display_consolidate_button && status_rb === 'current'" class=""
                    :is="consolidateRoute?.name ? Link : 'div'"
                    :href="consolidateRoute?.name ? route(consolidateRoute.name, consolidateRoute.parameters) : '#'"
                    @start="() => isLoading = true"
                    @finish="() => isLoading = false"
                    :method="consolidateRoute?.method"
                >
                    <Button label="Consolidate now" :loading="isLoading" />
                </component>
            </div>

            <div class="sm:pl-6 pr-0">
                <StartEndDate
                    :startDate="timeline_rb.start_date"
                    :endDate="timeline_rb.end_date"
                    :updateRoute
                    :isEndDateNotEditable="status_rb === 'former'"
                />
            </div>

        </div>

    </div>

    <BoxStatsRecurringBills :boxStats="box_stats" :currency="currency" />

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab"  :status="status_rb" />
</template>
