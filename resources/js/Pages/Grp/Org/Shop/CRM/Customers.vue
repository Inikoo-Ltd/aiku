<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableCustomers from '@/Components/Tables/Grp/Org/CRM/TableCustomers.vue'
import TableTemplateRecipients from '@/Components/Tables/TableTemplateRecipients.vue'
import { capitalize } from "@/Composables/capitalize"
import { faCircleNotch, faTachometerAlt, faDownload } from "@fal"
import { faExclamationCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { routeType } from '@/types/route'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { PageHeadingTypes } from '@/types/PageHeading'
library.add(faCircleNotch, faExclamationCircle, faTachometerAlt, faDownload)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {},
    dashboard?: {}
    customers: {}
    download_route: {
        xlsx: routeType
        csv: routeType
    }
    filtersStructure?: Record<string, any>
    filters?: Record<string, any>
    estimatedRecipients?: number
    shop_id?: number
    shop_slug?: string
    stateOptions?: { value: string; label: string; count: number }[]
    stateFilter?: string[]
    statusOptions?: { value: string; label: string; count: number }[]
    statusFilter?: string[]
}>()

const downloadUrl = (type: string) => {
    if (props.download_route?.[type]?.name) {
        return route(props.download_route[type].name, props.download_route[type].parameters);
    } else {
        return ''
    }
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <div v-if="!filtersStructure" class="rounded-md ">
                <a :href="(downloadUrl('csv') as string)" target="_blank" rel="noopener">
                    <Button :icon="faDownload" label="CSV" type="tertiary" class="rounded-r-none" />
                </a>
                <a :href="(downloadUrl('xlsx') as string)" target="_blank" rel="noopener">
                    <Button :icon="faDownload" label="xlsx" type="tertiary" class="border-l-0  rounded-l-none" />
                </a>
            </div>
        </template>
    </PageHeading>

    <TableTemplateRecipients v-if="filtersStructure" :filters="filters ?? {}" :filters-structure="filtersStructure"
        :recipients-recipe="filters && Object.keys(filters).length ? filters : null" :shop-id="(shop_id as number)"
        :shop-slug="(shop_slug as string)" :estimated-recipients="estimatedRecipients ?? 0"
        :export-routes="download_route" :show-save="false" :state-options="stateOptions" :state-filter="stateFilter ?? []"
        :status-options="statusOptions" :status-filter="statusFilter ?? []" estimate-label="Estimated Customers" />

    <TableCustomers :data="customers" />
</template>
