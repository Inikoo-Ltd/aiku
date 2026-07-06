<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { get } from 'lodash-es'
import { trans } from 'laravel-vue-i18n'
import Toggle from '@/Components/Pure/Toggle.vue'

interface ScopeOption {
    value: string
    label: string
}

interface ScopeRow {
    context: string
    label: string
    enabled: boolean
    organisation: boolean
    group: boolean
}

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: any
}>()

const scopeOptions: ScopeOption[] = [
    { value: 'organisation', label: trans('This organisation only') },
    { value: 'group', label: trans('All') },
]

const rowLabels: Record<string, string> = {
    shop: trans('Shop'),
    family: trans('Family'),
    product: trans('Product'),
}

const initialValue: ScopeRow[] = Array.isArray(props.form[props.fieldName]) ? props.form[props.fieldName] : []

const rows = ref<ScopeRow[]>(
    initialValue.map((row) => ({
        context: row.context,
        label: rowLabels[row.context] ?? row.context,
        enabled: row.enabled ?? false,
        organisation: row.organisation ?? false,
        group: row.group ?? false,
    }))
)

const syncForm = () => {
    props.form[props.fieldName] = rows.value.map((row) => ({
        context: row.context,
        label: row.label,
        enabled: row.enabled,
        organisation: row.organisation,
        group: row.group,
    }))
}

watch(rows, syncForm, { deep: true })

const setScope = (row: ScopeRow, key: 'organisation' | 'group', value: boolean): void => {
    row[key] = value

    if (value) {
        const other = key === 'organisation' ? 'group' : 'organisation'
        row[other] = false
    }
}

const fieldNameString = computed(() => props.fieldName)
</script>

<template>
    <div class="w-full">
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500">
                            {{ trans('Type') }}
                        </th>
                        <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500">
                            {{ trans('Include') }}
                        </th>
                        <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr v-for="row in rows" :key="row.context">
                        <td class="px-3 py-3 font-medium text-gray-700">
                            {{ row.label }}
                        </td>
                        <td class="px-3 py-3">
                            <Toggle v-model="row.enabled" />
                        </td>
                        <td class="px-3 py-3">
                            <div v-if="row.enabled" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-6">
                                <div class="flex items-center gap-2">
                                    <Toggle
                                        :modelValue="row.organisation"
                                        @update:modelValue="(val) => setScope(row, 'organisation', val)"
                                    />
                                    <span class="whitespace-nowrap text-xs text-gray-700">{{ scopeOptions[0].label }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Toggle
                                        :modelValue="row.group"
                                        @update:modelValue="(val) => setScope(row, 'group', val)"
                                    />
                                    <span class="whitespace-nowrap text-xs text-gray-700">{{ scopeOptions[1].label }}</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p
            v-if="get(form, ['errors', fieldNameString])"
            class="mt-2 text-sm text-red-600"
            :id="`${fieldNameString}-error`"
        >
            {{ form.errors[fieldNameString] }}
        </p>
    </div>
</template>
