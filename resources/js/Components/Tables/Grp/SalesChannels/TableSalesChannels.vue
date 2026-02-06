<script setup lang="ts">
import { inject } from 'vue'
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/Icon.vue'
import Table from '@/Components/Table/Table.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

const locale = inject("locale", aikuLocaleStructure)

defineProps<{
    data: {}
}>()

function salesChannelRoute(salesChannel: any) {
    return route("grp.sales_channels.show", [salesChannel.slug])
}
</script>

<template>
    <Table :resource="data" class="mt-5">
        <template #cell(is_active)="{ item }">
             <Icon :data="item.is_active" class="px-1" />
        </template>
        
        <template #cell(show_in_dashboard)="{ item }">
             <Icon :data="item.show_in_dashboard" class="px-1" />
        </template>

        <template #cell(name)="{ item }">
             <Link :href="salesChannelRoute(item)" class="primaryLink">
                {{ item.name }}
            </Link>
        </template>

        <template #cell(type)="{ item }">
            {{ item.type === 'na' ? 'NA' : item.type.charAt(0).toUpperCase() + item.type.slice(1) }}
        </template>

        <template #cell(sales)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat('GBP', item.sales) }}</span>
        </template>
    </Table>
</template>
