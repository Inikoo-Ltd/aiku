<!--
  -  Author: Andi Ferdiawan
  -  Created: Thu, 09 Jul 2026 11:00:00 Central Indonesia Time, Bali, Indonesia
  -  Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import TablePackagings from '@/Components/Tables/Grp/Org/Billables/TablePackagings.vue'

defineProps<{
    data: {}
    title: string
    pageHead: PageHeadingTypes
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <TablePackagings :data="data" />
</template>
