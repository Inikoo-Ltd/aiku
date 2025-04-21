<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 11 Apr 2024 15:14:08 Central Indonesia Time, Sanur , Indonesia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import { Head } from "@inertiajs/vue3"
  import PageHeading from "@/Components/Headings/PageHeading.vue"
  import { capitalize } from "@/Composables/capitalize"
  import TableOrganisations from "@/Components/Tables/Grp/SysAdmin/TableOrganisations.vue"
  import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
  
  defineProps<{
      data: {}
      title: string
      pageHead: PageHeadingTypes
  }>()
  
  </script>
  
  <template>
      <Head :title="capitalize(title)" />
      <PageHeading :data="pageHead"></PageHeading>
      <TableOrganisations :data="data" />
  </template>
  