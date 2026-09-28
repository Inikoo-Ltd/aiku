<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInboxIn, faPaperPlane, faPaperclip } from "@fal"
import Table from "@/Components/Table/Table.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"

defineProps<{
    data: object
    tab?: string
}>()

interface SupplierEmailRow {
    is_outbound: boolean
    sent_at: string
    supplier_name: string | null
    organisation_code: string | null
    correspondent: string | null
    subject: string
    snippet: string | null
    number_attachments: number
    route: routeType
}
</script>

<template>
    <Table :resource="data" :name="tab">
        <template #cell(direction)="{ item }: { item: SupplierEmailRow }">
            <FontAwesomeIcon v-if="item.is_outbound" :icon="faPaperPlane" class="text-gray-400" fixed-width v-tooltip="ctrans('Sent')" />
            <FontAwesomeIcon v-else :icon="faInboxIn" class="text-indigo-500" fixed-width v-tooltip="ctrans('Received')" />
        </template>

        <template #cell(sent_at)="{ item }: { item: SupplierEmailRow }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.sent_at, { formatTime: "hm" }) }}</span>
        </template>

        <template #cell(supplier_name)="{ item }: { item: SupplierEmailRow }">
            <span v-if="item.supplier_name">{{ item.supplier_name }}</span>
            <span v-else class="rounded bg-amber-50 px-1.5 py-0.5 text-xs text-amber-700">{{ ctrans("Unassigned") }}</span>
        </template>

        <template #cell(subject)="{ item }: { item: SupplierEmailRow }">
            <Link :href="route(item.route.name, item.route.parameters)" class="primaryLink">{{ item.subject }}</Link>
            <FontAwesomeIcon v-if="item.number_attachments" :icon="faPaperclip" class="ml-1 text-gray-400" fixed-width v-tooltip="ctrans('Attachments') + ': ' + item.number_attachments" />
            <div v-if="item.snippet" class="max-w-xl truncate text-xs text-gray-500">{{ item.snippet }}</div>
        </template>
    </Table>
</template>
