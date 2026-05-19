<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCube, faFolder, faDotCircle, faAlbumCollection, faTachometerAltFast } from '@fal'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import { useTabChange } from '@/Composables/tab-change'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'

import TableIrisFamilies from '@/Iris/Components/Catalogue/TableIrisFamilies.vue'
import TableIrisProducts from '@/Iris/Components/Catalogue/TableIrisProducts.vue'
import TableIrisCollection from '@/Iris/Components/Catalogue/TableIrisCollection.vue'
import CatalogueOverview from '@/Iris/Components/Catalogue/CatalogueOverview.vue'
import CatalogueTabs from '@/Iris/Components/Catalogue/CatalogueTabs.vue'

library.add(faCube, faFolder, faDotCircle, faAlbumCollection, faTachometerAltFast)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: Record<string, any>
    }
    data: {
        sub_department: {
            id: number
            slug: string
            code: string
            name: string
            description?: string
            image?: any
        }
        data_feed_url?: string
    }
    families?: object
    products?: object
    collections?: object
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Record<string, any> = {
        overview: CatalogueOverview,
        families: TableIrisFamilies,
        products: TableIrisProducts,
        collections: TableIrisCollection,
    }
    return components[currentTab.value] ?? null
})

const componentProps = computed(() => {
  if (currentTab.value === 'overview') {
    return {
      entity: props.data.sub_department,
      dataFeedUrl: props.data.data_feed_url,
    }
  }

  return {
    data: (props as Record<string, any>)[currentTab.value],
    tab: currentTab.value,
  }
})
</script>

<template>
    <Head :title="capitalize(title)" />

    <div class="max-w-7xl mx-auto my-8">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <CatalogueTabs />

            <PageHeading :data="pageHead" />
            <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

            <div class="px-4 py-4">
                <component
                    v-if="component"
                    :is="component"
                    v-bind="componentProps"
                />
            </div>
        </div>
    </div>
</template>
