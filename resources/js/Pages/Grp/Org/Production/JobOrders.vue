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
import { ctrans } from "@/Composables/useTrans"

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
            <span v-if="jobOrder.hold_reason" v-tooltip="jobOrder.hold_reason" class="ml-2 rounded bg-yellow-100 px-1.5 text-xs font-semibold text-yellow-800">{{ ctrans("On hold") }}</span>
            <span v-if="jobOrder.done_not_received_days !== null" class="ml-2 whitespace-nowrap rounded bg-amber-100 px-1.5 text-xs font-semibold text-amber-800">{{ ctrans("Done – not received :days days", { days: jobOrder.done_not_received_days }) }}</span>
        </template>
        <template #cell(artisan)="{ item: jobOrder }">
            {{ jobOrder.artisan ?? '-' }}
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
