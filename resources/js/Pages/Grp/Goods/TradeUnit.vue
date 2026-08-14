<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 22 Oct 2022 18:57:31 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInventory, faArrowRight, faBox, faClock, faCameraRetro, faPaperclip, faCube, faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign, faGripHorizontal, faAtomAlt } from "@fal"
import { computed, defineAsyncComponent, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Breadcrumb from 'primevue/breadcrumb'
import { capitalize } from "@/Composables/capitalize"
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue"
import TradeUnitShowcase from "@/Components/Goods/TradeUnitShowcase.vue"
import TradeUnitComposition from "@/Components/Goods/TradeUnitComposition.vue"
import { routeType } from "@/types/route"
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import TableStocks from "@/Components/Tables/Grp/Goods/TableStocks.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import type { Navigation } from "@/types/Tabs"
import { Images } from "@/types/Images"
import TradeUnitImagesManagement from "@/Components/Goods/ImagesManagement.vue"
import AttachmentManagement from "@/Components/Goods/AttachmentManagement.vue"
import TableMasterProducts from "@/Components/Tables/Grp/Goods/TableMasterProducts.vue"
import TableOrgStocks from "@/Components/Tables/Grp/Org/Inventory/TableOrgStocks.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faInventory, faArrowRight, faBox, faClock, faCameraRetro, faPaperclip, faCube, faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign, faGripHorizontal, faAtomAlt)

const isModalUploadOpen = ref(false)
const ModelChangelog = defineAsyncComponent(() => import("@/Components/ModelChangelog.vue"))

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: {
        current: string;
        navigation: Navigation
    }
    showcase?: object,
    composition?: object,
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
    org_stocks?: {}
    images?: {}
    master_products?: {}
    images_category_box?: {
        label: string
        type: string
        column_in_db: string
        url?: string
        images?: Images
    }[]
    images_update_route: routeType
    id: number | string
    tradeUnitFamilySlug?: string
    mini_breadcrumbs? : any[]
}>()


const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components = {
        showcase: TradeUnitShowcase,
        composition: TradeUnitComposition,
        history: ModelChangelog,
        attachments: AttachmentManagement,
        master_products: TableMasterProducts,
        products: TableProducts,
        stocks: TableStocks,
        org_stocks: TableOrgStocks,
        images: TradeUnitImagesManagement
    }
    return components[currentTab.value]

})

const visitTradeUnitFamily = () => {
    router.visit(route('grp.trade_units.families.show', {
        tradeUnitFamily: props.tradeUnitFamilySlug
    }));
}

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <!-- <template #other>
            <Button v-if="currentTab === 'attachments'" @click="() => isModalUploadOpen = true" label="Attach"
                icon="upload" />
        </template> -->
        <template #afterTitle>
            <FontAwesomeIcon 
                v-if="tradeUnitFamilySlug"
                @click="visitTradeUnitFamily"
                :icon="faAtomAlt"
                class="cursor-pointer hover:text-black transition ease-in-out"
            />
            <span class="font-normal text-lg leading-none">
                {{ props.pageHead.afterTitle?.label }}
            </span>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <div v-if="mini_breadcrumbs.length != 0" class="bg-white  px-4 py-2  w-full  border-gray-200 border-b overflow-x-auto">
        <Breadcrumb :model="mini_breadcrumbs">
            <template #item="{ item, index }">
                <div class="flex items-center gap-1 whitespace-nowrap">
                    <component :is="item.to ? Link : 'span'" v-bind="item.to ? { href: route(item.to.name, item.to.parameters) } : {}" v-tooltip="item.tooltip"
                        :title="item.label" class="flex items-center gap-2 text-sm transition-colors duration-150"
                        :class="item.to
                            ? 'text-gray-500'
                            : 'text-gray-500 cursor-default'">
                        <FontAwesomeIcon :icon="item.icon" class="w-4 h-4" />
                        <span>{{ item.label || '-' }}</span> <span v-if="item.post_label" class="text-gray-400">{{ item.post_label }}</span>
                    </component>
                </div>
            </template>
        </Breadcrumb>
    </div>
    <component :is="component" :data="props[currentTab]" :tab="currentTab" :tag_routes :handleTabUpdate="handleTabUpdate"/>

    <!-- <UploadAttachment v-model="isModalUploadOpen" scope="attachment" :title="{
        label: 'Upload your file',
        information: 'The list of column file: customer_reference, notes, stored_items'
    }" progressDescription="Adding Pallet Deliveries" :attachmentRoutes="attachmentRoutes" /> -->
</template>
<style scoped>
/* Remove default breadcrumb styles */
:deep(.p-breadcrumb) {
    padding: 0;
    margin: 0;
    background: transparent;
    border: none;
}

:deep(.p-breadcrumb-list > li.p-breadcrumb-separator:first-child) {
    display: none !important;
}
</style>
