<!--
  -  Author: stewicca <stewicalf@gmail.com>
  -  Created: Fri, 17 Jul 2026, Bali, Indonesia
  -  Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableOrgStockReplenishments from "@/Components/Tables/Grp/Org/Inventory/TableOrgStockReplenishments.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faDollyFlatbedEmpty, faShoppingBasket } from "@fas"

library.add(faDollyFlatbedEmpty, faShoppingBasket)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    replenishments: {}
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableOrgStockReplenishments :data="replenishments" class="mt-5" />
</template>
