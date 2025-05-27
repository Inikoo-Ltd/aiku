<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBox, faBullhorn, faCameraRetro, faCube, faFolder, faMoneyBillWave, faProjectDiagram, faRoad, faShoppingCart, faStream, faUsers, faHeart } from '@fal'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import ModelDetails from "@/Components/ModelDetails.vue"
import TableOrders from "@/Components/Tables/Grp/Org/Ordering/TableOrders.vue"
import { useTabChange } from "@/Composables/tab-change"
import { computed, defineAsyncComponent, ref } from "vue"
import type { Component } from 'vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableMailshots from "@/Components/Tables/TableMailshots.vue"
import TableCustomers from "@/Components/Tables/Grp/Org/CRM/TableCustomers.vue"
import ProductShowcase from "@/Components/Showcases/Grp/ProductShowcase.vue"
import ProductService from "@/Components/Showcases/Grp/ProductService.vue"
import ProductRental from "@/Components/Showcases/Grp/ProductRental.vue"
import TableProductFavourites from "@/Components/Tables/Grp/Org/Catalogue/TableProductFavourites.vue"
import TableProductBackInStockReminders from "@/Components/Tables/Grp/Org/Catalogue/TableProductBackInStockReminders.vue"

import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import TableTradeUnits from '@/Components/Tables/Grp/Goods/TableTradeUnits.vue'
import TableOrgStocks from '@/Components/Tables/Grp/Org/Inventory/TableOrgStocks.vue'

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
    faHeart
)

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

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
}>()


const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: {[key: string]: Component} = {
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

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>
