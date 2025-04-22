<!--
- Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
- Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
- Copyright (c) 2023, Inikoo LTD
-->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBox, faBullhorn, faCameraRetro, faCube, faFolder, faMoneyBillWave, faProjectDiagram, faRoad, faShoppingCart, faStream, faUsers } from '@fal'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import ModelDetails from "@/Components/ModelDetails.vue"
import TableOrders from "@/Components/Tables/Grp/Org/Ordering/TableOrders.vue"
import { useTabChange } from "@/Composables/tab-change"
import { computed, defineAsyncComponent, ref } from "vue"
import type { Component } from 'vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableMailshots from "@/Components/Tables/TableMailshots.vue"
import TableCustomers from "@/Components/Tables/Grp/Org/CRM/TableCustomers.vue"
import DummyTabComponent from "@/Components/DummyTabComponent.vue"
import ProductService from "@/Components/Showcases/Grp/ProductService.vue"
import ProductRental from "@/Components/Showcases/Grp/ProductRental.vue"

import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import ServiceShowcase from '@/Components/Fulfilment/ServiceShowcase.vue'

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
    faRoad
)

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
}>()


const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: {[key: string]: Component} = {
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
