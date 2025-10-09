<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

defineProps<{
    data: {}
    tab?: string
}>()


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item: customer }">
            {{ customer["name"] }}
        </template>

        <template #cell(location)="{ item: customer }">
            <AddressLocation :data="customer['location']" />
        </template>

        <template #cell(created_at)="{ item: customer }">
            <div class="text-gray-500">{{ useFormatTime(customer["created_at"]) }}</div>
        </template>
    </Table>
</template>
