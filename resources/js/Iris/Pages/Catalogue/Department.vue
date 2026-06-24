<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faCube,
    faFolder,
    faFolderTree,
    faFolderDownload,
    faDotCircle,
    faAlbumCollection,
    faTachometerAltFast,
} from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import { useTabChange } from '@/Composables/tab-change'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'
import Breadcrumb from 'primevue/breadcrumb'

import TableIrisSubDepartment from '@/Iris/Components/Catalogue/TableIrisSubDepartment.vue'
import TableIrisFamilies from '@/Iris/Components/Catalogue/TableIrisFamilies.vue'
import TableIrisProducts from '@/Iris/Components/Catalogue/TableIrisProducts.vue'
import TableIrisCollection from '@/Iris/Components/Catalogue/TableIrisCollection.vue'
import CatalogueOverview from '@/Iris/Components/Catalogue/CatalogueOverview.vue'
import CatalogueTabs from '@/Iris/Components/Catalogue/CatalogueTabs.vue'

library.add(faCube, faFolder, faFolderTree, faFolderDownload, faDotCircle, faAlbumCollection, faTachometerAltFast)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: Record<string, any>
    }
    mini_breadcrumbs?: Array<{
        label: string
        tooltip?: string
        title?: string
        icon?: any
        to?: {
            name: string
            parameters?: Record<string, any>
        }
    }>
    data: {
        department: {
            id: number
            slug: string
            code: string
            name: string
            description?: string
            image?: any
        }
        data_feed_url?: string
    }
    sub_departments?: object
    families?: object
    products?: object
    collections?: object
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Record<string, any> = {
        overview: CatalogueOverview,
        sub_departments: TableIrisSubDepartment,
        families: TableIrisFamilies,
        products: TableIrisProducts,
        collections: TableIrisCollection,
    }
    return components[currentTab.value] ?? null
})

const componentProps = computed(() => {
  if (currentTab.value === 'overview') {
    return {
      entity: props.data.department,
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

            <div
                v-if="mini_breadcrumbs && mini_breadcrumbs.length"
                class="bg-white px-4 py-2 w-full border-gray-200 border-b overflow-x-auto"
            >
                <Breadcrumb :model="mini_breadcrumbs">
                    <template #item="{ item }">
                        <div class="flex items-center gap-1 whitespace-nowrap">
                            <component
                                :is="item.to ? Link : 'span'"
                                :href="item.to ? route(item.to.name, item.to.parameters) : undefined"
                                v-tooltip="item.tooltip"
                                :title="item.title"
                                class="flex items-center gap-2 text-sm text-gray-500 transition-colors duration-150"
                                :class="{ 'cursor-default': !item.to }"
                            >
                                <FontAwesomeIcon v-if="item.icon" :icon="item.icon" class="h-4 w-4" />
                                <span>{{ item.label || '-' }}</span>
                            </component>
                        </div>
                    </template>
                </Breadcrumb>
            </div>

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

<style scoped>
:deep(.p-breadcrumb) {
    padding: 0;
    margin: 0;
    background: transparent;
    border: none;
}

:deep(.p-breadcrumb-list) {
    list-style: none;
    margin: 0;
    padding: 0;
}

:deep(.p-breadcrumb-list > li.p-breadcrumb-separator:first-child) {
    display: none !important;
}
</style>
