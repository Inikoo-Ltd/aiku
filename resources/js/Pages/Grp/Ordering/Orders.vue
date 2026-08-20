<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 12:20:38 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableOrders from "@/Components/Tables/Grp/Org/Ordering/TableOrders.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import OrdersStats from "@/Components/Dropshipping/Orders/OrdersStats.vue"
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTags, faTasksAlt, faChartPie, faFluxCapacitor, faSyncAlt, faArrowFromBottom, faQuestionCircle } from "@fal"
import TableInvoices from "@/Components/Tables/Grp/Org/Accounting/TableInvoices.vue"
import TableDeliveryNotes from "@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue"
import TableLastOrders from "@/Components/Tables/Grp/Org/Ordering/TableLastOrders.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Select from '@/Components/Forms/Fields/Select.vue'
import { useForm } from "@inertiajs/vue3"
import { routeType } from "@/types/route"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Icon from "@/Components/Icon.vue"
import SelectableCardGrid from "@/Components/Utils/SelectableCardGrid.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import LoadingOverlay from "@/Components/Utils/LoadingOverlay.vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"


library.add(faTags, faTasksAlt, faChartPie, faFluxCapacitor, faSyncAlt, faArrowFromBottom, faQuestionCircle)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    sales_channels: Array<{ id: number, name: string, code: string, type: string, icon: icon }>
    can_add_order: boolean
    tabs: {
        current: string
        navigation: {}
    },
    backlog?: {}
    orders?: {}
    excess_orders?: {}
    orders_with_replacements?: {}
    invoices?: {}
    delivery_notes?: {}
    mailshots?: {}
    stats?: {}
    history?: {}
    last_orders?: {
        icon: Icon
        label: string
        date_key: string
    }[]
    submitRoute?: routeType
    customersRoute?: routeType
    customerName?: string

}>()

const currentTab = ref<string>(props.tabs.current)
const isOrderModalOpen = ref(false)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const selectedCustomerId = ref<number | null>(props.submitRoute?.parameters?.customer ?? null)
const selectedCustomerName = ref<string | null>(props.customerName ?? null)
const orderForm = useForm({
    sales_channel_id: null as number | null
})
const canSubmitOrder = computed(() => !!selectedCustomerId.value && !!orderForm.sales_channel_id)
const createOrderLabel = computed(() => selectedCustomerName.value
    ? trans('Create Order for :customer', { customer: selectedCustomerName.value })
    : trans('Create Order'))
const closeOrderModal = () => {
    isOrderModalOpen.value = false
    orderForm.reset()
    if (props.customersRoute) {
        selectedCustomerId.value = null
        selectedCustomerName.value = null
    }
}
const submitOrder = () => {
    const customerId = selectedCustomerId.value
    if (!props.submitRoute || !customerId || !canSubmitOrder.value) {
        return
    }

    orderForm.post(route(props.submitRoute.name, { customer: customerId }), {
        onSuccess: () => {
            closeOrderModal()
        }
    })
}
const component = computed(() => {
    const components: any = {
        orders: TableOrders,
        stats: OrdersStats,
        excess_orders: TableOrders,
        orders_with_replacements: TableOrders,
        last_orders: TableLastOrders,
        invoices: TableInvoices,
        delivery_notes: TableDeliveryNotes,
        history: TableHistories
    }

    return components[currentTab.value]
})


</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button v-if="can_add_order" @click="isOrderModalOpen = true" :label="trans('Add Order')" style="create"
                icon="plus" />
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>
    <Modal :show="isOrderModalOpen" @close="closeOrderModal">
        <h2 class="text-2xl font-bold text-center text-gray-900">{{ ctrans('Create Order') }}</h2>
        <div class="p-6 relative">
            <LoadingOverlay :is-loading="orderForm.processing" position="absolute" />

            <template v-if="customersRoute">
                <h2 class="text-lg font-medium text-gray-900">{{ trans('Select Customer') }} <span class="text-red-500">*</span></h2>
                <p class="mt-1 text-sm text-gray-600">{{ trans('Select the customer this order is created for.') }}</p>
                <div class="mt-4">
                    <PureMultiselectInfiniteScroll v-model="selectedCustomerId" :fetchRoute="customersRoute"
                        valueProp="id" labelProp="name" labelAdditionalProp="reference"
                        :placeholder="trans('Select customer')"
                        @selectedObject="(customer: any) => selectedCustomerName = customer?.name ?? null">
                        <template #singlelabel="{ value }">
                            <div class="w-full text-left pl-4 leading-4 truncate mr-2">
                                {{ value.name }}
                                <span class="text-sm text-gray-400">
                                    ({{ value.reference }}<template v-if="value.email"> · {{ value.email }}</template>)
                                </span>
                            </div>
                        </template>

                        <template #option="{ option }">
                            <div>
                                {{ option.name }}
                                <span class="text-sm text-gray-400">
                                    ({{ option.reference }}<template v-if="option.email"> · {{ option.email }}</template>)
                                </span>
                            </div>
                        </template>
                    </PureMultiselectInfiniteScroll>
                </div>
            </template>

            <h2 class="text-lg font-medium text-gray-900" :class="customersRoute ? 'mt-6' : ''">{{ capitalize('Select Sales Channel') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ capitalize('Please select which sales channel the Order is from')}}</p>
            <div class="mt-6" :class="selectedCustomerId ? '' : 'opacity-50 pointer-events-none'">
                <SelectableCardGrid :options="sales_channels" :model-value="orderForm.sales_channel_id"
                    @update:model-value="(val) => orderForm.sales_channel_id = val" />
            </div>

            <div class="mt-6 flex justify-end">
                <Button :label="createOrderLabel" full icon="plus" size="lg" :disabled="!canSubmitOrder"
                    :loading="orderForm.processing" @click="submitOrder()" />
            </div>
        </div>
        </Modal>
</template>
