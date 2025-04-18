<!--
-  Author: Raul Perusquia <raul@inikoo.com>
-  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
-  Copyright (c) 2022, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"

import { faNarwhal } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import TableStoredItemsInWarehouse from '@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemsInWarehouse.vue'
library.add(faNarwhal)

const props = defineProps<{
    data: {}
    title: string
    pageHead: PageHeadingTypes
}>()

</script>
  
  <template>
      <Head :title="capitalize(title)" />
      <PageHeading :data="pageHead"></PageHeading>
      <TableStoredItemsInWarehouse :data="data" :name="undefined" />
  </template>
  