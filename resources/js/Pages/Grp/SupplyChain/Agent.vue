<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 04 May 2024 13:04:40 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import ProductDevelopmentRelatedList from "@/Components/Goods/ProductDevelopment/ProductDevelopmentRelatedList.vue"
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import AlertMessage from "@/Components/Utils/AlertMessage.vue"
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faInventory,
    faWarehouse,
    faBoxUsd,
    faTerminal,
    faPeopleArrows,
    faClipboard, faTruck, faCameraRetro,
    faPersonDolly, faAddressBook, faPaperclip
} from '@fal'
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from "vue"
import ModelDetails from "@/Components/ModelDetails.vue"
import { useTabChange } from "@/Composables/tab-change"
import TableSuppliers from "@/Components/Tables/Grp/SupplyChain/TableSuppliers.vue"
import TableSupplierProducts from "@/Components/Tables/Grp/SupplyChain/TableSupplierProducts.vue"
import AgentShowcase from "@/Components/Showcases/Grp/AgentShowcase.vue"
import { capitalize } from "@/Composables/capitalize"
import TablePurchaseOrders from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrders.vue"
import { useForm } from "@inertiajs/vue3"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import TableSupplierAgreedTerms from '@/Components/Tables/Grp/Org/Procurement/TableSupplierAgreedTerms.vue'
import TableProcurementNotes from '@/Components/Tables/Grp/Org/Procurement/TableProcurementNotes.vue'
import AgreementSummary from '@/Components/Procurement/AgreementSummary.vue'
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import AgentSystemUsers from "@/Components/Procurement/AgentSystemUsers.vue"
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

const props = defineProps<{
    productDevelopments?: InstanceType<typeof ProductDevelopmentRelatedList>["$props"]["data"]
    title: string,
    suspended_alert?: { status: string, title: string, description: string } | null,
    pageHead: object,
    tabs: {
        current: string
        navigation: object
    },
    showcase?: object
    suppliers?: object
    supplier_products?: object,
    purchase_orders?: object,
    errors?: object,
    history?: object
    agreed_terms?: object
    notes?: object
    agreement: object
    attachments?: object
    system_users?: object
    systemUsersManagement?: object | null
    attachmentRoutes: { attachRoute: routeType; detachRoute: routeType }
    attachmentScopes: { name: string; code: string }[]
}>()


library.add(
    faInventory,
    faWarehouse,
    faPersonDolly,
    faBoxUsd,
    faTruck,
    faTerminal,
    faCameraRetro,
    faClipboard,
    faPeopleArrows,
    faAddressBook,
    faPaperclip
)

let currentTab = ref(props.tabs.current)
const isModalUploadAttachmentOpen = ref(false)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components = {
        showcase: AgentShowcase,
        suppliers: TableSuppliers,
        supplier_products: TableSupplierProducts,
        purchase_orders: TablePurchaseOrders,
        details: ModelDetails,
        attachments: TableAttachments,
        system_users: AgentSystemUsers,
        agreed_terms: TableSupplierAgreedTerms,
        notes: TableProcurementNotes,
        history: TableHistories
    }
    return components[currentTab.value]

})

const getErrors = () => {
    if (props.errors.purchase_order) {
        if (confirm(props.errors.purchase_order)) {
            let fields = {
                force: true
            }

            const form = useForm(fields)

            form.post(route(
                props.pageHead.create_direct.route.name,
                props.pageHead.create_direct.route.parameters
            ))
        }
    }
}

</script>

<template>
    <!--suppress HtmlRequiredTitleElement -->

    <Head :title="capitalize(title)" />
    <!-- {{ typeof props.errors.purchase_orders }} -->
    <PageHeading :data="pageHead">
        <template #other>
            <Button v-if="currentTab === 'attachments'" :label="ctrans('Attach')" icon="upload" @click="() => (isModalUploadAttachmentOpen = true)" />
        </template>
    </PageHeading>
    <div v-if="suspended_alert" class="px-4 pt-4">
        <AlertMessage :alert="suspended_alert" />
    </div>
    <!--suppress TypeScriptUnresolvedReference -->
    <div v-if="props.errors.purchase_order">{{ getErrors() }}</div>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <div v-if="currentTab === 'showcase'" class="grid items-start gap-4 px-4 pt-4" :class="productDevelopments ? 'xl:grid-cols-[minmax(0,56rem)_minmax(0,1fr)]' : 'max-w-4xl'">
        <AgreementSummary :agreement="agreement" />
        <section v-if="productDevelopments" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <h3 class="border-b border-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-800">{{ ctrans("Open product developments") }}</h3>
            <ProductDevelopmentRelatedList :data="productDevelopments" compact :emptyText="ctrans('No open product development')" />
        </section>
    </div>
    <component :is="component" :data="props[currentTab]" :tab="currentTab" :detachRoute="attachmentRoutes.detachRoute" v-bind="['agreed_terms', 'notes'].includes(currentTab) ? { agreement } : currentTab === 'system_users' ? { management: systemUsersManagement } : {}"></component>

    <UploadAttachment
        v-model="isModalUploadAttachmentOpen"
        scope="attachment"
        :title="{ label: ctrans('Upload your file'), information: '' }"
        :progressDescription="ctrans('Adding agent attachments')"
        :attachmentRoutes="attachmentRoutes"
        :options="attachmentScopes"
        withCaption
    />
</template>
