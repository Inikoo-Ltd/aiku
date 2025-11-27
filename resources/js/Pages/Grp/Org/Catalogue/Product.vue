<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faBox, faBullhorn, faCameraRetro, faCube, faFolder,
    faMoneyBillWave, faProjectDiagram, faRoad, faShoppingCart,
    faStream, faUsers, faHeart, faMinus,
    faFolderTree, faBrowser, faLanguage,faFolders, faPaperclip,
    faFolderDownload,faQuoteLeft,
    faExternalLink
} from '@fal'
import { ref, computed } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import { capitalize } from "@/Composables/capitalize"
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import Breadcrumb from 'primevue/breadcrumb'
import Message from 'primevue/message'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import type { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import ModelDetails from "@/Components/ModelDetails.vue"
import TableOrders from "@/Components/Tables/Grp/Org/Ordering/TableOrders.vue"
import TableMailshots from "@/Components/Tables/TableMailshots.vue"
import TableCustomers from "@/Components/Tables/Grp/Org/CRM/TableCustomers.vue"
import ProductShowcase from "@/Components/Showcases/Grp/ProductShowcase.vue"
import ProductService from "@/Components/Showcases/Grp/ProductService.vue"
import ProductRental from "@/Components/Showcases/Grp/ProductRental.vue"
import TableProductFavourites from "@/Components/Tables/Grp/Org/Catalogue/TableProductFavourites.vue"
import TableProductBackInStockReminders from "@/Components/Tables/Grp/Org/Catalogue/TableProductBackInStockReminders.vue"
import TableTradeUnits from '@/Components/Tables/Grp/Goods/TableTradeUnits.vue'
import TableOrgStocks from '@/Components/Tables/Grp/Org/Inventory/TableOrgStocks.vue'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
import ProductTranslation from '@/Components/Showcases/Grp/ProductTranslation.vue'
import { routeType } from '@/types/route'
import TradeUnitImagesManagement from "@/Components/Goods/ImagesManagement.vue"
import AttachmentManagement from '@/Components/Goods/AttachmentManagement.vue'
import ProductSales from "@/Components/Product/ProductSales.vue";
import { trans } from "laravel-vue-i18n"
import ProductContent from '@/Components/Showcases/Grp/ProductContent.vue'


library.add(
    faFolder,
    faFolders,
    faCube,
    faStream,
    faMoneyBillWave,
    faShoppingCart,
    faUsers,
    faBullhorn,
    faProjectDiagram,
    faBox,
    faCameraRetro,
    faRoad,
    faHeart,
    faMinus,
    faBrowser,
    faLanguage,
    faPaperclip,
    faFolderTree,
    faFolderDownload,
    faQuoteLeft
)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    translation?: {}
    orders?: {}
    customers?: {}
    mailshots?: {}
    showcase?: {}
    content?: {}
    service: {}
    rental: {}
    trade_units?: {}
    history?: {}
    stocks?: {}
    images?: {}
    attachments?: {}
    master : boolean
    mini_breadcrumbs? : any[]
    masterRoute?: routeType
    taxonomy: {
        department?: {
            name: string
            tooltip: string
            route: {
                name: string
                parameters: Record<string, string>
            }
        }
        family?: {
            name: string
            tooltip: string
            route: {
                name: string
                parameters: Record<string, string>
            }
        }
    }
    webpage_canonical_url?: string
    sales: {}
    is_single_trade_unit?: boolean
    trade_unit_slug?: string
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Record<string, any> = {
        showcase: ProductShowcase,
        mailshots: TableMailshots,
        customers: TableCustomers,
        orders: TableOrders,
        details: ModelDetails,
        service: ProductService,
        rental: ProductRental,
        history: TableHistories,
        favourites: TableProductFavourites,
        reminders: TableProductBackInStockReminders,
        trade_units: TableTradeUnits,
        stocks: TableOrgStocks,
        images: TradeUnitImagesManagement,
        translation: ProductTranslation,
        attachments : AttachmentManagement,
        sales: ProductSales,
        content: ProductContent,
    }
    console.log(currentTab.value)
    return components[currentTab.value]
})

// Warning flag
const showMissingTaxonomyMessage = computed(() => {
    return !props.taxonomy?.department && !props.taxonomy?.family
})


</script>

<template>

    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead" >
        <template #afterTitle>
             <Link v-if="master" :href="route(masterRoute.name, masterRoute.parameters)"  v-tooltip="trans('Go to Master')">
                <FontAwesomeIcon
                    icon="fab fa-octopus-deploy"
                    color="#4B0082"
                />
            </Link>
            <Link v-if="is_single_trade_unit && trade_unit_slug" :href="route('grp.trade_units.units.show', [trade_unit_slug])" v-tooltip="trans('Go to Trade Unit')">
                <FontAwesomeIcon
                    icon="fal fa-atom"
                />
            </Link>
        </template>

        <template #other>
        </template>
    </PageHeading>


    <Message v-if="showMissingTaxonomyMessage" severity="warn" class="mb-4">
        {{trans('Both department and family data are missing in taxonomy.')}}
    </Message>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <div v-if="mini_breadcrumbs.length != 0" class="bg-white  px-4 py-2  w-full  border-gray-200 border-b overflow-x-auto">
        <Breadcrumb :model="mini_breadcrumbs">
            <template #item="{ item, index }">
                <div class="flex items-center gap-1 whitespace-nowrap">
                    <component :is="item.to ? Link : 'span'" :href="route(item.to.name,item.to.parameters)" v-tooltip="item.tooltip"
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


    <component :is="component" :data="props[currentTab]" :tab="currentTab" :handleTabUpdate />
</template>


<style scoped>
/* Remove default breadcrumb styles */
:deep(.p-breadcrumb) {
    padding: 0;
    margin: 0;
    background: transparent;
    border: none;
}
</style>
