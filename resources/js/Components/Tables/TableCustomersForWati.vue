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
import { faSpinner, faCommentAltPlus, faCheckDouble, faTimes } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { ref, computed } from "vue"

library.add(faSpinner, faCommentAltPlus, faCheckDouble, faTimes)

const props = defineProps<{
    data: object
    tab?: string
    routes?: { add: string; bulk_add: string; bulk_add_all: string }
}>()

const tableRef = ref()
const selectedRow = ref<Record<string, boolean>>({})
const loadingIds = ref<Set<number>>(new Set())
const isBulkLoading = ref(false)
const isAddAllLoading = ref(false)
const showConfirmAddAll = ref(false)

function clearSelection(): void {
    if (tableRef.value?.selectRow) {
        Object.keys(tableRef.value.selectRow).forEach(key => {
            tableRef.value.selectRow[key] = false
        })
    }
    tableRef.value?.data?.forEach((item: any) => {
        item.is_checked = false
    })
    selectedRow.value = {}
}

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

async function bulkAddSelected(): Promise<void> {
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

async function bulkAddAll(): Promise<void> {
    if (!props.routes?.bulk_add_all) return
    showConfirmAddAll.value = false
    isAddAllLoading.value = true
    try {
        const { data } = await axios.post(props.routes.bulk_add_all)
        notify({ title: data.message, type: "success" })
    } catch {
        notify({ title: "Failed to queue add all", type: "error" })
    } finally {
        isAddAllLoading.value = false
    }
}
</script>

<template>
    <div>
        <!-- Toolbar -->
        <div class="mb-3 flex items-center justify-between gap-3 px-5 py-3">
            <!-- Left: selection info -->
            <Transition
                enter-active-class="transition ease-out duration-150"
                enter-from-class="opacity-0 -translate-x-2"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 -translate-x-2"
            >
                <div v-if="selectedIds.length" class="flex items-center gap-2">
                    <span class="text-sm text-gray-600 font-medium">
                        {{ selectedIds.length }} selected
                    </span>
                    <button
                        class="text-xs text-gray-400 hover:text-gray-600"
                        @click="clearSelection"
                    >
                        <FontAwesomeIcon :icon="faTimes" fixed-width />
                        Clear
                    </button>
                </div>
            </Transition>

            <!-- Right: action buttons -->
            <div class="flex items-center gap-2 ml-auto">
                <!-- Add Selected -->
                <Transition
                    enter-active-class="transition ease-out duration-150"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-100"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <button
                        v-if="selectedIds.length"
                        :disabled="isBulkLoading"
                        class="inline-flex items-center gap-1.5 rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700 disabled:opacity-50 transition-colors"
                        @click="bulkAddSelected"
                    >
                        <FontAwesomeIcon
                            :icon="isBulkLoading ? faSpinner : faCommentAltPlus"
                            :class="isBulkLoading ? 'animate-spin' : ''"
                            fixed-width
                        />
                        Add {{ selectedIds.length }} to Wati
                    </button>
                </Transition>

                <!-- Add All -->
                <div class="relative">
                    <button
                        v-if="!showConfirmAddAll"
                        :disabled="isAddAllLoading"
                        class="inline-flex items-center pt-2 gap-1.5 rounded-md border border-green-300 bg-white px-3 py-1.5 text-xs font-semibold text-green-700 hover:bg-green-50 disabled:opacity-50 transition-colors"
                        @click="showConfirmAddAll = true"
                    >
                        <FontAwesomeIcon
                            :icon="isAddAllLoading ? faSpinner : faCheckDouble"
                            :class="isAddAllLoading ? 'animate-spin' : ''"
                            fixed-width
                        />
                        Add All to Wati
                    </button>

                    <!-- Confirm panel -->
                    <Transition
                        enter-active-class="transition ease-out duration-150"
                        enter-from-class="opacity-0 scale-95 translate-y-1"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="transition ease-in duration-100"
                        leave-from-class="opacity-100 scale-100 translate-y-0"
                        leave-to-class="opacity-0 scale-95 translate-y-1"
                    >
                        <div
                            v-if="showConfirmAddAll"
                            class="absolute right-0 top-0 z-10 flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 shadow-md whitespace-nowrap"
                        >
                            <span class="text-xs text-amber-800 font-medium">Add all to Wati?</span>
                            <button
                                class="rounded bg-green-600 px-2 py-0.5 text-xs font-semibold text-white hover:bg-green-700 transition-colors"
                                @click="bulkAddAll"
                            >
                                Yes
                            </button>
                            <button
                                class="rounded bg-white border border-gray-300 px-2 py-0.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors"
                                @click="showConfirmAddAll = false"
                            >
                                Cancel
                            </button>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>

        <!-- Table -->
        <Table
            ref="tableRef"
            :resource="data"
            :name="tab"
            :isCheckBox="true"
            checkboxKey="id"
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
                    class="inline-flex items-center gap-1 rounded-md border border-green-300 bg-white px-2 py-1 text-xs font-medium text-green-700 hover:bg-green-50 disabled:opacity-50 transition-colors"
                    @click="addSingle(customer.id)"
                >
                    <FontAwesomeIcon
                        :icon="loadingIds.has(customer.id) ? faSpinner : faCommentAltPlus"
                        :class="loadingIds.has(customer.id) ? 'animate-spin' : ''"
                        fixed-width
                    />
                    Add to Wati
                </button>
            </template>
        </Table>
    </div>
</template>
