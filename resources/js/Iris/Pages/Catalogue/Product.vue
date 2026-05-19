<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCube } from '@fal'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'

import CatalogueOverview from '@/Iris/Components/Catalogue/CatalogueOverview.vue'
import CatalogueTabs from '@/Iris/Components/Catalogue/CatalogueTabs.vue'

library.add(faCube)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
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

            <div class="px-4 py-4">
                <CatalogueOverview :entity="data.product" :data-feed-url="data.data_feed_url" />
            </div>
        </div>
    </div>
</template>
