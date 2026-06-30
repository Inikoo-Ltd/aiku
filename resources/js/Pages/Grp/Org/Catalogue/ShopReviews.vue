<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableReviews from "@/Components/Shop/Reviews/TableReviews.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: Record<string, any>
}>()
</script>

<template>
    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead" />

    <TableReviews :data="data" />
</template>
