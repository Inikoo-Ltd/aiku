<script setup lang="ts">
import { type Component, computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { ctrans } from '@/Composables/useTrans'

import CatalogueShowcase from '@/Components/Catalogue/CatalogueShowcase.vue'
import TableCatalogueOnItsWay from "@/Components/Tables/Grp/Org/Catalogue/TableCatalogueOnItsWay.vue"
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import TableTopListedProducts from '@/Components/Tables/Grp/Org/CRM/TableTopListedProducts.vue'
import TableTopSoldProducts from '@/Components/Tables/Grp/Org/CRM/TableTopSoldProducts.vue'
import { capitalize } from '@/Composables/capitalize'
import { useTabChange } from '@/Composables/tab-change'
import { PageHeadingTypes } from '@/types/PageHeading'
import { routeType } from '@/types/route'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faOctopusDeploy } from '@fortawesome/free-brands-svg-icons'
import { faTrophy, faAlignLeft, faBooks, faBars, faFolder, faGem, faCube, faTruckContainer } from '@fal'

library.add(faTrophy, faAlignLeft, faBooks, faBars, faFolder, faGem, faCube, faTruckContainer);

type TabKey = 'showcase' | 'top_listed_families' | 'top_listed_products' | 'top_sold_products' | 'on_its_way'

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    url_master?: routeType
    showcase?: any
    top_listed_families?: any
    top_listed_products?: any
    top_sold_products?: any
    on_its_way?: any
}>()

const currentTab = ref<TabKey>(props.tabs.current as TabKey)
const currentTabData = computed(() => props[currentTab.value])
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed<Component>(() => {
    const components: Record<TabKey, Component> = {
        showcase: CatalogueShowcase,
        top_listed_families: TableTopListedProducts,
        top_listed_products: TableTopListedProducts,
        top_sold_products: TableTopSoldProducts,
        on_its_way: TableCatalogueOnItsWay,
    }

    return components[currentTab.value]
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle>
            <div class="whitespace-nowrap">
                <Link
                    v-if="url_master"
                    :href="route(url_master.name, url_master.parameters)"
                    v-tooltip="ctrans('Go to Master')"
                    class="mr-1 opacity-70 hover:opacity-100"
                >
                    <FontAwesomeIcon :icon="faOctopusDeploy" color="#4B0082" fixed-width />
                </Link>
            </div>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="currentTabData" :tab="currentTab" />
</template>
