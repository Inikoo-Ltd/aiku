<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 09 Aug 2026 10:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSortShapesDown } from "@fal"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faSortShapesDown)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: object
}>()

function jobOrderRoute(jobOrder: { slug: string }) {
    return route(
        'grp.org.productions.show.operations.job-orders.show',
        [route().params['organisation'], route().params['production'], jobOrder.slug]
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <Table :resource="data" class="mt-5">
        <template #cell(reference)="{ item: jobOrder }">
            <Link :href="jobOrderRoute(jobOrder)" class="primaryLink">
                {{ jobOrder.reference }}
            </Link>
        </template>
        <template #cell(state)="{ item: jobOrder }">
            {{ jobOrder.state_label }}
        </template>
        <template #cell(date)="{ item: jobOrder }">
            {{ useFormatTime(jobOrder.date) }}
        </template>
        <template #cell(tasks)="{ item: jobOrder }">
            <span class="tabular-nums">{{ jobOrder.number_tasks_done }} / {{ jobOrder.number_tasks }}</span>
        </template>
    </Table>
</template>
