<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import Table from '../Table/Table.vue'

const props = defineProps<{
    data: {}
    tab: string
}>()
</script>

<template>
    <div>
        <Table :resource="data" :name="tab">
            <template #cell(created_at)="{ item }">
                {{ useFormatTime(item.created_at) }}
            </template>
        </Table>
    </div>
</template>