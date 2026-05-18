<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCube, faFolder, faTachometerAltFast } from '@fal'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import { useTabChange } from '@/Composables/tab-change'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'

import IrisLayout from '@/Layouts/Iris.vue'
import CatalogueLayout from '@/Iris/Layouts/CatalogueLayout.vue'

import TableIrisProducts from '@/Iris/Components/Tables/TableIrisProducts.vue'

import CatalogueOverview from '@/Iris/Components/CatalogueOverview.vue'

library.add(faCube, faFolder, faTachometerAltFast)

defineOptions({ layout: [IrisLayout, CatalogueLayout] })

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: Record<string, any>
    }
    data: {
        family: {
            id: number
            slug: string
            code: string
            name: string
            description?: string
            image?: any
        }
        data_feed_url?: string
    }
    products?: object
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Record<string, any> = {
        overview: CatalogueOverview,
        products: TableIrisProducts,
    }
    return components[currentTab.value] ?? null
})
const componentProps = computed(() => {
  if (currentTab.value === 'overview') {
    return {
      entity: props.data.family,
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
        <CatalogueOverview
            v-if="currentTab === 'overview'"
            :entity="data.family"
            :data-feed-url="data.data_feed_url"
        />

        <component
            v-else-if="component"
            :is="component"
            v-bind="componentProps"
        />
    </div>
</template>
