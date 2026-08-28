<script setup lang="ts">
import { reactive, ref, computed, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import InputText from "primevue/inputtext"
import { Select } from "primevue"
import Modal from "@/Components/Utils/Modal.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faTrashAlt, faLock, faLockOpen, faInfoCircle, faVial, faCheck, faTimes } from "@fal"

interface PreferredShippingRow {
    id: number | null
    shipper_id: number | null
    shipper_name: string | null
    country_id: number | null
    country_name: string | null
    postcode: string | null
    important: boolean
    trade_scope: "b2b" | "b2c"
}

const props = defineProps<{
    form: Record<string, any>
    fieldName: string
    options: {
        shippers: { id: number; name: string; code?: string; api_shipper?: string | null }[]
        countries: Record<string, { label: string; code: string; id: number | string }>
        scope_shops?: Record<string, { code: string; name: string }[]>
    }
}>()

const shipperOptions = computed(() =>
    (props.options?.shippers ?? []).map((shipper) => ({
        label: `${shipper.api_shipper ? "API · " : ""}${shipper.name}${shipper.code ? ` (${shipper.code})` : ""}`,
        is_api: !!shipper.api_shipper,
        value: shipper.id,
    }))
)
const countryOptions = computed(() =>
    Object.values(props.options?.countries ?? {}).map((country) => ({
        label: country.label,
        value: country.id,
    }))
)

const rows = reactive<PreferredShippingRow[]>(
    (props.form[props.fieldName] ?? []).map((row: PreferredShippingRow) => ({ trade_scope: "b2b", ...row }))
)

// Two independent rule sets: some carriers price and label wholesale (b2b) and
// consumer (b2c/dropshipping) traffic differently, so the sets never mix.
const scopes = [
    { value: "b2b" as const, label: trans("B2B — wholesale shops") },
    { value: "b2c" as const, label: trans("B2C — dropshipping & e-commerce shops") },
]

// A rule with no country and no postcode is the scope's catch-all: it loses to any
// specific rule and to the customer's choice, which is exactly what a default is.
// The UI surfaces it as the "Default shipper" select.
const isDefaultRow = (row: PreferredShippingRow) => !row.country_id && !row.postcode

// Tracked by reference, not by shape: a freshly added, still-empty rule row is also
// wildcard-shaped and must stay in the table while it is being edited.
const defaultRowByScope = reactive<{ b2b: PreferredShippingRow | null; b2c: PreferredShippingRow | null }>({
    b2b: rows.find((row) => row.trade_scope === "b2b" && isDefaultRow(row)) ?? null,
    b2c: rows.find((row) => row.trade_scope === "b2c" && isDefaultRow(row)) ?? null,
})

const rowsForScope = (scope: "b2b" | "b2c") => rows.filter((row) => row.trade_scope === scope && row !== defaultRowByScope[scope])

const defaultShipperId = (scope: "b2b" | "b2c") => defaultRowByScope[scope]?.shipper_id ?? null

const setDefaultShipper = (scope: "b2b" | "b2c", shipperId: number | null) => {
    const existing = defaultRowByScope[scope]
    if (!shipperId) {
        if (existing) {
            rows.splice(rows.indexOf(existing), 1)
            defaultRowByScope[scope] = null
        }
        return
    }
    if (existing) {
        existing.shipper_id = shipperId
    } else {
        const row: PreferredShippingRow = {
            id: null,
            shipper_id: shipperId,
            shipper_name: null,
            country_id: null,
            country_name: null,
            postcode: "",
            important: false,
            trade_scope: scope,
        }
        rows.push(row)
        defaultRowByScope[scope] = row
    }
}

watch(
    rows,
    () => {
        props.form[props.fieldName] = rows.map((row) => ({
            id: row.id,
            shipper_id: row.shipper_id,
            country_id: row.country_id,
            postcode: row.postcode,
            important: row.important,
            trade_scope: row.trade_scope,
        }))
    },
    { deep: true }
)

const addRow = (scope: "b2b" | "b2c") => {
    rows.push({
        id: null,
        shipper_id: null,
        shipper_name: null,
        country_id: null,
        country_name: null,
        postcode: "",
        important: false,
        trade_scope: scope,
    })
}

const removeRow = (row: PreferredShippingRow) => {
    rows.splice(rows.indexOf(row), 1)
}

const setImportant = (target: PreferredShippingRow, value: boolean) => {
    rows.forEach((row: PreferredShippingRow) => {
        if (row.trade_scope === target.trade_scope) {
            row.important = row === target ? value : false
        }
    })
}

// Section: postcode tester
// Mirrors the backend prefix match: both sides uppercased with spaces stripped,
// then str_starts_with(address postcode, rule postcode).
const normalisePostcode = (value?: string | null) => (value ?? "").toUpperCase().replace(/\s+/g, "")

const isPostcodeTestOpen = ref(false)
const postcodeTestRow = ref<PreferredShippingRow | null>(null)
const postcodeTestInput = ref("")

const postcodeTestMatched = computed(() => {
    if (!postcodeTestInput.value) return null
    const prefixes = normalisePostcode(postcodeTestRow.value?.postcode).split(",").filter(Boolean)
    const postcode = normalisePostcode(postcodeTestInput.value)
    return !prefixes.length || prefixes.some((prefix) => postcode.startsWith(prefix))
})

const openPostcodeTest = (row: PreferredShippingRow) => {
    postcodeTestRow.value = row
    postcodeTestInput.value = ""
    isPostcodeTestOpen.value = true
}
</script>

<template>
    <div class="space-y-8">
        <div v-for="scope in scopes" :key="scope.value">
        <h3 class="mb-2 flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm font-semibold text-gray-700">
            {{ scope.label }}
            <span class="inline-flex flex-wrap gap-1">
                <span
                    v-for="shop in options?.scope_shops?.[scope.value] ?? []"
                    :key="shop.code"
                    v-tooltip="shop.name"
                    class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] font-normal text-gray-500"
                >
                    {{ shop.code }}
                </span>
            </span>
        </h3>
        <div class="mb-3 flex items-center gap-2">
            <span class="text-sm text-gray-500">{{ trans("Default shipper") }}</span>
            <FontAwesomeIcon
                :icon="faInfoCircle"
                class="text-gray-400"
                fixed-width
                aria-hidden="true"
                v-tooltip="trans('Used when no rule below matches and the customer has not chosen a shipper. Leave empty for no default.')"
            />
            <Select
                :modelValue="defaultShipperId(scope.value)"
                @update:modelValue="(value) => setDefaultShipper(scope.value, value)"
                filter
                :options="shipperOptions"
                optionLabel="label"
                optionValue="value"
                :placeholder="trans('No default')"
                showClear
                class="w-full md:w-96"
            >
                <template #option="{ option }">
                    <span class="inline-flex items-center gap-2">
                        <span
                            v-if="option.is_api"
                            class="rounded bg-indigo-600 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white"
                        >
                            API
                        </span>
                        {{ option.label.replace(/^API · /, "") }}
                    </span>
                </template>
            </Select>
        </div>
        <DataTable :value="rowsForScope(scope.value)" class="text-sm" removableSort>
            <Column field="country_name" :header="trans('Country')" style="min-width: 10rem">
                <template #body="{ data }">
                    <Select
                        v-model="data.country_id"
                        filter
                        :options="countryOptions"
                        optionLabel="label"
                        optionValue="value"
                        :placeholder="trans('Any')"
                        showClear
                        class="w-full"
                    />
                </template>
            </Column>

            <Column field="postcode" style="min-width: 8rem">
                <template #header>
                    <span class="inline-flex items-center gap-1 font-semibold">
                        {{ trans('Postcode starts with') }}
                        <FontAwesomeIcon
                            :icon="faInfoCircle"
                            class="text-gray-400"
                            fixed-width
                            aria-hidden="true"
                            v-tooltip="trans('Not a regex, just the beginning of the postcode. BT matches BT1 7AB, BT29, etc. Several allowed with commas: 91,93,67. Spaces and case are ignored. Leave empty to match any postcode.')"
                        />
                    </span>
                </template>
                <template #body="{ data }">
                    <div class="flex items-center gap-2">
                        <InputText v-model="data.postcode" :placeholder="trans('Any')" class="w-full font-mono" />
                        <button
                            v-tooltip="trans('Test against a postcode')"
                            type="button"
                            :disabled="!data.postcode"
                            class="flex-none p-2 text-[--theme-color-0] hover:bg-gray-50 rounded-md transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            @click="openPostcodeTest(data)"
                        >
                            <FontAwesomeIcon :icon="faVial" fixed-width aria-hidden="true" />
                        </button>
                    </div>
                </template>
            </Column>

            <Column field="shipper_name" :header="trans('Shipper')" style="min-width: 10rem">
                <template #body="{ data }">
                    <Select
                        v-model="data.shipper_id"
                        filter
                        :options="shipperOptions"
                        optionLabel="label"
                        optionValue="value"
                        :placeholder="trans('Select shipper')"
                        class="w-full"
                    >
                        <template #option="{ option }">
                            <span class="inline-flex items-center gap-2">
                                <span
                                    v-if="option.is_api"
                                    class="rounded bg-indigo-600 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white"
                                >
                                    API
                                </span>
                                {{ option.label.replace(/^API · /, "") }}
                            </span>
                        </template>
                    </Select>
                </template>
            </Column>

            <Column field="important" style="width: 6rem">
                <template #header>
                    <span class="inline-flex items-center gap-1">
                        {{ trans('Lock') }}
                        <FontAwesomeIcon
                            :icon="faInfoCircle"
                            class="text-gray-400"
                            fixed-width
                            aria-hidden="true"
                            v-tooltip="trans('Locked: this shipper is forced and the packer cannot change it. Flexible: it is only preselected and the packer can pick another shipper.')"
                        />
                    </span>
                </template>
                <template #body="{ data, index }">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium border rounded-full cursor-pointer transition-colors"
                        :class="data.important
                            ? 'text-indigo-700 bg-indigo-50 border-indigo-300 hover:bg-indigo-100'
                            : 'text-gray-500 bg-white border-gray-300 hover:border-gray-400 hover:bg-gray-50'"
                        :aria-pressed="data.important"
                        v-tooltip="data.important ? trans('Forced, packer cannot change it') : trans('Preselected only, packer can change it')"
                        @click="setImportant(data, !data.important)"
                    >
                        <FontAwesomeIcon :icon="data.important ? faLock : faLockOpen" fixed-width aria-hidden="true" />
                        {{ data.important ? trans("Locked") : trans("Flexible") }}
                    </button>
                </template>
            </Column>

            <Column style="width: 4rem">
                <template #body="{ data }">
                    <button
                        type="button"
                        class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-md transition-colors"
                        @click="removeRow(data)"
                    >
                        <FontAwesomeIcon :icon="faTrashAlt" fixed-width aria-hidden="true" />
                    </button>
                </template>
            </Column>

            <template #empty>
                <div class="text-center text-gray-400 py-4">
                    {{ trans("No preferred shipping rules yet, orders will use the default shipper.") }}
                </div>
            </template>
        </DataTable>

        <button
            type="button"
            class="mt-3 inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-[--theme-color-0] border border-dashed border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
            @click="addRow(scope.value)"
        >
            <FontAwesomeIcon :icon="faPlus" fixed-width aria-hidden="true" />
            {{ trans("Add shipping rule") }}
        </button>
        </div>

        <Modal :isOpen="isPostcodeTestOpen" @onClose="isPostcodeTestOpen = false" width="w-full max-w-md">
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">
                    {{ trans("Test postcode rule") }}
                </h3>

                <div class="text-sm text-gray-500">
                    {{ trans("Rule: postcode starts with") }}
                    <span class="font-mono text-gray-700">{{ postcodeTestRow?.postcode }}</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ trans("Postcode to test") }}
                    </label>
                    <InputText
                        v-model="postcodeTestInput"
                        autofocus
                        :placeholder="trans('Type a postcode')"
                        class="w-full font-mono"
                    />
                </div>

                <div
                    v-if="postcodeTestMatched === true"
                    class="rounded-md bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-700"
                >
                    <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                    {{ trans("Matches, this rule applies to that postcode.") }}
                </div>
                <div
                    v-else-if="postcodeTestMatched === false"
                    class="rounded-md bg-gray-50 border border-gray-200 px-3 py-2 text-sm text-gray-600"
                >
                    <FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
                    {{ trans("No match, this rule would not apply.") }}
                </div>
            </div>
        </Modal>
    </div>
</template>
