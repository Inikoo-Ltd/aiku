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
import { PageHeadingTypes } from '@/types/PageHeading'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faReceipt } from "@fal"
library.add(faReceipt)

defineProps<{
  pageHead: PageHeadingTypes
  title: string
  data: {}
}>()

</script>
<template>
  <Head :title="capitalize(title)"/>
  <PageHeading :data="pageHead"></PageHeading>
  <TableRecurringBills :data="data" />
</template>

