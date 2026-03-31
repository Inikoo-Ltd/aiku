<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Fri, 05 Dec 2025 14:26:36 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { faFileExport } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faFileExport)

const props = defineProps<{
    data: object
    title: string
    pageHead: PageHeadingTypes
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <!-- Table -->
    <Table :resource="data" class="mt-5"></Table>
</template>