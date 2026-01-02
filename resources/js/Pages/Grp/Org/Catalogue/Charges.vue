<!--
  -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
  -  Created: Mon, 15 July 2024 17:33:07 Bali, Indonesia
  -  Copyright (c) 2024, Vika Aqordi
  -->

  <script setup lang="ts">
  import { Head } from '@inertiajs/vue3'
  import PageHeading from '@/Components/Headings/PageHeading.vue'
  import { capitalize } from "@/Composables/capitalize"
  import { PageHeadingTypes } from '@/types/PageHeading'
  import TableCharges from '@/Components/Tables/Grp/Org/Catalogue/TableCharges.vue'
  
defineProps<{
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