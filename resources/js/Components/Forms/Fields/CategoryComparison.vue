<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {
    COMPARISON_TEMPLATES,
    type ComparisonTemplate,
    type ComparisonTemplateName,
} from "@/Composables/comparisonTemplates.ts"

import { ref, computed, watch } from "vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import { cloneDeep, set } from "lodash-es"


const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData?: {
        type: string
        value?: {
            template?: ComparisonTemplateName
            items?: ComparisonTemplate
        } | null
        placeholder: string
        readonly?: boolean
        copyButton: boolean
        maxLength?: number
        uppercase?: boolean
        additional_instructions?: string
        /** Show the value read-only with an Edit link, instead of a permanently open input */
        collapsible?: boolean
        /** Prefixes the collapsed value with "Nx", for the unit label */
        unitsPreview?: number
        /** Warning shown while editing, before the save confirmation is reached */
        cascadeNote?: string
    }
}>()

const savedValue = props.fieldData?.value

const selectedTemplate = ref<ComparisonTemplateName>(
    savedValue?.template ?? "BATH_BOMBS"
)

const editableTemplate = ref<ComparisonTemplate>(
    cloneDeep(savedValue?.items ?? COMPARISON_TEMPLATES[selectedTemplate.value])
)

const keyInputRevision = ref(0)

const templateOptions = computed(() =>
    Object.keys(COMPARISON_TEMPLATES).map((key) => ({
        value: key,
        label: key
            .replaceAll("_", " ")
            .replace(/\b\w/g, char => char.toUpperCase()),
    }))
)

const renameKey = (currentKey: string, rawKey: string) => {
    const newKey = rawKey.trim().replaceAll(/\s+/g, "_")

    if (!newKey || newKey === currentKey || newKey in editableTemplate.value) {
        keyInputRevision.value++
        return
    }

    editableTemplate.value = Object.fromEntries(
        Object.entries(editableTemplate.value).map(
            ([key, item]) => key === currentKey ? [newKey, item] : [key, item]
        )
    )
}

watch(selectedTemplate, (templateName) => {
    editableTemplate.value = cloneDeep(
        COMPARISON_TEMPLATES[templateName]
    )
})

watch([selectedTemplate, editableTemplate], () => {
    set(props.form, props.fieldName, {
        template: selectedTemplate.value,
        items: editableTemplate.value,
    })
}, { deep: true, immediate: true })
</script>
<template>
    <div class="space-y-2">
        <!-- Template -->
        <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-gray-600">
                Template
            </span>

            <select
                v-model="selectedTemplate"
                class="h-8 rounded border-gray-300 px-2 py-1 text-xs"
            >
                <option
                    v-for="option in templateOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded border border-gray-200">
            <table class="w-full text-xs">
                <thead class="bg-gray-50">
                    <tr class="border-b border-gray-200">
                        <th class="w-12 px-2 py-1.5 text-center font-medium text-gray-500">
                            Show
                        </th>

                        <th class="px-2 py-1.5 text-left font-medium text-gray-500">
                            Key
                        </th>

                        <th class="px-2 py-1.5 text-left font-medium text-gray-500">
                            Label
                        </th>

                        <th class="px-2 py-1.5 text-left font-medium text-gray-500">
                            Value
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    <tr
                        v-for="(item, key) in editableTemplate"
                        :key="key"
                        class="hover:bg-gray-50"
                    >
                        <!-- Show -->
                        <td class="px-2 py-1 text-center">
                            <input
                                v-if="'show' in item"
                                v-model="item.show"
                                type="checkbox"
                                class="h-3.5 w-3.5 rounded border-gray-300"
                            />
                        </td>

                        <!-- Key -->
                        <td class="px-2 py-1">
                            <PureInput
                                :key="`${key}-${keyInputRevision}`"
                                :model-value="key"
                                class="w-full"
                                placeholder="Key"
                                @blur="(newKey: string) => renameKey(key, newKey)"
                            />
                        </td>

                        <!-- Label -->
                        <td class="px-2 py-1">
                            <PureInput
                                v-model="item.label"
                                class="w-full"
                            />
                        </td>

                        <!-- Value -->
                        <td class="px-2 py-1">
                            <PureInput
                                v-if="'value' in item"
                                v-model="item.value"
                                class="w-full"
                                placeholder="Value"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
