<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationTriangle } from "@fal"
import { faCheck } from "@fas"

library.add(faExclamationTriangle, faCheck)

const props = defineProps<{
    form?: any
    fieldName: any
    options: string[] | object
    fieldData?: {
        placeholder?: string
        required?: boolean
        mode?: string
		searchable?: boolean
        hideHeader?: boolean
        hideDivider?: boolean
        emptyWarning?: string
    }
}>()

const isNoneSelected = computed(() => !(props.form?.[props.fieldName] ?? []).some((option: { value: boolean }) => option.value))
</script>

<template>
    <div>
        <div v-if="fieldData?.mode === 'inline'" class="flex flex-wrap gap-2">
            <label v-for="(option, index) in form[fieldName]" :key="index" :for="`item-${fieldName}-${index}`"
                class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm font-medium cursor-pointer select-none transition-colors"
                :class="option.value
                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                    : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'">
                <input v-model="option.value"
                    :id="`item-${fieldName}-${index}`"
                    :name="`item-${fieldName}-${index}`"
                    type="checkbox"
                    class="sr-only" />
                <span class="flex h-4 w-4 items-center justify-center rounded border"
                    :class="option.value ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 bg-white'">
                    <FontAwesomeIcon v-if="option.value" icon="fas fa-check" class="text-[10px]" aria-hidden="true" />
                </span>
                {{ option.label }}
            </label>
        </div>

        <table v-else>
            <thead v-if="!(fieldData?.hideHeader ?? false)">
                <tr class="text-gray-600 bg-gray-200">
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left font-semibold tracking-wide">
                        {{ ctrans("Label") }}
                    </th>
                    <th scope="col" class="px-3 py-3.5 font-semibold tracking-wide text-center">
                        {{ ctrans("Value") }}
                    </th>
                </tr>
            </thead>
            <tbody :class="(fieldData?.hideDivider ?? false) ? '' : 'divide-y divide-gray-200'">
                <tr v-for="(option, index) in form[fieldName]" :key="index">
                    <td class="">
                        <label :for="`item-${index}`"
                            class="whitespace-nowrap block py-2 pl-4 pr-3 text-sm font-medium text-gray-500 hover:text-gray-600 cursor-pointer">
                            {{ option.label }}
                        </label>
                    </td>
                    <td class="whitespace-nowrap px-3 text-sm text-center">
                        <input v-model="option.value"
                            :id="`item-${index}`"
                            :name="`item-${index}`"
                            type="checkbox"
                            xtitles="`I'm Interested in ${option.label}`"
                            class="h-6 w-6 rounded cursor-pointer border-gray-300 hover:border-[--theme-color-0] text-[--theme-color-0] focus:ring-[--theme-color-0]" />
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-if="fieldData?.emptyWarning && isNoneSelected"
            class="mt-2 flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700">
            <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
            <span>{{ fieldData.emptyWarning }}</span>
        </div>
    </div>
</template>

<style scoped></style>
