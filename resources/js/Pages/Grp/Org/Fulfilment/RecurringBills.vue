<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 27 Apr 2024 18:08:36 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TableRecurringBills from "@/Components/Tables/Grp/Org/Fulfilment/TableRecurringBills.vue";
import {capitalize} from "@/Composables/capitalize"
import { PageHeading as PageHeadingTS } from '@/types/PageHeading'

defineProps<{
  pageHead: PageHeadingTS
  title: string
  data: {}
}>()

</script>
<template>
  <Head :title="capitalize(title)"/>
  <PageHeading :data="pageHead"></PageHeading>
  <TableRecurringBills :data="data" />
</template>

