<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 28 Jan 2025 01:32:11 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TableRefunds from "@/Components/Tables/Grp/Org/Accounting/TableRefunds.vue";
import { capitalize } from "@/Composables/capitalize"
import {library} from '@fortawesome/fontawesome-svg-core';
import { Icon } from "@/types/Utils/Icon";
import Button from '@/Components/Elements/Buttons/Button.vue'
import {
  faFileMinus
} from "@fal";
import { PageHeading as TSPageHeading } from "@/types/PageHeading";

library.add(faFileMinus);

const props = defineProps<{
  pageHead: TSPageHeading
  data: object
  title: string
  invoiceExportOptions: {
    type: string
    name: string
    label: string
    parameters: any
    tooltip: string
    icon: Icon
  }[]
}>()


</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <div v-if="props.invoiceExportOptions?.length" class="flex flex-wrap border border-gray-300 rounded-md overflow-hidden h-fit">
                <a v-for="exportOption in props.invoiceExportOptions"
                :href="exportOption.name ? route(exportOption.name, exportOption.parameters) : '#'"
                target="_blank"
                class="w-auto mt-0 sm:flex-none text-base"
                v-tooltip="exportOption.tooltip"
                >
                <Button
                    :label="exportOption.label"
                    :icon="exportOption.icon"
                    type="tertiary"
                    class="rounded-none border-transparent"
                />
                </a>
            </div>
        </template>
    </PageHeading>
    <TableRefunds :data="data" />
</template>

