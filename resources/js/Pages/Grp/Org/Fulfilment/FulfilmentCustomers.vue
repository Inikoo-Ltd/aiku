<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 25 Jan 2024 11:44:31 Malaysia Time, Bali Office, Indonesia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup  lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TableFulfilmentCustomers from '@/Components/Tables/Grp/Org/Fulfilment/TableFulfilmentCustomers.vue';
import { capitalize } from "@/Composables/capitalize"
import { library } from '@fortawesome/fontawesome-svg-core'
import { faUserClock, faUserTimes, faUserCircle, faNarwhal } from '@fal';

library.add(faUserClock, faUserTimes, faUserCircle, faNarwhal)

const props = defineProps<{
    data: object
    title: string
    pageHead: object
}>()

</script>

<template>
    <!-- <pre>{{ data }}</pre> -->
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <div class="overflow-x-auto border-r border-b border-gray-300">
      <TableFulfilmentCustomers :data="data"  />
    </div>
</template>
