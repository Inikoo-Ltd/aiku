<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 29 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import DispatchDashboard from "@/Components/Warehouse/DispatchDashboard.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTasks, faPaperPlane, faWarehouseAlt, faIndustryAlt, faBoxOpen, faPallet, faShip, faTruckLoading, faPlane, faShippingFast, faHourglassStart, faHandHoldingUsd, faCogs, faClipboardCheck } from "@fal"

library.add(faTasks, faPaperPlane, faWarehouseAlt, faIndustryAlt, faBoxOpen, faPallet, faShip, faTruckLoading, faPlane, faShippingFast, faHourglassStart, faHandHoldingUsd, faCogs, faClipboardCheck)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: object
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <DispatchDashboard tab="orders" :data="data" />
</template>
