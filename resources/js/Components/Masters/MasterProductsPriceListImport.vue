<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-24 13:55
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import Select from "primevue/select"
import Checkbox from "primevue/checkbox"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faCheck, faArrowLeft, faEye, faExclamationTriangle, faArrowRight, faCopy } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { RouteParams } from "@/types/route-params"
import { buildPriceListRows, parsePriceList, type PriceListColumn, type PriceListColumnRole, type PriceListRow } from "@/Components/Masters/priceListParser"

type PriceField = "master_prices" | "master_rrps"

interface PreviewChange {
    current: number | null
    new: number
    changed: boolean
}

interface PreviewRow {
    input_code: string
    found: boolean
    duplicate: boolean
    id?: number
    code?: string
    name?: string
    units?: number
    status?: boolean
    family_code?: string | null
    in_scope?: boolean
    has_changes?: boolean
    changes?: Partial<Record<PriceField, Record<string, PreviewChange>>>
    values?: Omit<PriceListRow, "code">
}

interface Preview {
    currencies: string[]
    unknown_currencies: string[]
    rows: PreviewRow[]
}

const visible = defineModel<boolean>("visible", { default: false })

const props = defineProps<{
    currencyCodes: string[]
    majorCurrencies?: string[]
    masterProductCategoryId?: number | null
}>()

const locale = useLocaleStore()

const step = ref<"paste" | "preview">("paste")
const pastedText = ref("")
const columns = ref<PriceListColumn[]>([])
const cells = ref<string[][]>([])
const hasHeader = ref(false)
const preview = ref<Preview | null>(null)
const includedIds = ref<Record<number, boolean>>({})
const isLoadingPreview = ref(false)
const isSaving = ref(false)
const errorMessage = ref("")

const roleOptions = computed<{ value: PriceListColumnRole, label: string }[]>(() => [
    { value: "code", label: ctrans("Product code") },
    { value: "master_prices", label: ctrans("Selling price") },
    { value: "master_rrps_per_unit", label: ctrans("RRP per unit") },
    { value: "master_rrps", label: ctrans("RRP per outer") },
    { value: "ignore", label: ctrans("Ignore") },
])

const currencyOptions = computed(() => props.currencyCodes.map(code => ({ value: code, label: code })))

watch(pastedText, (text) => {
    const parsed = parsePriceList(text, props.currencyCodes)
    columns.value = parsed.columns
    cells.value = parsed.cells
    hasHeader.value = parsed.hasHeader
    errorMessage.value = ""
})

watch(visible, (isVisible) => {
    if (isVisible) {
        step.value = "paste"
        pastedText.value = ""
        preview.value = null
        errorMessage.value = ""
    }
})

const onRoleChange = (columnIndex: number, role: PriceListColumnRole) => {
    columns.value.forEach((column, index) => {
        if (index === columnIndex) {
            column.role = role
            if (role === "code" || role === "ignore") {
                column.currency = null
            }
        } else if (role === "code" && column.role === "code") {
            column.role = "ignore"
        }
    })
}

const parsedRows = computed(() => buildPriceListRows(cells.value, columns.value))

const mappingProblem = computed<string | null>(() => {
    if (!cells.value.length) {
        return null
    }
    if (!columns.value.some(column => column.role === "code")) {
        return ctrans("Choose which column holds the product code")
    }
    const priced = columns.value.filter(column => column.role !== "code" && column.role !== "ignore")
    if (!priced.length) {
        return ctrans("Choose at least one price or RRP column")
    }
    if (priced.some(column => !column.currency)) {
        return ctrans("Choose the currency of every price and RRP column")
    }
    const seen = new Set<string>()
    for (const column of priced) {
        const key = `${column.role === "master_rrps_per_unit" ? "master_rrps" : column.role}:${column.currency}`
        if (seen.has(key)) {
            return ctrans("Two columns set the same field in :currency", { currency: column.currency ?? "" })
        }
        seen.add(key)
    }

    return null
})

const previewRoute = () => props.masterProductCategoryId
    ? route("grp.json.master_product_category.prices_from_list.preview", { masterProductCategory: props.masterProductCategoryId })
    : route("grp.json.master_shop.prices_from_list.preview", { masterShop: (route().params as RouteParams).masterShop })

const loadPreview = async () => {
    if (mappingProblem.value || !parsedRows.value.length) {
        return
    }

    isLoadingPreview.value = true
    errorMessage.value = ""
    try {
        const { data } = await axios.post<Preview>(previewRoute(), { rows: parsedRows.value })
        preview.value = data
        includedIds.value = Object.fromEntries(
            data.rows.filter(row => row.found && row.has_changes).map(row => [row.id, true])
        )
        step.value = "preview"
    } catch (error: any) {
        errorMessage.value = error?.response?.data?.message ?? ctrans("The price list could not be checked, please try again.")
    } finally {
        isLoadingPreview.value = false
    }
}

const previewFields = computed<{ field: PriceField, currency: string, label: string }[]>(() => {
    const fields: { field: PriceField, currency: string, label: string }[] = []
    for (const field of ["master_prices", "master_rrps"] as PriceField[]) {
        for (const currency of preview.value?.currencies ?? []) {
            if (preview.value?.rows.some(row => row.changes?.[field]?.[currency])) {
                fields.push({ field, currency, label: `${field === "master_prices" ? ctrans("Price") : ctrans("RRP")} ${currency}` })
            }
        }
    }

    return fields
})

const notFoundRows = computed(() => preview.value?.rows.filter(row => !row.found) ?? [])
const outOfScopeRows = computed(() => preview.value?.rows.filter(row => row.found && !row.in_scope) ?? [])
const unchangedRows = computed(() => preview.value?.rows.filter(row => row.found && !row.has_changes) ?? [])
const duplicateCodes = computed(() => [...new Set(preview.value?.rows.filter(row => row.duplicate).map(row => row.input_code) ?? [])])

const rowsToSubmit = computed(() => {
    const rowsById = new Map<number, PreviewRow>()
    for (const row of preview.value?.rows ?? []) {
        if (row.found && row.id && includedIds.value[row.id]) {
            rowsById.set(row.id, row)
        }
    }

    return [...rowsById.values()]
})

const pastedMinorCurrencies = computed(() =>
    (preview.value?.currencies ?? []).filter(code => props.majorCurrencies && !props.majorCurrencies.includes(code))
)

const leaveOutOfScopeRows = () => {
    outOfScopeRows.value.forEach(row => {
        if (row.id) {
            includedIds.value[row.id] = false
        }
    })
}

const submit = () => {
    if (!rowsToSubmit.value.length) {
        return
    }

    isSaving.value = true
    errorMessage.value = ""
    router.patch(
        route("grp.models.master_asset.prices.update_from_list"),
        {
            rows: rowsToSubmit.value.map(row => ({
                id: row.id,
                master_prices: row.values?.master_prices ?? {},
                master_rrps: row.values?.master_rrps ?? {},
                master_rrps_per_unit: row.values?.master_rrps_per_unit ?? {},
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                visible.value = false
                router.reload({ only: ["pricing"] })
            },
            onError: (errors: Record<string, string>) => {
                errorMessage.value = Object.values(errors)[0] ?? ctrans("The prices could not be saved, please try again.")
            },
            onFinish: () => {
                isSaving.value = false
            },
        }
    )
}

const exampleCurrencies = computed<string[]>(() => {
    const preferred = (props.majorCurrencies?.length ? props.majorCurrencies : props.currencyCodes).slice(0, 2)

    return preferred.length ? preferred : ["EUR", "GBP"]
})

const exampleRows = computed<string[][]>(() => [
    ["Code", ...exampleCurrencies.value.map(code => `Selling Price ${code}`), ...exampleCurrencies.value.map(code => `RRP/Unit ${code}`)],
    ["ABC-01", ...exampleCurrencies.value.map((_, index) => (4.75 - index * 0.75).toFixed(2)), ...exampleCurrencies.value.map((_, index) => (13 - index * 3).toFixed(2))],
    ["ABC-02", ...exampleCurrencies.value.map((_, index) => (6.5 - index).toFixed(2)), ...exampleCurrencies.value.map((_, index) => (16 - index * 2.5).toFixed(2))],
])

const isExampleCopied = ref(false)

const copyExample = async () => {
    await navigator.clipboard.writeText(exampleRows.value.map(row => row.join("\t")).join("\n"))
    isExampleCopied.value = true
    setTimeout(() => isExampleCopied.value = false, 2000)
}

const formatAmount = (currency: string, amount: number | null) =>
    amount === null ? "—" : locale.currencyFormat(currency, amount)
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :header="ctrans('Update prices from a list')"
        :style="{ width: step === 'preview' ? '72rem' : '52rem', maxWidth: 'calc(100vw - 2rem)' }"
        :draggable="false"
    >
        <div v-if="step === 'paste'" class="space-y-4">
            <div class="space-y-1">
                <label class="text-sm font-medium text-gray-700">
                    {{ ctrans("Price list") }} <span class="text-red-500">*</span>
                </label>
                <Textarea
                    v-model="pastedText"
                    rows="8"
                    class="w-full font-mono text-xs"
                    :placeholder="ctrans('Paste the rows from the spreadsheet, with the header row: Code, Selling Price EUR, RRP/Unit GBP …')"
                />
                <p class="text-xs text-gray-500">
                    {{ ctrans("Columns are recognised by their header. Check below what each column sets and change it when the header is not recognised.") }}
                </p>
            </div>

            <div v-if="!columns.length" class="space-y-2 rounded border border-gray-200 bg-gray-50 p-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="text-xs font-semibold text-gray-700">{{ ctrans("Example") }}</div>
                    <Button type="tertiary" size="xs" :label="isExampleCopied ? ctrans('Copied') : ctrans('Copy example')" @click="copyExample">
                        <template #icon>
                            <FontAwesomeIcon :icon="isExampleCopied ? faCheck : faCopy" :class="isExampleCopied ? 'text-green-600' : ''" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
                <div class="overflow-x-auto rounded border border-gray-200 bg-white">
                    <table class="min-w-full font-mono text-xs">
                        <thead class="whitespace-nowrap bg-gray-50 text-gray-600">
                            <tr>
                                <th v-for="(cell, index) in exampleRows[0]" :key="index" class="px-2 py-1 text-left font-medium">{{ cell }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <tr v-for="(row, rowIndex) in exampleRows.slice(1)" :key="rowIndex">
                                <td v-for="(cell, index) in row" :key="index" class="whitespace-nowrap px-2 py-1" :class="index ? 'text-right tabular-nums' : ''">{{ cell }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul class="list-disc space-y-0.5 pl-4 text-xs text-gray-500">
                    <li>{{ ctrans("Copy the rows straight from Excel or Google Sheets, header row included. Columns separated by tabs, semicolons or commas also work.") }}</li>
                    <li>{{ ctrans("Product code: a header with Code, SKU or Ref.") }}</li>
                    <li>{{ ctrans("Selling price: a header with Price, Selling or Wholesale and the currency.") }}</li>
                    <li>{{ ctrans("RRP: a header with RRP or Retail and the currency; add Unit (RRP/Unit) when the RRP is per unit.") }}</li>
                    <li>{{ ctrans("Currency as a code (EUR, GBP) or a symbol (€, £). Other columns, like the name, are ignored.") }}</li>
                    <li>{{ ctrans("Leave out the currencies or columns you do not want to change.") }}</li>
                </ul>
            </div>

            <div v-if="columns.length" class="space-y-2">
                <div class="text-sm font-medium text-gray-700">{{ ctrans("Columns") }}</div>
                <div class="overflow-x-auto rounded border border-gray-200">
                    <table class="min-w-full text-xs">
                        <thead class="whitespace-nowrap bg-gray-50 text-gray-600">
                            <tr>
                                <th v-for="(column, index) in columns" :key="index" class="px-2 py-2 text-left align-top font-medium">
                                    <div class="mb-1 truncate" :title="column.header">
                                        {{ column.header || ctrans("Column :number", { number: String(index + 1) }) }}
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <Select
                                            :modelValue="column.role"
                                            :options="roleOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            size="small"
                                            class="w-40"
                                            @update:modelValue="(role: PriceListColumnRole) => onRoleChange(index, role)"
                                        />
                                        <Select
                                            v-if="column.role !== 'code' && column.role !== 'ignore'"
                                            v-model="column.currency"
                                            :options="currencyOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            :placeholder="ctrans('Currency')"
                                            size="small"
                                            class="w-40"
                                            :invalid="!column.currency"
                                        />
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(row, rowIndex) in cells.slice(0, 5)" :key="rowIndex">
                                <td
                                    v-for="(column, index) in columns"
                                    :key="index"
                                    class="whitespace-nowrap px-2 py-1"
                                    :class="column.role === 'ignore' ? 'text-gray-300' : 'text-gray-700'"
                                >
                                    {{ row[index] }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500">
                    {{ ctrans(":count products in the list", { count: String(parsedRows.length) }) }}<template v-if="cells.length > 5">, {{ ctrans("first 5 shown") }}</template>
                </p>
                <p v-if="mappingProblem" class="text-xs text-red-500">{{ mappingProblem }}</p>
            </div>
        </div>

        <div v-else-if="preview" class="space-y-3">
            <div v-if="outOfScopeRows.length" class="flex items-start gap-2 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5" fixed-width aria-hidden="true" />
                <div class="flex-1">
                    {{ ctrans(":count products are not on this page: :codes. They will be changed too unless you untick them.", { count: String(outOfScopeRows.length), codes: outOfScopeRows.map(row => row.code).join(", ") }) }}
                </div>
                <button type="button" class="shrink-0 font-semibold underline hover:text-amber-900" @click="leaveOutOfScopeRows">
                    {{ ctrans("Leave them out") }}
                </button>
            </div>
            <div v-if="notFoundRows.length" class="flex items-start gap-2 rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5" fixed-width aria-hidden="true" />
                {{ ctrans("Not found in this master shop, skipped: :codes", { codes: notFoundRows.map(row => row.input_code).join(", ") }) }}
            </div>
            <div v-if="duplicateCodes.length" class="flex items-start gap-2 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5" fixed-width aria-hidden="true" />
                {{ ctrans("These codes are in the list more than once, the last row is used: :codes", { codes: duplicateCodes.join(", ") }) }}
            </div>
            <div v-if="preview.unknown_currencies.length" class="flex items-start gap-2 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5" fixed-width aria-hidden="true" />
                {{ ctrans("This master shop has no prices in :currencies, those columns are ignored", { currencies: preview.unknown_currencies.join(", ") }) }}
            </div>

            <div class="max-h-[55vh] overflow-auto rounded border border-gray-200">
                <table class="min-w-full text-xs">
                    <thead class="sticky top-0 z-10 whitespace-nowrap bg-gray-50 text-gray-600">
                        <tr>
                            <th class="w-8 px-2 py-2"></th>
                            <th class="px-2 py-2 text-left font-medium">{{ ctrans("Code") }}</th>
                            <th class="px-2 py-2 text-left font-medium">{{ ctrans("Name") }}</th>
                            <th v-for="column in previewFields" :key="`${column.field}-${column.currency}`" class="px-2 py-2 text-right font-medium">
                                {{ column.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="(row, rowIndex) in preview.rows"
                            :key="rowIndex"
                            :class="[
                                !row.found || (row.id && !includedIds[row.id]) ? 'bg-gray-50 text-gray-400' : 'text-gray-700',
                                row.found && !row.in_scope && row.id && includedIds[row.id] ? 'bg-amber-50/60' : '',
                            ]"
                        >
                            <td class="px-2 py-1.5 text-center">
                                <Checkbox v-if="row.found && row.id" v-model="includedIds[row.id]" binary :disabled="!row.has_changes" />
                            </td>
                            <td class="whitespace-nowrap px-2 py-1.5 font-medium">
                                {{ row.code ?? row.input_code }}
                            </td>
                            <td class="px-2 py-1.5">
                                <template v-if="!row.found">
                                    <span class="text-red-500">{{ ctrans("Not found") }}</span>
                                </template>
                                <template v-else>
                                    <span>{{ row.name }}</span>
                                    <span v-if="!row.in_scope" class="ml-1 whitespace-nowrap rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800">
                                        {{ ctrans("Not on this page") }}<template v-if="row.family_code"> · {{ row.family_code }}</template>
                                    </span>
                                    <span v-if="!row.status" class="ml-1 whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600">
                                        {{ ctrans("Inactive") }}
                                    </span>
                                    <span v-if="!row.has_changes" class="ml-1 whitespace-nowrap text-[10px] text-gray-400">
                                        {{ ctrans("No changes") }}
                                    </span>
                                </template>
                            </td>
                            <td v-for="column in previewFields" :key="`${column.field}-${column.currency}`" class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums">
                                <template v-if="row.changes?.[column.field]?.[column.currency]">
                                    <template v-if="row.changes[column.field]![column.currency].changed">
                                        <span class="text-gray-400 line-through">{{ formatAmount(column.currency, row.changes[column.field]![column.currency].current) }}</span>
                                        <FontAwesomeIcon :icon="faArrowRight" class="mx-1 text-gray-300" fixed-width aria-hidden="true" />
                                        <span class="font-semibold">{{ formatAmount(column.currency, row.changes[column.field]![column.currency].new) }}</span>
                                    </template>
                                    <span v-else class="text-gray-400">{{ formatAmount(column.currency, row.changes[column.field]![column.currency].new) }}</span>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="space-y-1 text-xs text-gray-500">
                <p>
                    {{ ctrans(":selected of :total products will be updated", { selected: String(rowsToSubmit.length), total: String(preview.rows.filter(row => row.found).length) }) }}<template v-if="unchangedRows.length">, {{ ctrans(":count already have these prices", { count: String(unchangedRows.length) }) }}</template>
                </p>
                <p v-if="pastedMinorCurrencies.length">
                    {{ ctrans("Prices in :currencies are set by hand and stop following the exchange rate.", { currencies: pastedMinorCurrencies.join(", ") }) }}
                </p>
            </div>
        </div>

        <p v-if="errorMessage" class="mt-3 text-xs text-red-500">{{ errorMessage }}</p>

        <template #footer>
            <div class="flex w-full gap-2">
                <Button v-if="step === 'paste'" type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button v-else type="tertiary" class="shrink-0" :label="ctrans('Back')" :disabled="isSaving" @click="step = 'paste'">
                    <template #icon>
                        <FontAwesomeIcon :icon="faArrowLeft" fixed-width aria-hidden="true" />
                    </template>
                </Button>

                <Button
                    v-if="step === 'paste'"
                    type="secondary"
                    full
                    :label="ctrans('Preview changes')"
                    :disabled="!!mappingProblem || !parsedRows.length || isLoadingPreview"
                    @click="loadPreview"
                >
                    <template #icon>
                        <LoadingIcon v-if="isLoadingPreview" />
                        <FontAwesomeIcon v-else :icon="faEye" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button
                    v-else
                    type="primary"
                    full
                    :label="ctrans('Update :count products', { count: String(rowsToSubmit.length) })"
                    :loading="isSaving"
                    :disabled="!rowsToSubmit.length"
                    @click="submit"
                >
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
