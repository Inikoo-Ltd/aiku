<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import TableEmployeeContracts from '@/Components/Tables/Grp/Org/HumanResources/TableEmployeeContracts.vue'

const props = defineProps<{
    title: string
    pageHead: {}
    data: {}
    unlinked_balances: {}
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableEmployeeContracts :data="data" :unlinkedBalances="unlinked_balances" />
</template>
