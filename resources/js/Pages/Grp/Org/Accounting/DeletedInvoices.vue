<!--
  -  Author: Jonathan Lopez <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Jonathan Lopez
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import { capitalize } from "@/Composables/capitalize"
import {library} from '@fortawesome/fontawesome-svg-core';
import {
  faFileMinus,
  faHandHoldingUsd
} from "@fal";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import TableDeletedInvoices from '@/Components/Tables/Grp/Org/Accounting/TableDeletedInvoices.vue'

library.add(faFileMinus, faHandHoldingUsd);

defineProps<{
  pageHead: PageHeadingTypes
  data: object
  title: string
}>()

</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead" />
    <TableDeletedInvoices :data="data" />
</template>

