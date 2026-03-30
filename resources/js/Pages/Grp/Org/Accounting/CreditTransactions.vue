<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import TableTopUps from "@/Components/Tables/Grp/Org/Accounting/TableTopUps.vue"
import TableCreditTransactions from "@/Components/Tables/Grp/Org/Accounting/TableCreditTransactions.vue"
  
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
      <TableCreditTransactions :data="data" />
  </template>
  