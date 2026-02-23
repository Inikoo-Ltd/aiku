<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'
import Table from '@/Components/Table/Table.vue'

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: object
}>()
</script>

<template layout="Grp">
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Table :resource="data" class="mt-5">
        <template #cell(year)="{ item }">
            <span class="whitespace-nowrap">
                {{ item.year }}
            </span>
        </template>

        <template #cell(label)="{ item }">
            <span class="whitespace-nowrap">
                {{ item.label || '—' }}
            </span>
        </template>

        <template #cell(type_label)="{ item }">
            <span class="whitespace-nowrap">
                {{ item.type_label }}
            </span>
        </template>

        <template #cell(from)="{ item }">
            <span class="whitespace-nowrap">
                {{ item.from }}
            </span>
        </template>

        <template #cell(to)="{ item }">
            <span class="whitespace-nowrap">
                {{ item.to }}
            </span>
        </template>

        <template #cell(duration_days)="{ item }">
            <span class="whitespace-nowrap">
                {{ item.duration_days }}
            </span>
        </template>
    </Table>
</template>

