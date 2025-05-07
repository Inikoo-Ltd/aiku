<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Tue, 28 Feb 2023 10:07:36 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

  <script setup lang="ts">
  import { Head } from "@inertiajs/vue3"
  import PageHeading from "@/Components/Headings/PageHeading.vue"
  import { capitalize } from "@/Composables/capitalize"
  import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import TableTopUps from "@/Components/Tables/Grp/Org/Accounting/TableTopUps.vue"
  
  const props = defineProps<{
      title: string
      pageHead: PageHeadingTypes
      data: {
          data: {}
      }
  }>()
  
  </script>
  
  <template>
      <Head :title="capitalize(title)" />
      <PageHeading :data="pageHead"></PageHeading>
      <TableTopUps :data="data"/>
  </template>
  