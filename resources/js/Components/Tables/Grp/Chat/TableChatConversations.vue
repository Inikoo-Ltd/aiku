<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCircle, faStoreAlt } from '@fal'

library.add(faCircle, faStoreAlt)

defineProps<{
    data: {}
    tab?: string
}>()

const statusColors: Record<string, string> = {
    active: 'text-green-500',
    waiting: 'text-yellow-500',
    resolved: 'text-blue-500',
    transferred: 'text-purple-500',
    closed: 'text-gray-400',
}

function formatDate(dateObj: any): string {
    if (!dateObj) return '-'
    return dateObj.formatted ?? dateObj
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(status)="{ item }">
            <div class="flex items-center gap-x-1.5">
                <FontAwesomeIcon
                    icon="fal fa-circle"
                    :class="statusColors[item.status] ?? 'text-gray-400'"
                    class="text-xs"
                    fixed-width />
                <span class="capitalize">{{ item.status }}</span>
            </div>
        </template>

        <template #cell(contact)="{ item }">
            <Link
                v-if="item.route && item.route.parameters?.shop"
                :href="route(item.route.name, item.route.parameters)"
                class="primaryLink">
                {{ item.contact_name }}
            </Link>
            <span v-else>{{ item.contact_name }}</span>
        </template>

        <template #cell(shop_name)="{ item }">
            <div class="flex items-center gap-x-1.5 text-sm text-gray-600">
                <FontAwesomeIcon icon="fal fa-store-alt" class="text-gray-400" fixed-width />
                {{ item.shop_name ?? '—' }}
            </div>
        </template>

        <template #cell(assigned_agent)="{ item }">
            <span v-if="item.assigned_agent">{{ item.assigned_agent }}</span>
            <span v-else class="text-sm text-gray-400">—</span>
        </template>

        <template #cell(created_at)="{ item }">
            <span class="text-sm text-gray-600">{{ formatDate(item.created_at) }}</span>
        </template>

        <template #cell(closed_at)="{ item }">
            <span v-if="item.closed_at" class="text-sm text-gray-600">{{ formatDate(item.closed_at) }}</span>
            <span v-else class="text-sm text-gray-400">—</span>
        </template>
    </Table>
</template>
