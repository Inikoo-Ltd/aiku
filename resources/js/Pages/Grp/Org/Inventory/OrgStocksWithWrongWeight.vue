<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 05 Nov 2026, Kuala Lumpur, Malaysia
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableOrgStocksWithWrongWeight from "@/Components/Tables/Grp/Org/Inventory/TableOrgStocksWithWrongWeight.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faWeightHanging } from "@fal"

library.add(faWeightHanging)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    orgStocks: {}
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableOrgStocksWithWrongWeight :data="orgStocks" tab="wrong_weights" class="mt-5" />
</template>
