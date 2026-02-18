<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 17 Feb 2026 15:26:29 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head} from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTags, faTasksAlt, faChartPie, faPaperPlane, faHourglassHalf, faUserCheck, faHandPaper, faBoxCheck, faBoxOpen, faCheckDouble, faTasks } from "@fal"
import TableDeliveryNotesInOrgStock from "@/Components/Tables/Grp/Inventory/TableDeliveryNotesInOrgStock.vue"

library.add(faTags, faTasksAlt, faChartPie, faPaperPlane, faHourglassHalf, faUserCheck, faHandPaper, faBoxCheck, faBoxOpen, faCheckDouble, faTasks)

defineProps<{
  pageHead: PageHeadingTypes
  title: string
  data?: {}
}>()


</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
  </PageHeading>
  <TableDeliveryNotesInOrgStock  :data="data" />
</template>
