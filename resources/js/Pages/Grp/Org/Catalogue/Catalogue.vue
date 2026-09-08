<script setup lang="ts">
import { type Component, computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'

import CatalogueShowcase from '@/Components/Catalogue/CatalogueShowcase.vue'
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
import { faTrophy, faAlignLeft } from '@fal'

library.add(faTrophy, faAlignLeft);

type TabKey = 'showcase' | 'top_listed_families' | 'top_listed_products' | 'top_sold_products'

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
                    v-tooltip="trans('Go to Master')"
                    class="mr-1 opacity-70 hover:opacity-100"
                >
                    <FontAwesomeIcon :icon="faOctopusDeploy" color="#4B0082" />
                </Link>
            </div>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="currentTabData" :tab="currentTab" />
</template>
