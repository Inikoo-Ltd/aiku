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
import {
    isDynamicComparisonItem,
    renameComparisonTemplateKey,
} from "@/Composables/comparisonTemplateKeys.ts"

import { ref, computed, watch } from "vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import { cloneDeep, set } from "lodash-es"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInfoCircle, faLock } from "@fal"

library.add(faInfoCircle, faLock)


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

const keyRulesHint = [
    "Spaces are turned into underscores: <i>bath bomb</i> becomes <i>bath_bomb</i>",
    "Must be unique inside the template",
    "Cannot be empty",
    "Saved when the input loses focus, an invalid key reverts to the previous one",
    "The key identifies the row, the label is the text shown to the customer",
    "Dynamic rows are filled in automatically, so their key is locked",
].map((rule) => `&bull; ${rule}`).join("<br>")

const dynamicKeyHint = "Dynamic row, its value is filled in automatically and the key cannot be changed"

const templateOptions = computed(() =>
    Object.keys(COMPARISON_TEMPLATES).map((key) => ({
        value: key,
        label: key
            .replaceAll("_", " ")
            .replace(/\b\w/g, char => char.toUpperCase()),
    }))
)

const renameKey = (currentKey: string, rawKey: string) => {
    const renamedTemplate = renameComparisonTemplateKey(editableTemplate.value, currentKey, rawKey)

    if (!renamedTemplate) {
        keyInputRevision.value++
        return
    }

    editableTemplate.value = renamedTemplate
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
        <div class="overflow-hidden rounded">
            <table class="w-full text-xs">
                <thead class="bg-gray-50">
                    <tr class="border-b border-gray-200">
                        <th class="w-12 px-2 py-1.5 text-center font-medium text-gray-500">
                            Show
                        </th>

                        <th class="px-2 py-1.5 text-left font-medium text-gray-500">
                            Key

                            <FontAwesomeIcon
                                v-tooltip="{ content: keyRulesHint, html: true }"
                                icon="fal fa-info-circle"
                                class="cursor-pointer text-gray-400 hover:text-gray-700"
                                fixed-width
                                aria-hidden="true"
                            />
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
                                v-if="isDynamicComparisonItem(item)"
                                v-model="item.show"
                                type="checkbox"
                                class="h-3.5 w-3.5 rounded border-gray-300"
                            />
                        </td>

                        <!-- Key -->
                        <td class="px-2 py-1">
                            <div
                                v-if="isDynamicComparisonItem(item)"
                                v-tooltip="dynamicKeyHint"
                                class="flex w-full items-center gap-1.5 rounded-md bg-gray-100 px-3 py-2.5 text-gray-500"
                            >
                                <FontAwesomeIcon
                                    icon="fal fa-lock"
                                    class="text-xxs"
                                    fixed-width
                                    aria-hidden="true"
                                />

                                {{ key }}
                            </div>

                            <PureInput
                                v-else
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
