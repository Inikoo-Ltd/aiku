<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 12:20:38 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TablePurchaseOrders from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrders.vue"
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle, faCheckDouble, faExclamationCircle, faGhost, faPaperPlane, faSeedling, faShare, faSignOutAlt, faSpellCheck, faTimesCircle } from "@fal"

library.add(
    faShare,
    faSeedling,
    faSpellCheck,
    faSignOutAlt,
    faGhost,
    faExclamationCircle,
    faTimesCircle,
    faCheckDouble,
    faCheckCircle,
    faPaperPlane,
)

defineProps<{
    pageHead: object
    title: string
    data: object
}>()
</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead" />
    <TablePurchaseOrders :data="data" />
</template>
