<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 22 Oct 2022 18:57:31 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->



<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import {useLocaleStore} from '@/Stores/locale';
import {library} from '@fortawesome/fontawesome-svg-core';
import { faInventory, faBox, faClock, faCameraRetro, faPaperclip, faCube, faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign } from '@fal';
import { computed, defineAsyncComponent, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { capitalize } from "@/Composables/capitalize"
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue";
import Button from '@/Components/Elements/Buttons/Button.vue';
import UploadAttachment from '@/Components/Upload/UploadAttachment.vue';
import TradeUnitShowcase from '@/Components/Goods/TradeUnitShowcase.vue';
import { routeType } from '@/types/route'
import TableProducts from '@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue';
import TableStocks from '@/Components/Tables/Grp/Goods/TableStocks.vue';

library.add( faInventory, faBox, faClock, faCameraRetro, faPaperclip, faCube, faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign, );

const locale = useLocaleStore();
const isModalUploadOpen = ref(false)
const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string;
        navigation: object;
    }
    showcase?: object,
    attachments?: {}
    attachmentRoutes?: {}
    tag_routes: {
        store_tag: routeType
        update_tag: routeType
        destroy_tag: routeType
        attach_tag: routeType
        detach_tag: routeType
    }
    products?: {}
    stocks?: {}

}>()

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        showcase: TradeUnitShowcase,
        history: ModelChangelog,
        attachments: TableAttachments,
        products: TableProducts,
        stocks: TableStocks
    };
    return components[currentTab.value];

});

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button v-if="currentTab === 'attachments'" @click="() => isModalUploadOpen = true" label="Attach" icon="upload"/>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <component
        :is="component"
        :data="props[currentTab]"
        :tab="currentTab"
        :tag_routes
        :detachRoute="attachmentRoutes.detachRoute">
    </component>

    <UploadAttachment v-model="isModalUploadOpen" scope="attachment" :title="{
        label: 'Upload your file',
        information: 'The list of column file: customer_reference, notes, stored_items'
    }" progressDescription="Adding Pallet Deliveries" :attachmentRoutes="attachmentRoutes" />
</template>
