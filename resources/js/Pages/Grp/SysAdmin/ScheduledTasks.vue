<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Wed, 03 Dec 2025 11:05:43 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script lang="ts" setup>
import Table from "@/Components/Table/Table.vue";
import { capitalize } from "@/Composables/capitalize";
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { useFormatTime } from "@/Composables/useFormatTime";

defineProps<{
    pageHead: any
    title: string
    scheduledTasks: any
}>();
</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"/>
    <Table :resource="scheduledTasks">
        <template #cell(started_at)="{ item }">
            {{ useFormatTime(item.started_at, {formatTime: 'hms', keepTimezone: true}) }}
        </template>

        <template #cell(finished_at)="{ item }">
            {{ useFormatTime(item.finished_at, {formatTime: 'hms', keepTimezone: true}) }}
        </template>
    </Table>
</template>
