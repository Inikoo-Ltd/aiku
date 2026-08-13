<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"

const props = defineProps<{
    data: {}
}>()

const formatDate = (dateIso: Date) => {
    const date = new Date(dateIso)
    return date.toLocaleString()
}
</script>

<template>
    <Table :resource="data" class="mt-5">
        <!-- Column: Username (if exist) -->
        <template #cell(username)="{ item: user }">
            <template v-if="user.username">{{ user.username }}</template>
        </template>

        <!-- Column: User Agent (if exist) -->
        <template #cell(user_agent)="{ item: user }">
            {{ user.user_agent }}
        </template>

        <!-- Column: Location (if exist) -->
        <template #cell(location)="{ item: user }">
            <AddressLocation :data="user.location" />
        </template>

        <!-- Column: Datetime (if exist) -->
        <template #cell(datetime)="{ item: user }">
            {{ formatDate(user.datetime) }}
        </template>
    </Table>
</template>
