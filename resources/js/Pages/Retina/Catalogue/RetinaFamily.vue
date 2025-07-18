<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faBullhorn,
    faCameraRetro,
    faCube,
    faFolder,
    faMoneyBillWave,
    faProjectDiagram,
    faTag,
    faUser,
    faBrowser
} from "@fal"
import { faExclamationTriangle } from "@fas"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import ModelDetails from "@/Components/ModelDetails.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import RetinaFamilyShowcase from "@/Components/Showcases/Retina/Catalouge/RetinaFamilyShowcase.vue"
import RetinaTableProducts from "@/Components/Tables/Retina/RetinaTableProducts.vue"
import ButtonAddCategoryToPortfolio from "@/Components/Iris/Products/ButtonAddCategoryToPortfolio.vue"

library.add(
    faFolder,
    faCube,
    faCameraRetro,
    faTag,
    faBullhorn,
    faProjectDiagram,
    faUser,
    faMoneyBillWave,
    faBrowser, faExclamationTriangle
)

const props = defineProps<{
    title: string
    pageHead: object
    tabs: {
        current: string
        navigation: object
    }
    showcase: object
    products: object
    data: object


}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => {
    useTabChange(tabSlug, currentTab)
}

const component = computed(() => {
    const components = {
        showcase: RetinaFamilyShowcase,
        products: RetinaTableProducts,
    }
    return components[currentTab.value] ?? ModelDetails
})
console.log("RetinaFamily.vue", props.data)
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
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" />
</template>
