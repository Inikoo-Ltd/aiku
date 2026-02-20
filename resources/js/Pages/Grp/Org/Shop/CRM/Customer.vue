 <!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Jun 2023 20:46:53 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { computed, defineAsyncComponent, ref } from "vue"
import type { Component } from "vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import CustomerShowcase from "@/Components/Showcases/Grp/CustomerShowcase.vue"
import TableWebUsers from "@/Components/Tables/Grp/Org/CRM/TableWebUsers.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import ModelDetails from "@/Components/ModelDetails.vue"
import TableOrders from "@/Components/Tables/Grp/Org/Ordering/TableOrders.vue"
import TableDispatchedEmails from "@/Components/Tables/TableDispatchedEmails.vue"
import TableCustomerFavourites from "@/Components/Tables/Grp/Org/CRM/TableCustomerFavourites.vue"
import TableCustomerBackInStockReminders from "@/Components/Tables/Grp/Org/CRM/TableCustomerBackInStockReminders.vue"
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCodeCommit, faUsers, faGlobe, faGraduationCap, faMoneyBill, faPaperclip, faPaperPlane, faStickyNote, faTags, faCube, faCodeBranch, faShoppingCart, faHeart, faQuestionCircle } from "@fal"
import { routeType } from "@/types/route"
import { AddressManagement } from "@/types/PureComponent/Address"
import TableCreditTransactions from "@/Components/Tables/Grp/Org/Accounting/TableCreditTransactions.vue"
import TablePayments from "@/Components/Tables/Grp/Org/Accounting/TablePayments.vue"
import BoxNote from "@/Components/Pallet/BoxNote.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Icon from "@/Components/Icon.vue"
import SelectableCardGrid from "@/Components/Utils/SelectableCardGrid.vue"
import { useForm } from "@inertiajs/vue3"
import LoadingOverlay from "@/Components/Utils/LoadingOverlay.vue"

library.add(faStickyNote, faUsers, faGlobe, faMoneyBill, faGraduationCap, faTags, faCodeCommit, faPaperclip, faPaperPlane, faCube, faCodeBranch, faShoppingCart, faHeart, faQuestionCircle)
const ModelChangelog = defineAsyncComponent(() => import("@/Components/ModelChangelog.vue"))


const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    showcase?: {
        address_management: {
            updateRoute: routeType
            addresses: AddressManagement
            address_update_route: routeType,
            address_modal_title: string
        }
    }
    orders?: {}
    sales_channels: Array<{ id: number, name: string, code: string, type: string, icon: string }>
    can_add_order: boolean
    products?: {}
    dispatched_emails?: {}
    web_users?: {}
    attachments?: {}
    attachmentRoutes?: {}
    favourites?: {}
    reminders?: {}
    history?: {}
    credit_transactions?: {}
    payments?: {}
    notes: {}
    updateRoute: routeType
    shop_data: {
        type: string
    }
}>()

let currentTab = ref(props.tabs.current)
const isModalUploadOpen = ref(false)
const isOrderModalOpen = ref(false)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const orderForm = useForm({
    sales_channel_id: null as number | null
})
const submitOrder = () => {
    const customerId = props.updateRoute.parameters.customer
    orderForm.post(route('grp.models.customer.submitted_order.store', { customer: customerId }), {
        onSuccess: () => {
            isOrderModalOpen.value = false
            orderForm.reset()
        }
    })
}
const component = computed(() => {
    const components: Component = {
        showcase: CustomerShowcase,
        products: TableProducts,
        orders: TableOrders,
        details: ModelDetails,
        history: TableHistories,
        dispatched_emails: TableDispatchedEmails,
        web_users: TableWebUsers,
        favourites: TableCustomerFavourites,
        reminders: TableCustomerBackInStockReminders,
        attachments: TableAttachments,
        credit_transactions: TableCreditTransactions,
        payments: TablePayments,
    }

    return components[currentTab.value]
});


</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button v-if="currentTab === 'attachments'" @click="() => isModalUploadOpen = true" label="Attach"
                icon="upload" />
            <Button v-if="can_add_order" @click="isOrderModalOpen = true" label="Add Order" style="create"
                icon="plus" />
        </template>
    </PageHeading>

    <!-- Section: Box Note -->
    <div v-if="shop_data.type !== 'external'" class="relative">
        <Transition name="headlessui">
            <div xv-if="notes?.note_list?.some(item => !!(item?.note?.trim()))"
                class="p-2 grid grid-cols-2 sm:grid-cols-3 gap-y-2 gap-x-2 h-fit lg:max-h-64 w-full lg:justify-center border-b border-gray-300">
                <BoxNote
                    v-for="(note, index) in notes.note_list"
                    :key="index + note.label"
                    :noteData="note"
                    :updateRoute="updateRoute"
                />
            </div>
        </Transition>
    </div>

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" :handleTabUpdate
        :detachRoute="attachmentRoutes.detachRoute" />

  <UploadAttachment v-model="isModalUploadOpen" scope="attachment" :title="{
        label: 'Upload your file',
        information: 'The list of column file: customer_reference, notes, stored_items'
    }" progressDescription="Adding Pallet Deliveries" :attachmentRoutes="attachmentRoutes" />

    <Modal :show="isOrderModalOpen" @close="isOrderModalOpen = false" width="w-full max-w-5xl">
        <div class="p-6 relative">
            <LoadingOverlay :is-loading="orderForm.processing" position="absolute" />
            <h2 class="text-lg font-medium text-gray-900">{{ capitalize('Select Sales Channel') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ capitalize('Please select a sales channel to create a new order.')}}</p>
            <div class="mt-6">
                <SelectableCardGrid :options="sales_channels" :model-value="orderForm.sales_channel_id"
                    @update:model-value="(val) => { orderForm.sales_channel_id = val; submitOrder() }" />
            </div>
        </div>
    </Modal>
</template>
