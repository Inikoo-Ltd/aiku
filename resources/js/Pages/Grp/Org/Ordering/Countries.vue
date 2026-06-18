<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGlobe } from "@fal"
import TableCountriesInShop from '@/Components/Tables/Grp/Org/Ordering/TableCountriesInShop.vue'
import { PageHeadingTypes } from '@/types/PageHeading'

library.add(faGlobe)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: {}
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableCountriesInShop :data="data" />
</template>
