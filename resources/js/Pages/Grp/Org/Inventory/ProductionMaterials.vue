<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 18 Nov 2026 Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import ProductionMaterialRequests from "@/Components/Warehouse/ProductionMaterialRequests.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPalletAlt, faCheck } from "@fal"

library.add(faPalletAlt, faCheck)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    scope: "open" | "done"
    can_work: boolean
    data: { data: any[] }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <ProductionMaterialRequests :resource="data" :scope="scope" :canWork="can_work" />
</template>
