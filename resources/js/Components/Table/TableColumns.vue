<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import ButtonWithDropdown from "./ButtonWithDropdown.vue"
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faChevronUp, faEye, faEyeSlash } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faEye, faEyeSlash, faChevronUp, faChevronDown)

/**
 * Which columns the table shows and in what order. Every column is listed so it can be moved; only
 * the ones the table allows to be hidden get a switch, the rest say so instead of offering a control
 * that would refuse.
 */
type Column = {
    key: string
    label: any
    shortLabel?: string | null
    icon?: string | null
    tooltip?: string | null
    hidden: boolean
    can_be_hidden: boolean
}

const props = defineProps<{
    columns: Column[]
    onChange: (key: string, hidden: boolean) => void
    onMove: (key: string, direction: -1 | 1) => void
}>()

/* A label is usually a string, sometimes `{ type: 'text', data }`, and for icon headings an icon
   array. A column headed by an icon alone has no label at all, and a switch with nothing beside it
   is a switch for nothing, so its short label, failing that its tooltip, failing that its key names
   it here. */
const labelOf = (column: Column): string => {
    const written =
        typeof column.label === "string"
            ? trans(column.label)
            : column.label && !Array.isArray(column.label) && typeof column.label.data === "string"
              ? trans(column.label.data)
              : ""

    return written || column.shortLabel || column.tooltip || column.key
}

const hiddenCount = computed(() => props.columns.filter((column) => column.hidden).length)

/* A crossed-out eye says some columns are being held back, which is the ordinary state on a wide
   table rather than a success or a warning, so the shape carries it and the colour stays neutral. */
const buttonTooltip = computed(() =>
    hiddenCount.value
        ? trans("Choose and order columns, :count hidden", { count: String(hiddenCount.value) })
        : trans("Choose and order columns")
)
</script>

<template>
    <ButtonWithDropdown
        placement="bottom-end"
        dusk="columns-dropdown"
        id="filter-colums"
        button-class="h-7 px-2 rounded">
        <template #button>
            <span v-tooltip="buttonTooltip" class="flex items-center">
                <FontAwesomeIcon
                    :icon="hiddenCount ? 'fal fa-eye-slash' : 'fal fa-eye'"
                    aria-hidden="true"
                    class="h-4 w-4 text-gray-500" />
                <span class="sr-only">{{ buttonTooltip }}</span>
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
                        <FontAwesomeIcon
                            v-if="column.icon"
                            :icon="column.icon"
                            fixed-width
                            aria-hidden="true"
                            class="text-gray-500" />
                        <p :id="`toggle-column-${column.key}`" :title="labelOf(column)" class="max-w-[18rem] truncate text-sm text-gray-800">
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
