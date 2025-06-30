<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 28 Jun 2025 20:21:09 British Summer Time, Sheffield, UK
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import UserAgent from "@/Components/Elements/Info/UserAgent.vue"

defineProps<{
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
        <template #cell(user_agentzz)="{ item: user }">
            <UserAgent :data="user.user_agent" />
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
