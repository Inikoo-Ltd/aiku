<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableWebsiteDialogs from "@/Components/Tables/Grp/Websites/TableWebsiteDialogs.vue"
import { capitalize } from "@/Composables/capitalize"
import type { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableWebsiteDialogs :data="data" />
</template>
