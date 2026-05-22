<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import IncomingDashboard from "@/Components/Warehouse/IncomingDashboard.vue"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    dashboard: object
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <IncomingDashboard :data="dashboard" />
</template>
