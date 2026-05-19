<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCube, faFolder, faFolderTree, faFolderDownload } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'
import Breadcrumb from 'primevue/breadcrumb'

import CatalogueOverview from '@/Iris/Components/Catalogue/CatalogueOverview.vue'
import CatalogueTabs from '@/Iris/Components/Catalogue/CatalogueTabs.vue'

library.add(faCube, faFolder, faFolderTree, faFolderDownload)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
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
        product: {
            id: number
            slug: string
            code: string
            name: string
            description?: string
            image?: any
        }
        data_feed_url?: string
    }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />

    <div class="max-w-7xl mx-auto my-8">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <CatalogueTabs />

            <PageHeading :data="pageHead" />

            <div
                v-if="mini_breadcrumbs && mini_breadcrumbs.length"
                class="w-full overflow-x-auto border-b border-gray-200 bg-white px-4 py-2"
            >
                <Breadcrumb :model="mini_breadcrumbs">
                    <template #item="{ item }">
                        <component
                            :is="item.to ? Link : 'span'"
                            :href="item.to ? route(item.to.name, item.to.parameters) : undefined"
                            v-tooltip="item.tooltip"
                            :title="item.title"
                            class="inline-flex items-center gap-2 whitespace-nowrap text-sm text-gray-500 transition-colors duration-150"
                            :class="{ 'cursor-default': !item.to }"
                        >
                            <FontAwesomeIcon v-if="item.icon" :icon="item.icon" class="h-4 w-4 shrink-0" />
                            <span>{{ item.label || '-' }}</span>
                        </component>
                    </template>
                </Breadcrumb>
            </div>

            <div class="px-4 py-4">
                <CatalogueOverview :entity="data.product" :data-feed-url="data.data_feed_url" />
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
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    list-style: none;
    margin: 0;
    padding: 0;
}

:deep(.p-breadcrumb-list > li) {
    display: inline-flex;
    align-items: center;
    flex: 0 0 auto;
}

:deep(.p-breadcrumb-list > li.p-breadcrumb-separator:first-child) {
    display: none !important;
}
</style>
