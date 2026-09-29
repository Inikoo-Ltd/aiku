<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 15 Sept 2022 16:07:20 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import {library} from '@fortawesome/fontawesome-svg-core';
import {
    faInventory,
    faWarehouse,
    faBoxUsd,
    faTerminal,
    faPeopleArrows,
    faClipboard, faTruck, faCameraRetro,
    faPersonDolly,faAddressBook, faInbox, faPaperclip
} from '@fal';
import Tabs from "@/Components/Navigation/Tabs.vue";
import {computed, defineAsyncComponent, ref} from "vue";
import ModelDetails from "@/Components/ModelDetails.vue";
import {useTabChange} from "@/Composables/tab-change";
import TableOrgSuppliers from "@/Components/Tables/Grp/Org/Procurement/TableOrgSuppliers.vue";
import TableOrgSupplierProducts from "@/Components/Tables/Grp/Org/Procurement/TableOrgSupplierProducts.vue";
import AgentShowcase from "@/Components/Showcases/Grp/AgentShowcase.vue";
import { capitalize } from "@/Composables/capitalize"
import TablePurchaseOrders from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrders.vue";
import {useForm} from "@inertiajs/vue3";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import TableSupplierMessages from "@/Components/Tables/Grp/Org/Procurement/TableSupplierMessages.vue";
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string;
        navigation: object;
    },
    showcase?: object
    org_suppliers?: object
    org_supplier_products?: object,
    purchase_orders?: object,
    errors?: object,
    history?: object
    inbox?: object
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
    faPaperclip
);

let currentTab = ref(props.tabs.current);
const isModalUploadAttachmentOpen = ref(false)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        showcase: AgentShowcase,
        org_suppliers: TableOrgSuppliers,
        org_supplier_products: TableOrgSupplierProducts,
        purchase_orders: TablePurchaseOrders,
        details: ModelDetails,
        inbox: TableSupplierMessages,
        attachments: TableAttachments,
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
    <div v-if="props.errors.purchase_order">{{ getErrors() }}</div>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <component :is="component" :data="props[currentTab]" :tab="currentTab" :detachRoute="attachmentRoutes.detachRoute"></component>

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

