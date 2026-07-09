<!--
  -  Author: Andi Ferdiawan
  -  Created: Thu, 09 Jul 2026 11:00:00 Central Indonesia Time, Bali, Indonesia
  -  Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Links, Meta } from "@/types/Table"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { inject } from "vue"
import { Link } from "@inertiajs/vue3"
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

const packagingEditRoute = (packaging: { slug: string }) => {
    const params = route().params as Record<string, string>

    return route('grp.org.shops.show.billables.packagings.edit', [
        params['organisation'],
        params['shop'],
        packaging.slug,
    ])
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: packaging }">
            <Icon :data="packaging['state_icon']" />
        </template>
        <template #cell(code)="{ item: packaging }">
            <Link :href="packagingEditRoute(packaging)" class="primaryLink font-medium">
                {{ packaging["code"] }}
            </Link>
        </template>
        <template #cell(dimensions)="{ item: packaging }">
            <span v-if="packaging.dimensions" class="whitespace-nowrap">{{ packaging.dimensions }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>
        <template #cell(price)="{ item: packaging }">
            {{ locale.currencyFormat(packaging.currency_code, packaging.price) }}
        </template>
    </Table>
</template>
