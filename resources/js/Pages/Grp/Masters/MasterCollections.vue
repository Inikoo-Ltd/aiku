<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome } from '@fal'
import { library } from "@fortawesome/fontawesome-svg-core"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import TableMasterCollections from '@/Components/Tables/Grp/Goods/TableMasterCollections.vue'
library.add(faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
}>()
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableMasterCollections :data="data" />
</template>