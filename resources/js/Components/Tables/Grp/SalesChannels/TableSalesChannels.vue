<script setup lang="ts">
import { inject } from 'vue'
import Icon from '@/Components/Icon.vue'
import Table from '@/Components/Table/Table.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

const locale = inject("locale", aikuLocaleStructure)

defineProps<{
    data: {}
}>()
</script>

<template>
    <Table :resource="data" class="mt-5">
        <template #cell(is_active)="{ item }">
             <Icon :data="item.is_active" class="px-1" />
        </template>

        <template #cell(type)="{ item }">
            {{ item.type === 'na' ? 'NA' : item.type.charAt(0).toUpperCase() + item.type.slice(1) }}
        </template>

        <template #cell(sales)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat('GBP', item.sales) }}</span>
        </template>
    </Table>
</template>
