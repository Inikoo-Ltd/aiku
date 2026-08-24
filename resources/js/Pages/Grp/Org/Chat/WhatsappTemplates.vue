<!--
  - Author: Artha <artha@aw-advantage.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import Table from "@/Components/Table/Table.vue"

defineProps<{
    data: object
    title: string
    pageHead: object
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Table :resource="data">
        <template #cell(synchronize_at)="{ item: template }">
            {{ useFormatTime(template.synchronize_at) }}
        </template>
    </Table>
</template>
