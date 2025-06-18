<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faBox, faBullhorn, faCameraRetro, faCube, faFolder,
    faMoneyBillWave, faProjectDiagram, faRoad, faShoppingCart,
    faStream, faUsers, faHeart, faMinus,
    faFolderTree
} from '@fal'
import { ref, computed, defineAsyncComponent } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import { capitalize } from "@/Composables/capitalize"
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import Breadcrumb from 'primevue/breadcrumb'
import Message from 'primevue/message'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import type { PageHeading as PageHeadingTypes } from '@/types/PageHeading'

// Components
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

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

library.add(
    faFolder,
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
    faMinus
)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    orders?: {}
    customers?: {}
    mailshots?: {}
    showcase?: {}
    service: {}
    rental: {}
    trade_units?: {}
    stocks?: {}
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
}>()
console.log('Product.vue props:', props)
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
        history: ModelChangelog,
        favourites: TableProductFavourites,
        reminders: TableProductBackInStockReminders,
        trade_units: TableTradeUnits,
        stocks: TableOrgStocks
    }
    return components[currentTab.value]
})

// Warning flag
const showMissingTaxonomyMessage = computed(() => {
    return !props.taxonomy?.department && !props.taxonomy?.family
})

// Breadcrumb logic
const breadcrumbItems = computed(() => {
    const items = []

    const hasDepartment = props.taxonomy?.department
    const hasFamily = props.taxonomy?.family

    if (!hasDepartment && !hasFamily) return []

    items.push({
        label: hasDepartment ? props.taxonomy.department.label : '-',
        to: hasDepartment
            ? route(
                props.taxonomy.department.route.name,
                props.taxonomy.department.route.parameters,
            )
            : null,
        tooltip: hasDepartment ? 'Departement ' + props.taxonomy.department.tooltip : 'no department',
        title: hasDepartment ? props.taxonomy.department.name : 'No department',
        icon: faFolderTree,
    })

    if (hasFamily) {
        items.push({
            label: props.taxonomy.family.label,
            to: route(
                props.taxonomy.family.route.name,
                props.taxonomy.family.route.parameters,
            ),
            title: props.taxonomy.family.name,
            tooltip: 'Family ' + props.taxonomy.family.tooltip,
            icon: faFolder
        })
    }
    return items
})
</script>

<template>

    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead" />


    <Message v-if="showMissingTaxonomyMessage" severity="warn" class="mb-4">
        Both department and family data are missing in taxonomy.
    </Message>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <div class="bg-white shadow-sm rounded px-4 py-2 mx-4 mt-2 w-fit border border-gray-200 overflow-x-auto">
        <Breadcrumb :model="breadcrumbItems">
            <template #item="{ item, index }">
                <div class="flex items-center gap-1 whitespace-nowrap">
                    <!-- Breadcrumb link or text -->
                    <component :is="item.to ? Link : 'span'" :href="item.to" v-tooltip="item.tooltip"
                        :title="item.title" class="flex items-center gap-2 text-sm transition-colors duration-150"
                        :class="item.to
                            ? 'text-gray-500'
                            : 'text-gray-500 cursor-default'">
                        <FontAwesomeIcon :icon="item.icon" class="w-4 h-4" />
                        <span class="truncate max-w-[150px]">{{ item.label || '-' }}</span>
                    </component>
                </div>
            </template>
        </Breadcrumb>
    </div>


    <component :is="component" :data="props[currentTab]" :tab="currentTab" />
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
