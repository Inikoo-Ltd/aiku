<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 08 Sep 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"

const props = defineProps<{
    data: {}
    currency: string
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
      <template #cell(name)="{ item }">
            <Link :href="route(item.route.name, item.route.parameters)" class="primaryLink">
                {{ item.name }}
            </Link>
        </template>
      <template #cell(status)="{ item }">
            <div v-if="!item.status" class="text-gray-400">—</div>
            <div v-else :class="item.status === 'ENABLED' ? 'text-green-600' : 'text-gray-500'">
                {{ item.status }}
            </div>
        </template>
      <template #cell(budget_amount)="{ item }">
            <div v-if="item.budget_amount === null" class="text-gray-400">—</div>
            <div v-else class="text-gray-500">{{ useLocaleStore().currencyFormat(item.currency_code, item.budget_amount) }}</div>
        </template>
      <template #cell(spend_30d)="{ item }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat(currency, item.spend_30d) }}</div>
        </template>
      <template #cell(spend_total)="{ item }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat(currency, item.spend_total) }}</div>
        </template>
    </Table>
</template>
