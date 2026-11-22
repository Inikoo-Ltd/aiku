<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 15 Sept 2022 16:07:20 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import AlertMessage from "@/Components/Utils/AlertMessage.vue"
import {library} from '@fortawesome/fontawesome-svg-core';
import {
    faInventory,
    faWarehouse,
    faBoxUsd,
    faTerminal,
    faPeopleArrows,
    faClipboard, faTruck, faCameraRetro,
    faPersonDolly,faAddressBook, faInbox, faPaperclip, faWallet,
    faClipboardList, faHandshake, faSitemap
} from '@fal';
import Tabs from "@/Components/Navigation/Tabs.vue";
import {computed, defineAsyncComponent, ref} from "vue";
import {useTabChange} from "@/Composables/tab-change";
import TableOrgSuppliers from "@/Components/Tables/Grp/Org/Procurement/TableOrgSuppliers.vue";
import TableOrgSupplierProducts from "@/Components/Tables/Grp/Org/Procurement/TableOrgSupplierProducts.vue";
import AgentShowcase from "@/Components/Showcases/Grp/AgentShowcase.vue";
import ContactInfoStrip from "@/Components/Showcases/Grp/ContactInfoStrip.vue"
import { type AgentCard, type CoverBucket } from "@/Components/Procurement/AgentWorkCard.vue"
import AgentOrdersOverview from "@/Components/Procurement/AgentOrdersOverview.vue"
import OverviewSection from "@/Components/Utils/OverviewSection.vue"
import { agreementSummary } from "@/Composables/useAgentOverviewSummaries"
import { capitalize } from "@/Composables/capitalize"
import TablePurchaseOrders from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrders.vue";
import {useForm} from "@inertiajs/vue3";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import TableSupplierAgreedTerms from '@/Components/Tables/Grp/Org/Procurement/TableSupplierAgreedTerms.vue'
import TableProcurementNotes from '@/Components/Tables/Grp/Org/Procurement/TableProcurementNotes.vue'
import AgreementSummary from '@/Components/Procurement/AgreementSummary.vue'
import TableSupplierMessages from "@/Components/Tables/Grp/Org/Procurement/TableSupplierMessages.vue";
import OrgAgentMoney from "@/Components/Procurement/OrgAgentMoney.vue";
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    suspended_alert?: { status: string, title: string, description: string } | null,
    pageHead: object,
    tabs: {
        current: string;
        navigation: object;
    },
    showcase?: {
        contactCard: InstanceType<typeof ContactInfoStrip>["$props"]["contactCard"]
        agentCards?: AgentCard[]
    }
    details?: object
    cover?: Record<number, CoverBucket[]>
    cardSections: Record<string, boolean>
    overviewSections: Record<string, boolean>
    org_suppliers?: object
    org_supplier_products?: object,
    purchase_orders?: object,
    errors?: object,
    history?: object
    agreed_terms?: object
    notes?: object
    agreement: object
    inbox?: object
    money?: object
    attachments?: object
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
    faInbox,
    faWallet,
    faPaperclip,
    faClipboardList,
    faHandshake,
    faSitemap
);

let currentTab = ref(props.tabs.current);
const isModalUploadAttachmentOpen = ref(false)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab, tabSlug === "showcase" ? ["cover"] : []);
const sections = ref<Record<string, boolean>>({ ...props.overviewSections })
const cardSections = ref<Record<string, boolean>>({ ...props.cardSections })

const component = computed(() => {

    const components = {
        org_suppliers: TableOrgSuppliers,
        org_supplier_products: TableOrgSupplierProducts,
        purchase_orders: TablePurchaseOrders,
        details: AgentShowcase,
        inbox: TableSupplierMessages,
        money: OrgAgentMoney,
        attachments: TableAttachments,
        agreed_terms: TableSupplierAgreedTerms,
        notes: TableProcurementNotes,
        history: TableHistories
    };
    return components[currentTab.value];

});

const getErrors = () => {
    if (props.errors.purchase_order) {
        if (confirm(props.errors.purchase_order)) {
            let fields = {
                force: true
            };

            const form = useForm(fields);

            form.post(route(
                props.pageHead.create_direct.route.name,
                props.pageHead.create_direct.route.parameters
            ));
        }
    }
}

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button v-if="currentTab === 'attachments'" :label="ctrans('Attach')" icon="upload" @click="() => (isModalUploadAttachmentOpen = true)" />
        </template>
    </PageHeading>
    <div v-if="suspended_alert" class="px-4 pt-4">
        <AlertMessage :alert="suspended_alert" />
    </div>
    <div v-if="props.errors.purchase_order">{{ getErrors() }}</div>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <div v-if="currentTab === 'showcase'" class="space-y-4 px-4 py-4">
        <ContactInfoStrip v-if="showcase?.contactCard" :contactCard="showcase.contactCard" />
        <AgentOrdersOverview v-if="showcase?.agentCards?.length" :cards="showcase.agentCards" :cover="cover" :sections="sections" :cardSections="cardSections" />
        <OverviewSection sectionKey="agent_agreement" :title="ctrans('What was agreed')" :summary="agreementSummary(agreement)" icon="fal fa-handshake" :sections="sections">
            <div class="max-w-4xl"><AgreementSummary :agreement="agreement" /></div>
        </OverviewSection>
    </div>
    <component v-if="component" :is="component" :data="props[currentTab]" :tab="currentTab" :detachRoute="attachmentRoutes.detachRoute" v-bind="['agreed_terms', 'notes'].includes(currentTab) ? { agreement } : {}"></component>

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

