<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faShippingFast } from "@fal"
import TableCouriersInShop from '@/Components/Tables/Grp/Org/Ordering/TableCouriersInShop.vue'
import { PageHeadingTypes } from '@/types/PageHeading'

library.add(faShippingFast)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: {}
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableCouriersInShop :data="data" />
</template>
