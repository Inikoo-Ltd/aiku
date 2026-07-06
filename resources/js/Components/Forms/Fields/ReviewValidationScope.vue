<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { get } from 'lodash-es'
import { trans } from 'laravel-vue-i18n'
import RadioButton from 'primevue/radiobutton'
import ToggleSwitch from 'primevue/toggleswitch'

interface ScopeOption {
    value: string
    label: string
}

interface ScopeRow {
    context: string
    label: string
    enabled: boolean
    scope: string
}

const props = defineProps<{
    form: any
    fieldName: string
    options?: ScopeOption[]
    fieldData?: any
}>()

const scopeOptions = computed<ScopeOption[]>(() =>
    props.options ??
    props.fieldData?.options ?? [
        { value: 'organisation', label: trans('Organisation') },
        { value: 'group', label: trans('Group') },
    ]
)

const initialValue: ScopeRow[] = Array.isArray(props.form[props.fieldName]) ? props.form[props.fieldName] : []

const rows = ref<ScopeRow[]>(
    initialValue.map((row) => ({
        context: row.context,
        label: row.label,
        enabled: row.enabled ?? false,
        scope: row.scope ?? scopeOptions.value[0]?.value,
    }))
)

const syncForm = () => {
    props.form[props.fieldName] = rows.value.map((row) => ({
        context: row.context,
        label: row.label,
        enabled: row.enabled,
        scope: row.scope,
    }))
}

watch(rows, syncForm, { deep: true })

const fieldNameString = computed(() => props.fieldName)
</script>

<template>
    <div class="w-full">
        <div class="overflow-hidden rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-2 text-left font-medium text-gray-500">
                            {{ trans('Review type') }}
                        </th>
                        <th scope="col" class="px-4 py-2 text-left font-medium text-gray-500">
                            {{ trans('Enabled') }}
                        </th>
                        <th scope="col" class="px-4 py-2 text-left font-medium text-gray-500">
                            {{ trans('Validation scope') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr v-for="row in rows" :key="row.context">
                        <td class="px-4 py-3 font-medium text-gray-700">
                            {{ row.label }}
                        </td>
                        <td class="px-4 py-3">
                            <ToggleSwitch v-model="row.enabled" />
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-6" :class="{ 'opacity-50': !row.enabled }">
                                <div
                                    v-for="option in scopeOptions"
                                    :key="`${row.context}-${option.value}`"
                                    class="flex items-center gap-2"
                                >
                                    <RadioButton
                                        v-model="row.scope"
                                        :inputId="`${fieldNameString}-${row.context}-${option.value}`"
                                        :name="`${fieldNameString}-${row.context}`"
                                        :value="option.value"
                                        :disabled="!row.enabled"
                                    />
                                    <label
                                        :for="`${fieldNameString}-${row.context}-${option.value}`"
                                        class="text-sm text-gray-700"
                                        :class="row.enabled ? 'cursor-pointer' : 'cursor-not-allowed'"
                                    >
                                        {{ option.label }}
                                    </label>
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
