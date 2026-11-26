<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Fri, 24 Feb 2023 10:21:46 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faBoxUsd,
    faCameraRetro,
    faClipboard,
    faClock,
    faInbox,
    faMoneyBill,
    faPaperclip,
    faPaperPlane,
    faPersonDolly,
    faPoop,
    faTruckContainer
} from '@fal'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import SupplierShowcase from '@/Components/Showcases/Grp/SupplierShowcase.vue'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
import TableSupplierMessages from '@/Components/Tables/Grp/Org/Procurement/TableSupplierMessages.vue'
import SupplierDeclarations from '@/Components/SupplyChain/SupplierDeclarations.vue'
import TableSupplierAgreedTerms from '@/Components/Tables/Grp/Org/Procurement/TableSupplierAgreedTerms.vue'
import TableProcurementNotes from '@/Components/Tables/Grp/Org/Procurement/TableProcurementNotes.vue'
import AgreementSummary from '@/Components/Procurement/AgreementSummary.vue'
import ProductDevelopmentRelatedList from '@/Components/Goods/ProductDevelopment/ProductDevelopmentRelatedList.vue'
import AgentAgreementsByOrganisation from '@/Components/Procurement/AgentAgreementsByOrganisation.vue'
import SupplierAgreementStatusBadge from '@/Components/Procurement/SupplierAgreementStatusBadge.vue'
import PurchaseOrderForOrganisationMenu from '@/Components/Procurement/PurchaseOrderForOrganisationMenu.vue'
import SupplierArchiveButton from '@/Components/SupplyChain/SupplierArchiveButton.vue'
import SupplierMergedInto from '@/Components/SupplyChain/SupplierMergedInto.vue'
import SupplierArchivedBadge from '@/Components/SupplyChain/SupplierArchivedBadge.vue'
import Action from '@/Components/Forms/Fields/Action.vue'
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { useTabChange } from '@/Composables/tab-change'
import { capitalize } from '@/Composables/capitalize'

library.add(
    faBoxUsd,
    faCameraRetro,
    faClipboard,
    faClock,
    faInbox,
    faMoneyBill,
    faPaperclip,
    faPaperPlane,
    faPersonDolly,
    faPoop,
    faTruckContainer
)

const props = defineProps<{
    title: string
    pageHead: object
    tabs: {
        current: string
        navigation: object
    }
    showcase?: object
    inbox?: object
    declarations?: object
    agreed_terms?: object
    notes?: object
    agreement: object
    history?: object
    errors?: object
    attachments?: object
    attachmentRoutes: { attachRoute: routeType; detachRoute: routeType }
    attachmentScopes: { name: string; code: string }[]
    archive?: InstanceType<typeof SupplierArchiveButton>["$props"]["archive"] | null
    archived?: { is_archived: boolean; archived_at: string | null; merged_into?: InstanceType<typeof SupplierMergedInto>["$props"]["mergedInto"] | null }
    productDevelopments?: InstanceType<typeof ProductDevelopmentRelatedList>["$props"]["data"] | null
}>()

const currentTab = ref(props.tabs.current)
const isModalUploadAttachmentOpen = ref(false)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components = {
        showcase: SupplierShowcase,
        inbox: TableSupplierMessages,
        declarations: SupplierDeclarations,
        agreed_terms: TableSupplierAgreedTerms,
        notes: TableProcurementNotes,
        attachments: TableAttachments,
        history: TableHistories
    }

    return components[currentTab.value]
})

const getErrors = () => {
    if (props.errors.purchase_order) {
        if (confirm(props.errors.purchase_order)) {
            const form = useForm({ force: true })

            form.post(route(
                props.pageHead.create_direct.route.name,
                props.pageHead.create_direct.route.parameters
            ))
        }
    }
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle2>
            <SupplierAgreementStatusBadge v-if="agreement?.supplier_agreement?.status" :status="agreement.supplier_agreement" class="ml-2" />
            <SupplierArchivedBadge v-if="archived?.is_archived" :archivedAt="archived.archived_at" class="ml-2" />
            <SupplierMergedInto v-if="archived?.merged_into" :mergedInto="archived.merged_into" class="ml-2" />
        </template>
        <template #button-group-purchase-order-organisation="{ action }">
            <PurchaseOrderForOrganisationMenu :action="action" />
        </template>
        <template #button-edit="{ action }">
            <Action :action="action" />
            <SupplierArchiveButton v-if="archive" :archive="archive" />
        </template>
        <template #other>
            <Button v-if="currentTab === 'attachments'" :label="ctrans('Attach')" icon="upload" @click="() => (isModalUploadAttachmentOpen = true)" />
        </template>
    </PageHeading>
    <div v-if="props.errors.purchase_order">{{ getErrors() }}</div>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <div v-if="currentTab === 'showcase'" class="grid items-start gap-4 px-4 pt-4 md:px-6 lg:px-8" :class="productDevelopments ? 'xl:grid-cols-[minmax(0,56rem)_minmax(0,1fr)]' : 'max-w-4xl'">
        <AgreementSummary :agreement="agreement" />
        <section v-if="productDevelopments" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <h3 class="border-b border-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-800">{{ ctrans("Open product developments") }}</h3>
            <ProductDevelopmentRelatedList :data="productDevelopments" compact :emptyText="ctrans('No open product development')" />
        </section>
    </div>
    <AgentAgreementsByOrganisation v-if="currentTab === 'agreed_terms' && agreement?.by_organisation" :rows="agreement.by_organisation" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" :detachRoute="attachmentRoutes.detachRoute" v-bind="['agreed_terms', 'notes'].includes(currentTab) ? { agreement } : {}" />

    <UploadAttachment
        v-model="isModalUploadAttachmentOpen"
        scope="attachment"
        :title="{ label: ctrans('Upload your file'), information: '' }"
        :progressDescription="ctrans('Adding supplier attachments')"
        :attachmentRoutes="attachmentRoutes"
        :options="attachmentScopes"
        withCaption
    />
</template>
