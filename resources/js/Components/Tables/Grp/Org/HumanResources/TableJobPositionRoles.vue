<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import Tag from '@/Components/Tag.vue'

defineProps<{
    data: object
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(label)="{ item: role }">
            <div class="font-medium">{{ role['label'] }}</div>
            <div class="text-xs text-gray-400">{{ role['name'] }}</div>
        </template>

        <template #cell(scope_type)="{ item: role }">
            <Tag :label="role['scope_type']" stringToColor />
        </template>

        <template #cell(scope_name)="{ item: role }">
            <span v-if="role['scope_name']">{{ role['scope_name'] }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>
    </Table>
</template>
