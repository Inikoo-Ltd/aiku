<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import ReviewStatsPanel from "@/Components/Shop/Reviews/ReviewStatsPanel.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    stats?: Record<string, any>
    rating_labels?: any[]
}>()
</script>

<template>
    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead" />

    <div class="p-4">
        <ReviewStatsPanel :stats="stats" :rating-labels="rating_labels" />
    </div>
</template>
