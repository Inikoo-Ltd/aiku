<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, provide, ref, watch } from "vue"

import PageHeading from "@/Components/Headings/PageHeading.vue"

import { capitalize } from "@/Composables/capitalize"
import { useLayoutStore } from "@/Stores/layout"
import TableProductsInVariant from "@/Components/Tables/Grp/Goods/TableProductsInVariant.vue"

import Tabs from "@/Components/Navigation/Tabs.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faImage } from "@far"
import { faMoneyBill } from "@fal"
import MasterVariantShowcase from "@/Components/Showcases/Grp/MasterVariantShowcase.vue"
import { useTabChange } from "@/Composables/tab-change"
import TableMasterProductsPricing from "@/Components/Tables/Grp/Goods/TableMasterProductsPricing.vue"
import TableVariants from "@/Components/Tables/Grp/Org/Catalogue/TableVariants.vue"
import VariantProductOrdering from "@/Components/Master/VariantProductOrdering.vue"

library.add(faImage, faMoneyBill)

const layout = useLayoutStore()
provide("layout", layout)

type Variant = {
    label: string
    options: string[]
}

type VariantProductMap = {
    product: { id: number }
    [key: string]: any
}

type MasterProduct = {
    id: number
    name: string
    slug: string
    unit?: string
    units?: string[]
    main_images?: { webp?: string }
    gpsr?: any
    properties?: any
    attachment_box?: any
    salesData?: any
}

const props = defineProps<{
    title: string
    pageHead: any
    tabs: {
        current: string
        navigation: {}
    }
    showcase?: {}
    variants?: {}
    products?: { id: number, code: string, name: string | null, image_thumbnail?: any }[]
    pricing?: {}
    reorderRoute?: { name: string, parameters: Record<string, unknown> } | null
    masterProductCategoryId?: number
    pricingMajorCurrencies?: string[]
    pricingCurrencies?: Record<string, any>
    pricingCostRates?: Record<string, number | null>
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

// pricingCurrencies/pricingCostRates are optional props (skipped unless the page
// loads directly on the pricing tab) — fetch them once when the tab is first opened
watch(currentTab, (tab) => {
    if (tab === 'pricing' && !props.pricingCurrencies) {
        router.reload({ only: ['pricingCurrencies', 'pricingCostRates'] })
    }
})


const component = computed(() => {
    const components: Record<string, any> ={
        showcase: MasterVariantShowcase,
        products: VariantProductOrdering,
        variants: TableVariants,
        pricing: TableMasterProductsPricing,
    }
    return components[currentTab.value]
})

</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component
        :is="component"
        :tab="currentTab"
        :master="true"
        :data="props[currentTab]"
        :majorCurrencies="pricingMajorCurrencies"
        :pricingCurrencies="pricingCurrencies"
        :pricingCostRates="pricingCostRates"
        :masterProductCategoryId="masterProductCategoryId"
        :reorderRoute="reorderRoute"
    />

</template>
