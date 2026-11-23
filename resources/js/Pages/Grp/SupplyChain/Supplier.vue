<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Fri, 24 Feb 2023 10:21:46 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import ProductDevelopmentRelatedList from "@/Components/Goods/ProductDevelopment/ProductDevelopmentRelatedList.vue"
import { Head, router } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
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
    faArchive,
    faBoxOpen,
    faTimes
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

const isArchiveDialogOpen = ref(false)
const isArchiveSaving = ref(false)
const archiveError = ref<string | null>(null)

const openArchiveDialog = () => {
    archiveError.value = null
    isArchiveDialogOpen.value = true
}

const submitArchive = () => {
    router.patch(route(props.archive.route.name, props.archive.route.parameters), {}, {
        preserveScroll: true,
        onStart: () => {
            isArchiveSaving.value = true
            archiveError.value = null
        },
        onSuccess: () => {
            isArchiveDialogOpen.value = false
        },
        onError: (errors) => {
            archiveError.value = Object.values(errors).join(' ') || ctrans('Something went wrong')
        },
        onFinish: () => {
            isArchiveSaving.value = false
        },
    })
}

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
            <Button v-if="archive.can_archive" type="tertiary" :label="archive.is_archived ? ctrans('Unarchive') : ctrans('Archive')" :tooltip="archive.is_archived ? ctrans('Make this supplier active again in the organisations it was archived from') : ctrans('Stop using this supplier in every organisation. Its products stay active')" @click="openArchiveDialog">
                <template #icon>
                    <FontAwesomeIcon :icon="archive.is_archived ? faBoxOpen : faArchive" :class="archive.is_archived ? 'text-green-600' : 'text-amber-500'" fixed-width aria-hidden="true" />
                </template>
            </Button>
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

    <Dialog v-model:visible="isArchiveDialogOpen" modal :header="archive.is_archived ? ctrans('Unarchive supplier') : ctrans('Archive supplier?')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div class="space-y-3 text-sm text-gray-700">
            <p v-if="archive.is_archived">{{ ctrans(':name will be active again in the organisations it was archived from.', { name: archive.supplier_name }) }}</p>
            <template v-else>
                <p>{{ ctrans(':name will be archived in every organisation. Its history is kept.', { name: archive.supplier_name }) }}</p>
                <p class="text-gray-500">{{ ctrans('Its products are not changed and stay active.') }}</p>
            </template>
            <p v-if="archiveError" class="text-xs text-red-600">{{ archiveError }}</p>
        </div>
        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isArchiveDialogOpen = false">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button v-if="archive.is_archived" type="primary" full :label="ctrans('Unarchive')" :loading="isArchiveSaving" @click="submitArchive">
                    <template #icon>
                        <FontAwesomeIcon :icon="faBoxOpen" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button v-else type="red" :icon="faArchive" full :label="ctrans('Yes, archive')" :loading="isArchiveSaving" @click="submitArchive">
                    <!-- <template #icon>
                        <FontAwesomeIcon class="text-amber-500" fixed-width aria-hidden="true" />
                    </template> -->
                </Button>
            </div>
        </template>
    </Dialog>
</template>
