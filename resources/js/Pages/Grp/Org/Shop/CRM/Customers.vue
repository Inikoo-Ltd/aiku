<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableCustomers from '@/Components/Tables/Grp/Org/CRM/TableCustomers.vue'
import { capitalize } from "@/Composables/capitalize"
import { faCircleNotch } from "@fal"
import { faExclamationCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { routeType } from '@/types/route'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { PageHeadingTypes } from '@/types/PageHeading'
library.add(faCircleNotch, faExclamationCircle)

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
            <div class="rounded-md ">
                <a :href="(downloadUrl('csv') as string)" target="_blank" rel="noopener">
                    <Button :icon="faDownload" label="CSV" type="tertiary" class="rounded-r-none" />
                </a>
                <a :href="(downloadUrl('xlsx') as string)" target="_blank" rel="noopener">
                    <Button :icon="faDownload" label="xlsx" type="tertiary" class="border-l-0  rounded-l-none" />
                </a>
            </div>
        </template>
    </PageHeading>
    <TableCustomers :data="customers" />
</template>
