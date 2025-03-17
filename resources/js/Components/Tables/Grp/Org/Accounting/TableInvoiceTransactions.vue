<script setup lang='ts'>

import Table from '@/Components/Table/Table.vue'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { Link } from '@inertiajs/vue3'

defineProps<{
    data: object
    tab: string
}>()

const locale = inject('locale', aikuLocaleStructure)


</script>

<template>
    <div class="h-min">
        <Table :resource="data" :name="tab">
            <template #cell(net_amount)="{ item }">
                <div :class="item.net_amount < 0 ? 'text-red-500' : ''">
                    {{ locale.currencyFormat(item.currency_code, item.net_amount) }}
                </div>
            </template>
            <template #cell(name)="{ item: invoice }">
                <Link v-if="invoice?.route_desc" :href="route(invoice.route_desc['name'], invoice.route_desc['parameters'])" class="primaryLink py-0.5">
                    {{ invoice.name }}
                </Link>
                <span v-else>{{ invoice.name }}</span>
                <span v-if="invoice.pallet && invoice.handling_date">
                    <br>
                    <span class="text-gray-400 text-xs">Pallet:
                        <Link :href="route(invoice.palletRoute?.name, invoice.palletRoute?.parameters)" class="primaryLink">
                                {{ invoice.pallet }}
                        </Link>
                    </span>
                    <br>
                    <span class="text-gray-400 text-xs">Date: {{ invoice.handling_date }}</span>
                </span>
                <span v-else>
                </span>
		    </template>
        </Table>
    </div>
</template>
