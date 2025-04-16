<script setup lang="ts">
import {Head, Link} from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"

import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import Table from '@/Components/Table/Table.vue'


const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    data: {}
}>()

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <Table :resource="data" :name="'notifications'" class="mt-5">
        <template #cell(title)="{ item: notification }">
            <Link :href="notification.route" class="primaryLink">
                {{ notification.title }}
            </Link>
        </template>
    </Table>

</template>
