<script setup lang="ts">
import { reactive, computed, watch } from "vue"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import InputText from "primevue/inputtext"
import { Select } from "primevue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faTrashAlt, faInfoCircle } from "@fal"
import { ctrans } from "@/Composables/useTrans"

interface DestinationRow {
    country_id: number | null
    postcode: string | null
}

const props = defineProps<{
    form: Record<string, any>
    fieldName: string
    options: {
        countries: Record<string, { label: string; code: string; id: number | string }>
        shops: { code: string; name: string }[]
    }
}>()

const countryOptions = computed(() =>
    Object.values(props.options?.countries ?? {}).map((country) => ({
        label: country.label,
        value: country.id,
    }))
)

const rows = reactive<DestinationRow[]>([...(props.form[props.fieldName] ?? [])])

watch(
    rows,
    () => {
        props.form[props.fieldName] = rows.map((row) => ({ country_id: row.country_id, postcode: row.postcode }))
    },
    { deep: true }
)

const addRow = () => rows.push({ country_id: null, postcode: "" })
const removeRow = (row: DestinationRow) => rows.splice(rows.indexOf(row), 1)
</script>

<template>
    <div>
        <p class="mb-2 text-sm text-gray-500">
            {{ ctrans("Delivery notes of these shops going to these destinations need every picked item put in a box before they can be set as packed, and print a packing list per box. It only applies while the switch above is on.") }}
        </p>
        <div class="mb-3 flex flex-wrap gap-1">
            <span
                v-for="shop in options?.shops ?? []"
                :key="shop.code"
                v-tooltip="shop.name"
                class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-500"
            >
                {{ shop.code }}
            </span>
        </div>

        <DataTable :value="rows" class="text-sm">
            <Column :header="ctrans('Country')" style="min-width: 10rem">
                <template #body="{ data }">
                    <Select
                        v-model="data.country_id"
                        filter
                        :options="countryOptions"
                        optionLabel="label"
                        optionValue="value"
                        :placeholder="ctrans('Any')"
                        showClear
                        class="w-full"
                    />
                </template>
            </Column>

            <Column style="min-width: 8rem">
                <template #header>
                    <span class="inline-flex items-center gap-1 font-semibold">
                        {{ ctrans("Postcode starts with") }}
                        <FontAwesomeIcon
                            :icon="faInfoCircle"
                            class="text-gray-400"
                            fixed-width
                            aria-hidden="true"
                            v-tooltip="ctrans('Not a regex, just the beginning of the postcode. Several allowed with commas: GY,JE. Spaces and case are ignored. Leave empty to match any postcode.')"
                        />
                    </span>
                </template>
                <template #body="{ data }">
                    <InputText v-model="data.postcode" :placeholder="ctrans('Any')" class="w-full font-mono" />
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
                    {{ ctrans("No destinations yet, no delivery note needs a packing list by box.") }}
                </div>
            </template>
        </DataTable>

        <button
            type="button"
            class="mt-3 inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-[--theme-color-0] border border-dashed border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
            @click="addRow"
        >
            <FontAwesomeIcon :icon="faPlus" fixed-width aria-hidden="true" />
            {{ ctrans("Add destination") }}
        </button>
    </div>
</template>
