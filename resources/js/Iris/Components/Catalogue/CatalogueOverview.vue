<script setup lang="ts">
import BoxApiUrl from '@/Components/Showcases/Retina/Catalouge/BoxApiUrl.vue'
import ProductCategoryCard from '@/Components/ProductCategoryCard.vue'

defineProps<{
    entity: {
        id?: number
        slug?: string
        code?: string
        name?: string
        description?: string
        image?: any
        [key: string]: any
    }
    dataFeedUrl?: string
}>()
</script>

<template>
    <BoxApiUrl v-if="dataFeedUrl" :routeApi="dataFeedUrl" />

    <div
        class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-6 gap-4"
        :class="dataFeedUrl ? 'mt-4' : ''"
    >
        <div class="col-span-1 md:col-span-2 lg:col-span-2">
            <ProductCategoryCard :data="entity" />
        </div>
    </div>
</template>
