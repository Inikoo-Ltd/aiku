<script setup lang="ts">
import { reactive, computed, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import InputText from "primevue/inputtext"
import { Select } from "primevue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faTrashAlt, faLock, faLockOpen, faInfoCircle } from "@fal"

interface PreferredShippingRow {
    id: number | null
    shipper_id: number | null
    shipper_name: string | null
    country_id: number | null
    country_name: string | null
    postcode: string | null
    important: boolean
}

const props = defineProps<{
    form: Record<string, any>
    fieldName: string
    options: {
        shippers: { id: number; name: string }[]
        countries: Record<string, { label: string; code: string; id: number | string }>
    }
}>()

const shipperOptions = computed(() =>
    (props.options?.shippers ?? []).map((shipper) => ({ label: shipper.name, value: shipper.id }))
)
const countryOptions = computed(() =>
    Object.values(props.options?.countries ?? {}).map((country) => ({
        label: country.label,
        value: country.id,
    }))
)

const rows = reactive<PreferredShippingRow[]>(
    (props.form[props.fieldName] ?? []).map((row: PreferredShippingRow) => ({ ...row }))
)

watch(
    rows,
    () => {
        props.form[props.fieldName] = rows.map((row) => ({
            id: row.id,
            shipper_id: row.shipper_id,
            country_id: row.country_id,
            postcode: row.postcode,
            important: row.important,
        }))
    },
    { deep: true }
)

const addRow = () => {
    rows.push({
        id: null,
        shipper_id: null,
        shipper_name: null,
        country_id: null,
        country_name: null,
        postcode: "",
        important: false,
    })
}

const removeRow = (index: number) => {
    rows.splice(index, 1)
}

const setImportant = (index: number, value: boolean) => {
    rows.forEach((row: PreferredShippingRow, i: number) => {
        row.important = i === index ? value : false
    })
}
</script>

<template>
    <div>
        <DataTable :value="rows" dataKey="id" class="text-sm" removableSort>
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

            <Column field="postcode" :header="trans('Postcode')" style="min-width: 8rem">
                <template #body="{ data }">
                    <InputText v-model="data.postcode" :placeholder="trans('Any')" class="w-full font-mono" />
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
                    />
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
                        @click="setImportant(index, !data.important)"
                    >
                        <FontAwesomeIcon :icon="data.important ? faLock : faLockOpen" fixed-width aria-hidden="true" />
                        {{ data.important ? trans("Locked") : trans("Flexible") }}
                    </button>
                </template>
            </Column>

            <Column style="width: 4rem">
                <template #body="{ index }">
                    <button
                        type="button"
                        class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-md transition-colors"
                        @click="removeRow(index)"
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
            @click="addRow"
        >
            <FontAwesomeIcon :icon="faPlus" fixed-width aria-hidden="true" />
            {{ trans("Add shipping rule") }}
        </button>
    </div>
</template>
