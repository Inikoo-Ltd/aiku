<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Updated & Optimized by ChatGPT
-->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome, faPlus, faPencil, faMinus } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

import TableMasterProductsEdit from "@/Components/Tables/TableMasterProductsEdit.vue"

library.add(faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome, faPlus)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
}>()


</script>

<template>
    <!-- Page Title -->
    <Head :title="capitalize(title)" />

    <!-- Page Heading with slot button -->
    <PageHeading :data="pageHead">
    </PageHeading>

    <TableMasterProductsEdit/>
    
</template>

<style>

</style>
