<!--
 * Author: Arya Permana <aryapermana02@gmail.com>
 * Created: Thu, 19 Jun 2024 13:00:40 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import { Head } from '@inertiajs/vue3'
  import PageHeading from '@/Components/Headings/PageHeading.vue'
  import { capitalize } from "@/Composables/capitalize"
  import TablePalletDeliveries from "@/Components/Tables/Grp/Org/Fulfilment/TablePalletDeliveries.vue"
  import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
  import Table from '@/Components/Table/Table.vue'
  
  const props = defineProps<{
      data: {}
      title: string
      pageHead: PageHeadingTypes

  }>()
  
  </script>
  
  <template>
  
      <Head :title="capitalize(title)" />
      <PageHeading :data="pageHead"></PageHeading>
      <Table :data="data" />
  </template>
  