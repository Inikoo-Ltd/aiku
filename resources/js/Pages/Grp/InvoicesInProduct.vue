<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { capitalize } from '@/Composables/capitalize';
import { PageHeadingTypes } from '@/types/PageHeading';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TableInvoices from '@/Components/Tables/Grp/Org/CRM/TableInvoices.vue';

defineProps<{
  title: string
  pageHead: PageHeadingTypes,
  data: any
}>()
</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead" />
  <TableInvoices :data="data" />
</template>
