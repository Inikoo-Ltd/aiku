<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import DatePicker from "primevue/datepicker"
import { faCreditCard, faFileDownload } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { capitalize } from "@/Composables/capitalize"
import { ref } from "vue"

library.add(faCreditCard, faFileDownload)

const props = defineProps<{
    data: object
    title: string
    pageHead: object
    before_date?: string
}>()

const beforeDate = ref<Date | null>(props.before_date ? new Date(props.before_date) : null)

const applyFilter = () => {
    if (!beforeDate.value) return
    const formatted = beforeDate.value.toISOString().split("T")[0]
    router.get(route(route().current(), route().params), { before_date: formatted }, {
        preserveState: true,
        preserveScroll: true,
    })
}

const exportExcel = () => {
    if (!props.before_date) return
    window.location.href = route('grp.org.reports.customer-credit.export', {
        ...route().params,
        before_date: props.before_date,
        type: 'xlsx',
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 py-4">
        <div class="flex items-end gap-4 mb-6">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-gray-700">
                    {{ $t("Before Date") }}
                    <span class="text-red-500">*</span>
                </label>
                <DatePicker
                    v-model="beforeDate"
                    dateFormat="yy-mm-dd"
                    :showIcon="true"
                    placeholder="Select date"
                />
            </div>
            <Button
                @click="applyFilter"
                :disabled="!beforeDate"
                :style="'primary'"
                label="Apply"
            />
            <Button
                v-if="before_date"
                @click="exportExcel"
                :style="'secondary'"
                icon="fal fa-file-download"
                label="Export Excel"
            />
        </div>

        <div v-if="!before_date" class="rounded-md bg-yellow-50 border border-yellow-200 px-4 py-3 text-sm text-yellow-800">
            {{ $t("Please select a date to view customer credit balances.") }}
        </div>

        <Table v-else :resource="data" class="mt-2">
            <template #cell(latest_transaction_date)="{ item }">
                <span class="text-sm text-gray-700">
                    {{ item.latest_transaction_date ? new Date(item.latest_transaction_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-' }}
                </span>
            </template>

            <template #cell(reference)="{ item }">
                <span class="font-mono text-sm font-medium text-gray-900">
                    {{ item.reference }}
                </span>
            </template>

            <template #cell(name)="{ item }">
                <span class="text-sm text-gray-900">
                    {{ item.name }}
                </span>
            </template>

            <template #cell(email)="{ item }">
                <span class="text-sm text-gray-500">
                    {{ item.email }}
                </span>
            </template>

            <template #cell(shop_code)="{ item }">
                <span class="font-mono text-xs text-gray-700">
                    {{ item.shop_code }}
                </span>
            </template>

            <template #cell(credit_balance)="{ item }">
                <span class="text-sm font-medium text-gray-900">
                    {{ item.currency_symbol }}{{ item.credit_balance }}
                </span>
            </template>
        </Table>
    </div>
</template>
