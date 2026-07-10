<!--
  -  Author: Andi Ferdiawan
  -  Created: Fri, 10 Jul 2026 10:00:00 Central Indonesia Time, Bali, Indonesia
  -  Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Links, Meta } from "@/types/Table"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { inject } from "vue"
import Icon from "@/Components/Icon.vue"

defineProps<{
    data: {
        data: {}
        links: Links
        meta: Meta
    },
    tab?: string
}>()

const locale = inject('locale', aikuLocaleStructure)
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: leaflet }">
            <Icon :data="leaflet['state_icon']" />
        </template>
        <template #cell(packaging_code)="{ item: leaflet }">
            <span v-if="leaflet.packaging_code" class="whitespace-nowrap">{{ leaflet.packaging_code }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>
        <template #cell(price)="{ item: leaflet }">
            {{ locale.currencyFormat(leaflet.currency_code, leaflet.price) }}
        </template>
    </Table>
</template>
