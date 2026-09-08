<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 02 Sep 2026 21:40:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

defineProps<{ data: object; tab?: string }>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(datetime)="{ item }">
            <span class="whitespace-nowrap text-gray-500">{{ useFormatTime(item.datetime, { formatTime: "hms", keepTimezone: true }) }}</span>
        </template>
        <template #cell(route_name)="{ item }">
            <span class="font-mono text-xs text-gray-700">{{ item.route_name }}</span>
        </template>
        <template #cell(location)="{ item }">
            <AddressLocation :data="item.location" />
        </template>
    </Table>
</template>
