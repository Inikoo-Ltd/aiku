<script setup lang="ts">
import { reactive, watchEffect } from "vue"
import { trans } from "laravel-vue-i18n"
import { faPlus, faTrash } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faPlus, faTrash)

type FieldConfig = {
    key: string
    placeholder?: string
    label?: string
}

type RowData = Record<string, string> & { _key: number }

let _counter = 0
const nextKey = () => ++_counter

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: {
        fields?: FieldConfig[]
        addLabel?: string
    }
}>()

const fields = props.fieldData?.fields ?? [{ key: "value", placeholder: "Value" }]

const toEmptyRow = (): RowData => {
    const row: RowData = { _key: nextKey() }
    for (const f of fields) row[f.key] = ""
    return row
}

const initial: Record<string, string>[] = Array.isArray(props.form[props.fieldName])
    ? props.form[props.fieldName]
    : []

const rows = reactive<RowData[]>(
    initial.map((d) => {
        const row: RowData = { _key: nextKey() }
        for (const f of fields) row[f.key] = d[f.key] ?? ""
        return row
    })
)

watchEffect(() => {
    props.form[props.fieldName] = rows.map(({ _key: _, ...d }) => ({ ...d }))
})

const addRow = () => rows.push(toEmptyRow())

const removeRow = (key: number) => {
    const index = rows.findIndex((r) => r._key === key)
    if (index !== -1) rows.splice(index, 1)
}
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="row in rows"
            :key="row._key"
            class="flex gap-2 items-start">
            <div
                v-for="field in fields"
                :key="field.key"
                class="flex-1">
                <input
                    v-model="row[field.key]"
                    type="text"
                    :placeholder="field.placeholder ? trans(field.placeholder) : field.key"
                    class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            <button
                type="button"
                @click.stop.prevent="removeRow(row._key)"
                class="mt-1 p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-md transition-colors">
                <FontAwesomeIcon :icon="faTrash" class="h-4 w-4" />
            </button>
        </div>

        <button
            type="button"
            @click.stop.prevent="addRow"
            class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800 font-medium">
            <FontAwesomeIcon :icon="faPlus" class="h-3.5 w-3.5" />
            {{ fieldData?.addLabel ? trans(fieldData.addLabel) : trans("Add row") }}
        </button>

        <p v-if="form?.errors?.[fieldName]" class="mt-1 text-sm text-red-600">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
