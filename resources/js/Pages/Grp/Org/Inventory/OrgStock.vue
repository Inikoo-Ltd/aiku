<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 22 Oct 2022 18:57:31 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->



<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { useLocaleStore } from '@/Stores/locale'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faInventory,
    faBox,
    faClock,
    faCameraRetro,
    faPaperclip,
    faCube,
    faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign
} from '@fal'
import { computed, defineAsyncComponent, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import ModelDetails from "@/Components/ModelDetails.vue"
import TableSupplierProducts from "@/Components/Tables/Grp/SupplyChain/TableSupplierProducts.vue"
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableLocations from "@/Components/Tables/Grp/Org/Inventory/TableLocations.vue"
import StockShowcase from "@/Components/Showcases/Grp/StockShowcase.vue"
import { capitalize } from "@/Composables/capitalize"
import TablePurchaseOrders from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrders.vue"
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { routeType } from '@/types/route'
library.add(
    faInventory,
    faBox,
    faClock,
    faCameraRetro,
    faPaperclip,
    faCube,
    faHandReceiving,
    faClipboard,
    faPoop,
    faScanner,
    faDollarSign,

)

const locale = useLocaleStore()

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string
        navigation: object
    }
    showcase: object
    supplier_products: object
    locations: object
    purchase_orders: {}
    product?: {}
    master: {}
    masterRoute: routeType | null
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components = {
        showcase: StockShowcase,
        locations: TableLocations,
        supplier_products: TableSupplierProducts,
        product: TableProducts,
        details: ModelDetails,
        history: ModelChangelog,
        purchase_orders: TablePurchaseOrders,
    }
    return components[currentTab.value]

})

</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle>
            <Link
                v-if="master"
                :href="masterRoute?.name ? route(masterRoute.name, masterRoute.parameters) : ''"
                v-tooltip="trans('Go to Master')"
            >
                <FontAwesomeIcon
                    icon="fab fa-octopus-deploy"
                    color="#4B0082"
                    fixed-width
                />
            </Link>

            <!-- <Link v-if="is_single_trade_unit && trade_unit_slug" :href="route('grp.trade_units.units.show', [trade_unit_slug])" v-tooltip="trans('Go to Trade Unit')">
                <FontAwesomeIcon
                    icon="fal fa-atom"
                />
            </Link> -->
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>
