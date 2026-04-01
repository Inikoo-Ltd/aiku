<!--
 -  Author: Nickel
 -  Created: Tue, 01 Apr 2026
 -  Copyright (c) 2026, Inikoo LTD
 -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableOrganisationStockHistories from "@/Components/Tables/Grp/Org/Inventory/TableOrganisationStockHistories.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faDownload } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { routeType } from "@/types/route"

library.add(faDownload)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    download_route: routeType
    data: {}
}>()

function exportUrl(): string {
    if (!props.download_route?.name) return ""
    const params = new URLSearchParams(window.location.search)
    const bucketFilter = params.get('elements[bucket]')
    const base = route(props.download_route.name, {
        ...props.download_route.parameters,
        type: 'xlsx'
    })
    return bucketFilter ? `${base}&elements[bucket]=${bucketFilter}` : base
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <a :href="exportUrl()" download target="_blank" rel="noopener">
                <Button :icon="faDownload" label="Excel" type="tertiary" />
            </a>
        </template>
    </PageHeading>
    <TableOrganisationStockHistories :data="data" />
</template>
