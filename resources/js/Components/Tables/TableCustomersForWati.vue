<!--
  - Author: andiferdiawan (https://github.com/andiferdiawan)
  - Created: Wednesday, 21 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2026, andiferdiawan
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { Link } from "@inertiajs/vue3"
import { RouteParams } from "@/types/route-params"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSpinner, faCommentAltPlus } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { ref, computed } from "vue"

library.add(faSpinner, faCommentAltPlus)

const props = defineProps<{
    data: object
    tab?: string
    routes?: { add: string; bulk_add: string }
}>()

const selectedRow = ref<Record<string, boolean>>({})
const loadingIds = ref<Set<number>>(new Set())
const isBulkLoading = ref(false)

const selectedIds = computed(() =>
    Object.entries(selectedRow.value)
        .filter(([, checked]) => checked)
        .map(([id]) => Number(id))
)

function customerRoute(slug: string): string | null {
    try {
        const params = route().params as RouteParams
        return route("grp.org.shops.show.crm.customers.show", [
            params.organisation,
            params.shop,
            slug,
        ])
    } catch {
        return null
    }
}

async function addSingle(customerId: number): Promise<void> {
    if (!props.routes?.add) return
    loadingIds.value = new Set([...loadingIds.value, customerId])
    try {
        await axios.post(props.routes.add, { customer_id: customerId })
        notify({ title: "Added to Wati", type: "success" })
    } catch {
        notify({ title: "Failed to add contact", type: "error" })
    } finally {
        loadingIds.value.delete(customerId)
        loadingIds.value = new Set(loadingIds.value)
    }
}

async function bulkAdd(): Promise<void> {
    if (!selectedIds.value.length || !props.routes?.bulk_add) return
    isBulkLoading.value = true
    try {
        const { data } = await axios.post(props.routes.bulk_add, { customer_ids: selectedIds.value })
        notify({ title: data.message, type: "success" })
        selectedRow.value = {}
    } catch {
        notify({ title: "Failed to queue bulk add", type: "error" })
    } finally {
        isBulkLoading.value = false
    }
}
</script>

<template>
    <div>
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="selectedIds.length"
                class="mb-3 flex items-center justify-between rounded-lg border border-green-200 bg-green-50 px-4 py-2"
            >
                <span class="text-sm text-green-700">
                    {{ selectedIds.length }} customer{{ selectedIds.length > 1 ? 's' : '' }} selected
                </span>
                <button
                    :disabled="isBulkLoading"
                    class="inline-flex items-center gap-1.5 rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                    @click="bulkAdd"
                >
                    <FontAwesomeIcon
                        :icon="isBulkLoading ? faSpinner : faCommentAltPlus"
                        :class="isBulkLoading ? 'animate-spin' : ''"
                        fixed-width
                    />
                    Add {{ selectedIds.length }} to Wati
                </button>
            </div>
        </Transition>

        <Table
            :resource="data"
            :name="tab"
            checkboxKey="id"
            class="mt-5"
            @onSelectRow="(value: Record<string, boolean>) => selectedRow = value"
        >
            <template #cell(name)="{ item: customer }">
                <Link
                    :href="customerRoute(customer.slug) ?? '#'"
                    class="primaryLink font-medium"
                >
                    {{ customer.name }}
                </Link>
            </template>

            <template #cell(phone)="{ item: customer }">
                <span class="text-sm font-mono text-gray-600">{{ customer.phone ?? '—' }}</span>
            </template>

            <template #cell(email)="{ item: customer }">
                <span class="text-sm text-gray-600">{{ customer.email ?? '—' }}</span>
            </template>

            <template #cell(actions)="{ item: customer }">
                <button
                    :disabled="loadingIds.has(customer.id)"
                    class="inline-flex items-center gap-1 rounded-md border border-green-300 bg-white px-2 py-1 text-xs font-medium text-green-700 hover:bg-green-50 disabled:opacity-50"
                    @click="addSingle(customer.id)"
                >
                    <FontAwesomeIcon
                        :icon="loadingIds.has(customer.id) ? faSpinner : faCommentAltPlus"
                        :class="loadingIds.has(customer.id) ? 'animate-spin' : ''"
                        fixed-width
                    />
                    Add to wati
                </button>
            </template>
        </Table>
    </div>
</template>
