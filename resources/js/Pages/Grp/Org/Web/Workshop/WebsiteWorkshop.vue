<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow } from '@fal'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import Tabs from "@/Components/Navigation/Tabs.vue"
import LayoutWorkshop from "@/Components/CMS/Website/Layout/LayoutWorkshop.vue"
import WorkshopProduct from "@/Components/CMS/Website/Product/ProductWorkshop.vue"
import { capitalize } from "@/Composables/capitalize"
import CategoryWorkshop from '@/Components/CMS/Website/Family/CategoryWorkshop.vue'

library.add(faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow)

const props = defineProps<{
    title: string,
    pageHead: {}
    tabs: {
        current: string
        navigation: {}
    }
    color_scheme?: {}
    header?: {}
    menu?: {}
    footer?: {}
    category?: {}
    product?: {}
    website_layout: {}
    family?: {}
}>()

console.log(props)

let currentTab = ref(props.tabs?.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components = {
        website_layout: LayoutWorkshop,
        family: CategoryWorkshop,
        product: WorkshopProduct
    }
    return components[currentTab.value]
})

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" />
</template>
