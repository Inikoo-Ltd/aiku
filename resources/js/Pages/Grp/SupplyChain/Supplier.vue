<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Fri, 24 Feb 2023 10:21:46 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import ProductDevelopmentRelatedList from "@/Components/Goods/ProductDevelopment/ProductDevelopmentRelatedList.vue"
import { Head } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useFormatTime } from '@/Composables/useFormatTime'
import { computed, ref } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faBoxUsd,
    faClipboardList,
    faClock,
    faInbox,
    faPaperclip,
    faPersonDolly,
    faTruckContainer,
    faFileSignature,
    faArchive
} from '@fal'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import SupplierShowcase from '@/Components/Showcases/Grp/SupplierShowcase.vue'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import TableSupplierMessages from '@/Components/Tables/Grp/Org/Procurement/TableSupplierMessages.vue'
import SupplierDeclarations from '@/Components/SupplyChain/SupplierDeclarations.vue'
import SupplierArchiveButton from '@/Components/SupplyChain/SupplierArchiveButton.vue'
import TableSupplierAgreedTerms from '@/Components/Tables/Grp/Org/Procurement/TableSupplierAgreedTerms.vue'
import TableProcurementNotes from '@/Components/Tables/Grp/Org/Procurement/TableProcurementNotes.vue'
import AgreementSummary from '@/Components/Procurement/AgreementSummary.vue'
import SupplierAgreementStatusBadge from '@/Components/Procurement/SupplierAgreementStatusBadge.vue'
import { useTabChange } from '@/Composables/tab-change'
import { capitalize } from '@/Composables/capitalize'

library.add(faBoxUsd, faClipboardList, faClock, faInbox, faPaperclip, faPersonDolly, faTruckContainer, faFileSignature)

const props = defineProps<{
    productDevelopments?: InstanceType<typeof ProductDevelopmentRelatedList>["$props"]["data"]
    title: string
    pageHead: object
    tabs: {
        current: string
        navigation: object
    }
    showcase?: InstanceType<typeof SupplierShowcase>["$props"]["data"]
    inbox?: object
    declarations?: object
    agreed_terms?: object
    notes?: object
    agreement: object
    history?: object
    attachments?: object
    attachmentRoutes: { attachRoute: routeType; detachRoute: routeType }
    attachmentScopes: { name: string; code: string }[]
    archive: {
        is_archived: boolean
        supplier_name: string
        archived_at: string | null
        can_archive: boolean
        route: routeType
    }
}>()

const currentTab = ref(props.tabs.current)
const isModalUploadAttachmentOpen = ref(false)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components = {
        inbox: TableSupplierMessages,
        declarations: SupplierDeclarations,
        agreed_terms: TableSupplierAgreedTerms,
        notes: TableProcurementNotes,
        attachments: TableAttachments,
        history: TableHistories
    }

    return components[currentTab.value]
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle2>
            <SupplierAgreementStatusBadge v-if="agreement?.supplier_agreement?.status" :status="agreement.supplier_agreement" class="ml-2" />
            <span v-if="archive.is_archived" class="ml-2 inline-flex items-center gap-1 whitespace-nowrap rounded border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">
                <FontAwesomeIcon :icon="faArchive" fixed-width aria-hidden="true" />
                {{ archive.archived_at ? ctrans('Archived :date', { date: useFormatTime(archive.archived_at) }) : ctrans('Archived') }}
            </span>
        </template>
        <template #other>
            <Button v-if="currentTab === 'attachments'" :label="ctrans('Attach')" icon="upload" @click="() => (isModalUploadAttachmentOpen = true)" />
            <SupplierArchiveButton :archive="archive" />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <template v-if="currentTab === 'showcase'">
        <div class="grid items-start gap-4 px-4 pt-4 md:px-6 lg:px-8" :class="productDevelopments ? 'xl:grid-cols-[minmax(0,56rem)_minmax(0,1fr)]' : 'max-w-4xl'">
            <AgreementSummary :agreement="agreement" />
            <section v-if="productDevelopments" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <h3 class="border-b border-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-800">{{ ctrans("Open product developments") }}</h3>
                <ProductDevelopmentRelatedList :data="productDevelopments" compact :emptyText="ctrans('No open product development')" />
            </section>
        </div>
        <SupplierShowcase v-if="showcase" :data="showcase" />
    </template>
    <component v-if="component" :is="component" :data="props[currentTab]" :tab="currentTab" :detachRoute="attachmentRoutes.detachRoute" v-bind="['agreed_terms', 'notes'].includes(currentTab) ? { agreement } : {}" />

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
