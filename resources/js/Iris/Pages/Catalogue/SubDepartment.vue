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

import IrisLayout from '@/Layouts/Iris.vue'
import CatalogueLayout from '@/Iris/Layouts/CatalogueLayout.vue'

import TableIrisFamilies from '@/Iris/Components/Tables/TableIrisFamilies.vue'
import TableIrisProducts from '@/Iris/Components/Tables/TableIrisProducts.vue'
import TableIrisCollection from '@/Iris/Components/Tables/TableIrisCollection.vue'
import CatalogueOverview from '@/Iris/Components/CatalogueOverview.vue'

library.add(faCube, faFolder, faDotCircle, faAlbumCollection, faTachometerAltFast)

defineOptions({ layout: [IrisLayout, CatalogueLayout] })

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
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <div class="px-4 py-4">
        <component
            v-if="component"
            :is="component"
            v-bind="componentProps"
        />
    </div>
</template>
