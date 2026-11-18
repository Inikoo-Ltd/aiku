<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import ProductionOutput from "@/Components/Warehouse/ProductionOutput.vue"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    production_output: { data: any[] }
    can_edit: boolean
    put_away_route: { name: string; parameters: Record<string, string> }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <ProductionOutput :resource="production_output" :canEdit="can_edit" :putAwayRoute="put_away_route" />
</template>
