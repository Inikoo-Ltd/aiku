<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import type { Component } from 'vue'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import { useTabChange } from '@/Composables/tab-change'
import { capitalize } from '@/Composables/capitalize'


import {
    PageHeading as PageHeadingTypes
} from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import { routeType } from '@/types/route'
import RetinaTableFamilies from '@/Components/Tables/Retina/RetinaTableFamilies.vue'
import RetinaTableCollections from '@/Components/Tables/Retina/RetinaTableCollections.vue'
import RetinaTableProducts from '@/Components/Tables/Retina/RetinaTableProducts.vue'
import CollectionsShowcase from '@/Components/Dropshipping/Catalogue/CollectionsShowcase.vue'
import RetinaCollectionShowcase from '@/Components/Showcases/Retina/Catalouge/RetinaCollectionShowcase.vue'

import { faPlus } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import TableCollections from '@/Components/Tables/Grp/Org/Catalogue/TableCollections.vue'

library.add(faPlus)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase?: { stats: {} }
    families?: {}
    products?: {}
    collections?: {}
    history?: {};
    routes: {
        families: { dataList: routeType, submitAttach: routeType, detach: routeType }
        products: { dataList: routeType, submitAttach: routeType, detach: routeType }
        collections: { dataList: routeType, submitAttach: routeType, detach: routeType }
    }
}>()

const currentTab = ref(props.tabs.current)
const errorMessage = ref<string>('')


const handleTabUpdate = (tabSlug: string) => {
    useTabChange(tabSlug, currentTab)
    errorMessage.value = ''
}

const component = computed(() => {
    const components: Record<string, Component> = {
        showcase: RetinaCollectionShowcase,
        families: RetinaTableFamilies,
        products: RetinaTableProducts,
        collections: RetinaTableCollections,
    }
    return components[currentTab.value]
})


</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
</template>
