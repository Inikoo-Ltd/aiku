<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Links, Meta } from "@/types/Table"
import { capitalize } from "@/Composables/capitalize"

defineProps<{
    data: {
        data: {}[]
        links: Links
        meta: Meta
    },
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(type)="{ item }">
            {{ capitalize((item.type ?? '').replace('_', ' ')) }}
        </template>

        <template #cell(state)="{ item }">
            {{ capitalize(item.state ?? '') }}
        </template>
    </Table>
</template>
