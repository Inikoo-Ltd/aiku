<!--
  - Author: andiferdiawan (https://github.com/andiferdiawan)
  - Created: Wednesday, 21 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2026, andiferdiawan
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes, faLink, faUnlink } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { Link } from "@inertiajs/vue3"
import { RouteParams } from "@/types/route-params"

library.add(faCheck, faTimes, faLink, faUnlink)

defineProps<{
    data: object
    tab?: string
}>()

function customerRoute(customerId: number): string | null {
    try {
        const params = route().params as RouteParams
        return route("grp.org.shops.show.crm.customers.show", [
            params.organisation,
            params.shop,
            customerId,
        ])
    } catch {
        return null
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item: contact }">
            <span class="font-medium text-sm text-gray-800">{{ contact.name }}</span>
        </template>

        <template #cell(phone)="{ item: contact }">
            <span class="text-sm font-mono text-gray-600">+{{ contact.phone }}</span>
        </template>

        <template #cell(contact_status)="{ item: contact }">
            <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                :class="contact.contact_status === 'valid'
                    ? 'bg-green-100 text-green-700'
                    : 'bg-red-100 text-red-700'"
            >
                {{ contact.contact_status }}
            </span>
        </template>

        <template #cell(opted_in)="{ item: contact }">
            <FontAwesomeIcon
                :icon="contact.opted_in ? faCheck : faTimes"
                :class="contact.opted_in ? 'text-green-500' : 'text-gray-300'"
                fixed-width
            />
        </template>

        <template #cell(allow_broadcast)="{ item: contact }">
            <FontAwesomeIcon
                :icon="contact.allow_broadcast ? faCheck : faTimes"
                :class="contact.allow_broadcast ? 'text-green-500' : 'text-gray-300'"
                fixed-width
            />
        </template>

        <template #cell(customer_id)="{ item: contact }">
            <template v-if="contact.customer_id">
                <Link
                    :href="customerRoute(contact.customer_id) ?? '#'"
                    class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800"
                >
                    <FontAwesomeIcon :icon="faLink" class="text-xs" fixed-width />
                    {{ contact.customer?.name ?? `#${contact.customer_id}` }}
                </Link>
            </template>
            <template v-else>
                <span class="inline-flex items-center gap-1 text-xs text-gray-400">
                    <FontAwesomeIcon :icon="faUnlink" class="text-xs" fixed-width />
                    Not linked
                </span>
            </template>
        </template>
    </Table>
</template>
