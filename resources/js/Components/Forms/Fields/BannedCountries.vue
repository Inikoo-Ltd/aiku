<script setup lang="ts">
import { ref, reactive, computed, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import Multiselect from "@vueform/multiselect"
import InputText from "primevue/inputtext"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faTrashAlt, faLock } from "@fal"
import { get } from "lodash-es"
import { Select } from "primevue"
import InformationIcon from "@/Components/Utils/InformationIcon.vue"

interface BannedCountryRow {
    country: string | null
    postcode: string | null
    billing: boolean
    delivery: boolean
    read_only: boolean
}

const props = defineProps<{
    form: Record<string, any>
    fieldName: string
    options: Record<string, { label: string; code: string; id: number | string }>
    fieldData?: {
        placeholder?: string
        information?: string
        required?: boolean
    }
}>()

// The country select uses the country code as value to stay consistent with the
// stored shape (form[fieldName] is keyed by country code).
const countryOptions = computed(() =>
    Object.values(props.options ?? {}).map((option) => ({
        label: option.label,
        value: option.code,
    }))
)

// Exclude countries already chosen in other rows so each country can be picked once.
const getCountryOptions = (currentRow: BannedCountryRow) => {
    const usedCodes = rows
        .filter((row) => row !== currentRow && row.country)
        .map((row) => row.country)

    return countryOptions.value.filter((option) => !usedCodes.includes(option.value))
}

const buildRows = (): BannedCountryRow[] => {
    const stored = props.form[props.fieldName]

    if (!stored || Array.isArray(stored)) {
        return []
    }

    return Object.entries(stored).map(([country, value]: [string, any]) => ({
        country,
        postcode: value?.postcode ?? "",
        billing: !!value?.billing,
        delivery: !!value?.delivery,
        read_only: !!value?.read_only,
    }))
}

const rows = reactive<BannedCountryRow[]>(buildRows())

// TODO: not yet persisted, the database location for this flag is undecided.
const followOrganisation = ref(false)

const isDisabled = computed(() => followOrganisation.value)

const syncForm = () => {
    const next: Record<string, any> = {}

    rows.forEach((row) => {
        if (!row.country) {
            return
        }

        next[row.country] = {
            postcode: row.postcode || null,
            billing: row.billing,
            delivery: row.delivery,
            ...(row.read_only ? { read_only: true } : {}),
        }
    })

    props.form[props.fieldName] = next
}

watch(rows, syncForm, { deep: true })

const addRow = () => {
    rows.push({
        country: null,
        postcode: "",
        billing: false,
        delivery: true,
        read_only: false,
    })
}

const removeRow = (index: number) => {
    rows.splice(index, 1)
}

// At least one of billing/delivery must stay true. Reverting the change the user
// just made is the least surprising behaviour.
const onFlagChange = (row: BannedCountryRow, flag: "billing" | "delivery") => {
    if (!row.billing && !row.delivery) {
        row[flag] = true
    }
}
</script>

<template>
    <div>
        <label
            class="flex items-center gap-2 mb-4 cursor-pointer select-none w-fit"
            :class="{ 'opacity-100': true }"
        >
            <input
                v-model="followOrganisation"
                type="checkbox"
                class="h-5 w-5 rounded cursor-pointer border-gray-300 hover:border-[--theme-color-0] text-[--theme-color-0] focus:ring-[--theme-color-0]"
            />
            <span class="text-sm font-medium text-gray-700">
                {{ ctrans("Follow Banned Countries from Organisation") }}
            </span>
        </label>

        <div :class="{ 'opacity-50 pointer-events-none': isDisabled }">
            <DataTable :value="rows" dataKey="country" class="text-sm">
                <Column :header="trans('Country')" style="min-width: 16rem">
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <FontAwesomeIcon
                                v-if="data.read_only"
                                v-tooltip="trans('Inherited from Organisation')"
                                :icon="faLock"
                                class="text-gray-400"
                                fixed-width
                                aria-hidden="true"
                            />
                            <!-- <Select
                                v-model="data.country"
                                :options="countryOptions"
                                :filter="true"
                                :disabled="data.read_only || isDisabled"
                                :placeholder="fieldData?.placeholder ?? trans('Select country')"
                                class="w-full"
                            /> -->
                            <Select v-model="data.country"
                                filter
                                :options="getCountryOptions(data)"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select a country"
                                :disabled="data.read_only || isDisabled"
                                filterPlaceholder="Type to search country"
                                class="w-full md:w-56"
                            />
                        </div>
                    </template>
                </Column>

                <Column :header="trans('Postcode (regex)')" style="min-width: 12rem">
                    <template #body="{ data }">
                        <InputText
                            v-model="data.postcode"
                            :disabled="data.read_only || isDisabled"
                            placeholder="/^2/"
                            class="w-full font-mono"
                        />
                    </template>
                </Column>

                <Column :header="trans('Billing / Delivery')" style="min-width: 12rem">
                    <template #body="{ data }">
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="data.billing"
                                    type="checkbox"
                                    :disabled="data.read_only || isDisabled"
                                    class="h-5 w-5 rounded cursor-pointer border-gray-300 hover:border-[--theme-color-0] text-[--theme-color-0] focus:ring-[--theme-color-0]"
                                    @change="onFlagChange(data, 'billing')"
                                />
                                <span class="whitespace-nowrap">{{ ctrans("Billing") }} <InformationIcon :information="ctrans('If checked, billing location from the postcode will be excluded')" /></span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="data.delivery"
                                    type="checkbox"
                                    :disabled="data.read_only || isDisabled"
                                    class="h-5 w-5 rounded cursor-pointer border-gray-300 hover:border-[--theme-color-0] text-[--theme-color-0] focus:ring-[--theme-color-0]"
                                    @change="onFlagChange(data, 'delivery')"
                                />
                                <span class="whitespace-nowrap">{{ ctrans("Delivery") }} <InformationIcon :information="ctrans('If active, delivery location from the postcode will be excluded')" /></span>
                            </label>
                        </div>
                    </template>
                </Column>

                <Column style="width: 4rem">
                    <template #body="{ data, index }">
                        <button
                            v-if="!data.read_only"
                            type="button"
                            :disabled="isDisabled"
                            class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-md transition-colors"
                            @click="removeRow(index)"
                        >
                            <FontAwesomeIcon :icon="faTrashAlt" fixed-width aria-hidden="true" />
                        </button>
                    </template>
                </Column>

                <template #empty>
                    <div class="text-center text-gray-400 py-4">
                        {{ trans("No banned countries yet") }}
                    </div>
                </template>
            </DataTable>

            <button
                type="button"
                :disabled="isDisabled"
                class="mt-3 inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-[--theme-color-0] border border-dashed border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                @click="addRow"
            >
                <FontAwesomeIcon :icon="faPlus" fixed-width aria-hidden="true" />
                {{ trans("Add country") }}
            </button>
        </div>

        <p
            v-if="get(form, ['errors', fieldName])"
            class="mt-2 text-sm text-red-600"
            :id="`${fieldName}-error`"
        >
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>

<style src="@vueform/multiselect/themes/default.css"></style>
