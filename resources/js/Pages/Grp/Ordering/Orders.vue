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
    submitRoute: routeType

}>()

const currentTab = ref<string>(props.tabs.current)
const isOrderModalOpen = ref(false)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const orderForm = useForm({
    sales_channel_id: null as number | null
})
const submitOrder = () => {
    const customerId = props.submitRoute.parameters.customer
    orderForm.post(route(props.submitRoute.name, { customer: customerId }), {
        onSuccess: () => {
            isOrderModalOpen.value = false
            orderForm.reset()
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
            <Button v-if="can_add_order" @click="isOrderModalOpen = true" label="Add Order" style="create"
                icon="plus" />
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>
    <Modal :show="isOrderModalOpen" @close="isOrderModalOpen = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">{{ capitalize('Select Sales Channel') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ capitalize('Please select a sales channel to create a new order.')}}</p>

                <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-4 max-h-[60vh] overflow-y-auto p-1">
                    <button v-for="channel in sales_channels" :key="channel.id"
                        @click="orderForm.sales_channel_id = channel.id" type="button"
                        class="relative min-h-[120px] text-left border rounded-md p-6 transition-all duration-200 flex flex-col justify-between group"
                        :class="[
                            orderForm.sales_channel_id === channel.id
                                ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500 z-10'
                                : 'border-gray-300 bg-gray-50 hover:bg-gray-100 hover:border-gray-400'
                        ]">
                        <div class="flex justify-between items-start w-full mb-3">
                            <div class="font-semibold text-gray-900 capitalize text-lg pr-2 leading-tight">
                                {{ channel.name }}
                            </div>
                            <Icon :data="{ icon: channel.icon }" class="text-2xl flex-shrink-0 transition-colors"
                                :class="orderForm.sales_channel_id === channel.id ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-500'" />
                        </div>

                        <div class="mt-auto">
                            <span
                                class="inline-flex items-center px-2 py-1 rounded text-xs font-medium uppercase tracking-wide"
                                :class="orderForm.sales_channel_id === channel.id ? 'bg-indigo-200 text-indigo-800' : 'bg-gray-200 text-gray-600'">
                                {{ channel.type }}
                            </span>
                        </div>
                    </button>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <Button label="Cancel" type="secondary" @click="isOrderModalOpen = false" />
                    <Button label="Create Order" type="primary" @click="submitOrder"
                        :disabled="orderForm.processing || !orderForm.sales_channel_id" />
                </div>
            </div>
        </Modal>
</template>
