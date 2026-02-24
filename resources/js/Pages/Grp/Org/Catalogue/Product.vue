<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faBox, faBullhorn, faCameraRetro, faCube, faFolder,
    faMoneyBillWave, faProjectDiagram, faRoad, faShoppingCart,
    faStream, faUsers, faHeart, faMinus,
    faFolderTree, faBrowser, faLanguage,faFolders, faPaperclip,
    faFolderDownload,faQuoteLeft,
    faSync,
    faSearch
} from '@fal'
import { ref, computed } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import { capitalize } from "@/Composables/capitalize"
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import Breadcrumb from 'primevue/breadcrumb'
import Message from 'primevue/message'
import { FontAwesomeIcon, FontAwesomeLayers } from '@fortawesome/vue-fontawesome'
import type { PageHeadingTypes } from '@/types/PageHeading'
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
import ProductCategoryTimeSeriesTable from "@/Components/Product/ProductCategoryTimeSeriesTable.vue";
import { trans } from "laravel-vue-i18n"
import ProductContent from '@/Components/Showcases/Grp/ProductContent.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { faMagnifyingGlass, faMagnifyingGlassArrowRight } from '@fortawesome/free-solid-svg-icons'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import { faShapes, faStar } from '@fas'
import ButtonReindexWebpage from '@/Components/Webpages/ButtonReindexWebpage.vue'


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
    faQuoteLeft,
    faMagnifyingGlass
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
    family_slug : string
    luigi_data: {
        webpage_id: number
        last_reindexed: string
        luigisbox_tracker_id: string
        luigisbox_private_key: string
        luigisbox_lbx_code: string
    }
    master : boolean
    mini_breadcrumbs? : any[]
    masterRoute?: routeType
    is_external_shop?: boolean
    product_state?: boolean
    variant?: {}
    is_variant_leader?: boolean
   /*  taxonomy: {
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
    } */
    webpage_canonical_url?: string
    sales?: {}
    salesData?: object
    is_single_trade_unit?: boolean
    reminders?: {}
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
        attachments: AttachmentManagement,
        sales: ProductCategoryTimeSeriesTable,
        content: ProductContent,
    }
    return components[currentTab.value]
})

// Warning flag
/* const showMissingTaxonomyMessage = computed(() => {
    if(props.is_external_shop) {
        return false
    }
    return !props.taxonomy?.department && !props.taxonomy?.family
})
 */


const routeVariant = () => {
    return route(
        'grp.org.shops.show.catalogue.families.show.variants.show',
        {
            organisation: (route().params as RouteParams).organisation,
            shop: (route().params as RouteParams).shop,
            family: props.family_slug,
            variant: props.variant.slug,
        }
    )
}


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
            <!-- TODO PLEASE CHANGE TO HAVE LINK TO VARIANT -->
            <Link  v-if="variant"  :href="routeVariant()" v-tooltip="trans('Go to Variant')">
                <FontAwesomeIcon :icon="is_variant_leader ? faStar : faShapes" class="text-yellow-500 cursor-pointer" />
            </Link>
            
        </template>

        <template #button-reindex>
            <div class="w-fit">
                <ButtonReindexWebpage
                    :webpage="{
                        id: luigi_data.webpage_id,
                        luigi_data: {
                            luigisbox_tracker_id: luigi_data.luigisbox_tracker_id,
                            luigisbox_private_key: luigi_data.luigisbox_private_key
                        }
                    }"
                >
                    <template #default="{ isLoadingReindexing }">
                        <Button
                            v-tooltip="trans('Use this feature to update data to Luigi Search.')"
                            method="post"
                            :style="'edit'"
                            size="sm"
                            xrouteTarget="{name: 'grp.models.webpage_luigi.reindex', parameters: { webpage: luigi_data.webpage_id }}"
                            :loading="isLoadingReindexing"
                        >
                            <template #icon>
                                <FontAwesomeIcon :icon="faSearch" class="" fixed-width aria-hidden="true" />
                            </template>
                            <template #label>
                                {{ trans('Reindex') }}
                            </template>
                        </Button>
                    </template>
                </ButtonReindexWebpage>
            </div>
        </template>
        <template #other>
        </template>
    </PageHeading>


    <!-- <Message v-if="showMissingTaxonomyMessage" severity="warn" class="mb-4">
        {{trans('Both department and family data are missing in taxonomy.')}}
    </Message> -->

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
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


    <component :is="component" :data="props[currentTab]" :tab="currentTab" :handleTabUpdate :salesData="salesData" />
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
