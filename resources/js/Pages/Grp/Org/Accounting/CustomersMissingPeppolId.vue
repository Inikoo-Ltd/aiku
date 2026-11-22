<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faAddressCard } from "@fal"
library.add(faAddressCard)

defineProps<{
    data: object
    title: string
    pageHead: PageHeadingTypes
    countries: string[]
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <p class="px-4 py-3 text-sm text-gray-600">
        {{ ctrans("Business customers invoiced in the last 12 months in :countries who cannot receive Peppol e-invoices yet. Ask each one for their DIČ and enter it on the customer as their Peppol ID, e.g. 0245:2120643690.", { countries: countries.join(", ") }) }}
    </p>
    <Table :resource="data">
        <template #cell(reference)="{ item }">
            <Link :href="route(item.edit_route.name, item.edit_route.parameters)" class="primaryLink">
                {{ item.reference }}
            </Link>
        </template>
        <template #cell(last_invoiced_at)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.last_invoiced_at) }}</span>
        </template>
    </Table>
</template>
