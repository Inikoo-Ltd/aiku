<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sun, 22 Nov 2026, Kuala Lumpur, Malaysia
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableStockValueFindings from "@/Components/Tables/Grp/Org/Inventory/TableStockValueFindings.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSearchDollar } from "@fal"

library.add(faSearchDollar)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    findings: {}
    currency: string
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableStockValueFindings :data="findings" :currency="currency" tab="stock_value_findings" class="mt-5" />
</template>
