<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 22 Oct 2022 18:57:31 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->


<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faInventory,
    faBox,
    faClock,
    faCameraRetro,
    faPaperclip,
    faCube,
    faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign,
    faExclamationCircle,
    faMoneyCheckEditAlt,
    faChartLine,
    faChevronDown
} from "@fal"
import {
faCloudRainbow,
faShoppingCart,
faShoppingBasket,
faInventory as faInventorySolid,
} from "@fas"
import { computed, defineAsyncComponent, inject, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import ModelDetails from "@/Components/ModelDetails.vue"
import TableOrgStockSupplierProducts from "@/Components/Tables/Grp/Org/Inventory/TableOrgStockSupplierProducts.vue"
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableLocations from "@/Components/Tables/Grp/Org/Inventory/TableLocations.vue"
import StockShowcase from "@/Components/Showcases/Grp/StockShowcase.vue"
import { capitalize } from "@/Composables/capitalize"
import TablePurchaseOrders from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrders.vue"
import TableOrgStockMovements from "@/Components/Tables/Grp/Org/Inventory/TableOrgStockMovements.vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { routeType } from "@/types/route"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import TableTradeUnits from "@/Components/Tables/Grp/Goods/TableTradeUnits.vue"
import { faWarning } from "@fortawesome/free-solid-svg-icons"
import { Message } from "primevue"
import StockIssues from "@/Components/Warehouse/Inventory/StockIssues.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import StocksManagement from "@/Components/Warehouse/Inventory/StocksManagement/StocksManagement.vue"
import { Popover, PopoverButton, PopoverPanel } from "@headlessui/vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"

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
    faCloudRainbow,
    faMoneyCheckEditAlt,
    faChartLine,
    faChevronDown,
    faShoppingCart,
    faShoppingBasket,
    faInventorySolid,
)


const ModelChangelog = defineAsyncComponent(() => import("@/Components/ModelChangelog.vue"))

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes,
    tabs: TSTabs
    showcase?: object
    supplier_products?: object
    locations?: object
    purchase_orders?: {}
    products?: {}
    trade_units?: {}
    stock_history?: {}
    purchase_history?: {}
    master: {}
    masterRoute: routeType | null
    history?: {}
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const locale = inject("locale", aikuLocaleStructure)
const stocksManagement = computed(() => (props.showcase as any)?.stocks_management)
const showHeaderStats = computed(() => !!stocksManagement.value && currentTab.value !== "showcase")

const component = computed(() => {

    const components = {
        showcase: StockShowcase,
        feedbacks: StockIssues,
        locations: TableLocations,
        supplier_products: TableOrgStockSupplierProducts,
        products: TableProducts,
        trade_units: TableTradeUnits,
        stock_history: TableOrgStockMovements,
        purchase_history: TableOrgStockMovements,
        details: ModelDetails,
        history: TableHistories,
        purchase_orders: TablePurchaseOrders,
    }
    return components[currentTab.value]

})

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle2>
            <Link
                v-if="master"
                :href="masterRoute?.name ? route(masterRoute.name, masterRoute.parameters) : ''"
                v-tooltip="trans('Go to Master')"
            >
                <FontAwesomeIcon
                    icon="fas fa-cloud-rainbow"
                    color="#4B0082"
                    fixed-width
                />
            </Link>
        </template>

        <template #otherBefore v-if="showHeaderStats">
            <Popover class="relative" v-slot="{ open }">
                <PopoverButton
                    v-tooltip="trans('Click to view stock details')"
                    class="group flex cursor-pointer items-center justify-between gap-x-3 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm transition hover:border-indigo-400 hover:bg-gray-50 focus:outline-none"
                    :class="open ? 'ring-1 ring-indigo-400' : ''"
                >
                    <template v-for="(item, key) in stocksManagement.summary" :key="key">
                        <span v-tooltip="item.icon_state.tooltip" class="flex items-center gap-x-1 text-gray-500 tabular-nums">
                            <FontAwesomeIcon :icon="item.icon_state.icon" fixed-width aria-hidden="true" />
                            {{ locale.number(item.value ?? 0) }}
                        </span>
                    </template>
                    <span class="flex items-center border-l border-gray-200 pl-3 transition"
                        :class="open ? 'text-indigo-600' : 'text-indigo-500 group-hover:text-indigo-600'"
                    >
                        <FontAwesomeIcon
                            :icon="faChevronDown"
                            fixed-width
                            aria-hidden="true"
                            class="text-xs transition-transform duration-200"
                            :class="open ? 'rotate-180' : ''"
                        />
                    </span>
                </PopoverButton>

                <Transition name="headlessui">
                    <PopoverPanel class="absolute right-0 top-[120%] z-50 w-max max-w-[90vw] max-h-[70vh] overflow-auto rounded-md border border-gray-300 bg-white shadow-lg">
                        <StocksManagement
                            :stocks_management="stocksManagement"
                            :trade_units="showcase.trade_units"
                            :data="{ is_quantity_excess: showcase.is_quantity_excess, currency_code: showcase.currency_code }"
                        />
                    </PopoverPanel>
                </Transition>
            </Popover>
        </template>
    </PageHeading>
    <!-- <div>
        <Message :severity="'warn'">
            <FontAwesomeIcon
                :icon="faExclamationCircle"
                class="text-yellow-500 mr-1"
            />
            {{ trans("Stock location changes for this Org SKU may be overwritten during Aurora imports.") }}
        </Message>
    </div> -->
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>
