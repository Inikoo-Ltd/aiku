<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faBullhorn,
    faCameraRetro,
    faCube,
    faFolder, faMoneyBillWave, faProjectDiagram, faTag, faUser
} from '@fal'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import { computed, ref } from "vue"
import type { Component } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { faDiagramNext } from "@fortawesome/free-solid-svg-icons"
import { capitalize } from "@/Composables/capitalize"
import RetinaSubDepartementShowcase from '@/Components/Showcases/Retina/Catalouge/RetinaSubDepartementShowcase.vue'
import RetinaTableProducts from '@/Components/Tables/Retina/RetinaTableProducts.vue'
import RetinaTableFamilies from '@/Components/Tables/Retina/RetinaTableFamilies.vue'
import RetinaTableCollections from '@/Components/Tables/Retina/RetinaTableCollections.vue'


library.add(
    faFolder,
    faCube,
    faCameraRetro,
    faTag,
    faBullhorn,
    faProjectDiagram,
    faUser,
    faMoneyBillWave,
    faDiagramNext,
)


const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string
        navigation: object
    }
    showcase: {}
    customers: {}
    products: {}
    families: {}
    collections: {}
    data: {
        showcase: number
        products: object
    }
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component: Component = computed(() => {
    const components = {
        showcase: RetinaSubDepartementShowcase,
        products: RetinaTableProducts,
        families: RetinaTableFamilies,
        collections: RetinaTableCollections,
    }
    return components[currentTab.value]

});

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
         <template #button-to-portfolio>
            <ButtonAddCategoryToPortfolio :products="data.products.data" :categoryId="data.showcase"
                :routeGetCategoryChannels="{ name: 'retina.json.product_category.channel_ids.index', parameters: { productCategory: data.showcase } }"
                :routeAddPortfolios="{ name: 'retina.models.portfolio.store_to_multi_channels', parameters: { productCategory: data.showcase } }" />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>
