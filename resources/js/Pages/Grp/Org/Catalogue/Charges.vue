<!--
  -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
  -  Created: Mon, 15 July 2024 17:33:07 Bali, Indonesia
  -  Copyright (c) 2024, Vika Aqordi
  -->

  <script setup lang="ts">
  import { Head } from '@inertiajs/vue3'
  import PageHeading from '@/Components/Headings/PageHeading.vue'
  import { capitalize } from "@/Composables/capitalize"
  import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
  import Table from '@/Components/Table/Table.vue'
  import TableCharges from '@/Components/Tables/Grp/Org/Catalogue/TableCharges.vue'
  
  const props = defineProps<{
      data: {}
      title: string
      pageHead: PageHeadingTypes
  }>()
  
  </script>
  
  <template>
      <Head :title="capitalize(title)" />
      <PageHeading :data="pageHead" />
  
      <TableCharges :data="data" />
  </template>