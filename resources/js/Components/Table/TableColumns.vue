<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import ButtonWithDropdown from "./ButtonWithDropdown.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faChevronUp } from "@fal"
import { faEye } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faEye, faChevronUp, faChevronDown)

/**
 * Which columns the table shows and in what order. Every column is listed so it can be moved; only
 * the ones the table allows to be hidden get a switch, the rest say so instead of offering a control
 * that would refuse.
 */
type Column = { key: string; label: any; hidden: boolean; can_be_hidden: boolean }

const props = defineProps<{
    columns: Column[]
    hasHiddenColumns: boolean
    onChange: (key: string, hidden: boolean) => void
    onMove: (key: string, direction: -1 | 1) => void
}>()

/* A label is usually a string, sometimes `{ type: 'text', data }`, and for icon headings an icon
   array; the key stands in for an icon so the row still reads as something. */
const labelOf = (column: Column): string => {
    if (typeof column.label === "string") return trans(column.label)
    if (column.label && !Array.isArray(column.label) && typeof column.label.data === "string") return trans(column.label.data)

    return column.key
}
</script>

<template>
    <ButtonWithDropdown placement="bottom-end" dusk="columns-dropdown" :active="hasHiddenColumns" id="filter-colums">
        <template #button>
            <span v-tooltip="trans('Choose and order columns')" class="flex items-center">
                <FontAwesomeIcon
                    icon="fas fa-eye"
                    aria-hidden="true"
                    :class="[hasHiddenColumns ? 'text-green-400' : 'text-gray-400', 'h-5 w-5']" />
                <span class="sr-only">{{ trans("Choose and order columns") }}</span>
            </span>
        </template>

        <div class="max-h-96 min-w-max overflow-y-auto px-2">
            <ul class="divide-y divide-gray-200">
                <li v-for="(column, index) in props.columns" :key="column.key" class="flex items-center justify-between gap-4 py-1.5">
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            :disabled="index === 0"
                            :aria-label="trans('Move :column up', { column: labelOf(column) })"
                            class="rounded p-1 text-gray-400 transition hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-30 disabled:hover:text-gray-400"
                            @click.prevent="onMove(column.key, -1)">
                            <FontAwesomeIcon icon="fal fa-chevron-up" fixed-width aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            :disabled="index === props.columns.length - 1"
                            :aria-label="trans('Move :column down', { column: labelOf(column) })"
                            class="rounded p-1 text-gray-400 transition hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-30 disabled:hover:text-gray-400"
                            @click.prevent="onMove(column.key, 1)">
                            <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width aria-hidden="true" />
                        </button>
                        <p :id="`toggle-column-${column.key}`" class="text-sm text-gray-800">
                            {{ labelOf(column) }}
                            <FontAwesomeIcon v-if="Array.isArray(column.label)" class="text-gray-700" :icon="column.label" aria-hidden="true" />
                        </p>
                    </div>

                    <button
                        v-if="column.can_be_hidden"
                        type="button"
                        role="switch"
                        :aria-checked="!column.hidden"
                        :aria-labelledby="`toggle-column-${column.key}`"
                        :dusk="`toggle-column-${column.key}`"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                        :class="[column.hidden ? 'bg-gray-200' : 'bg-gray-700']"
                        @click.prevent="onChange(column.key, column.hidden)">
                        <span
                            aria-hidden="true"
                            :class="column.hidden ? 'translate-x-0' : 'translate-x-5'"
                            class="inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" />
                    </button>
                    <span v-else class="text-xs text-gray-400">{{ trans("always shown") }}</span>
                </li>
            </ul>
        </div>
    </ButtonWithDropdown>
</template>
