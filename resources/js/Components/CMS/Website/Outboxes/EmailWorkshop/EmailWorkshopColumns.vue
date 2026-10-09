<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPlus, faTrashAlt, faGripLinesVertical } from '@fal'
import EmailWorkshopField from './EmailWorkshopField.vue'
import EmailWorkshopPadding from './EmailWorkshopPadding.vue'
import { ctrans } from '@/Composables/useTrans'
import {
    EmailRow, MIN_COLUMN_WIDTH, addColumn, canAddColumn, columnRatioPresets, columnWidthPercent, removeColumn, resizeColumn, setColumnWidths,
} from './emailWorkshopBlocks'

library.add(faPlus, faTrashAlt, faGripLinesVertical)

const props = defineProps<{
    row: EmailRow
}>()

const selectedIndex = ref(0)
watch(() => props.row.uuid, () => {
    selectedIndex.value = 0
})

const selectedColumn = computed(() => props.row.columns[Math.min(selectedIndex.value, props.row.columns.length - 1)])
const widthOf = (index: number): number => columnWidthPercent(props.row.columns[index])

const ratioLabel = (widths: number[]): string => widths.map((width) => Math.round(width)).join(' : ')
const ratioPresets = computed(() => columnRatioPresets(props.row.columns.length))
const isActiveRatio = (widths: number[]): boolean =>
    props.row.columns.every((column, index) => Math.abs(columnWidthPercent(column) - widths[index]) < 0.5)

const barRef = ref<HTMLElement | null>(null)
const resizingDivider = ref<number | null>(null)

const startResize = (divider: number, event: PointerEvent) => {
    resizingDivider.value = divider
    ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)
}

const onResize = (event: PointerEvent) => {
    if (resizingDivider.value === null || !barRef.value) {
        return
    }
    const bar = barRef.value.getBoundingClientRect()
    const edge = Math.round((event.clientX - bar.left) / bar.width * 100)
    const widthBefore = props.row.columns.slice(0, resizingDivider.value).reduce((sum, column) => sum + columnWidthPercent(column), 0)
    resizeColumn(props.row, resizingDivider.value, edge - widthBefore)
}

const stopResize = () => {
    resizingDivider.value = null
}

const nudgeDivider = (divider: number, change: number) => resizeColumn(props.row, divider, Math.round(widthOf(divider)) + change)

const addNewColumn = () => {
    if (addColumn(props.row)) {
        selectedIndex.value = props.row.columns.length - 1
    }
}

const deleteSelectedColumn = () => {
    const index = props.row.columns.indexOf(selectedColumn.value)
    if (removeColumn(props.row, index)) {
        selectedIndex.value = Math.max(0, index - 1)
    }
}
</script>

<template>
    <div class="space-y-3 py-2">
        <div class="flex justify-end">
            <button type="button" class="flex items-center gap-x-1 text-[12px] font-medium text-[var(--theme-color-4)] hover:underline disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="!canAddColumn(row)" v-tooltip="canAddColumn(row) ? '' : ctrans('No column is wide enough to split')" @click="addNewColumn">
                <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                {{ ctrans('Add new') }}
            </button>
        </div>

        <div ref="barRef" class="flex select-none items-stretch">
            <template v-for="(column, index) in row.columns" :key="column.uuid ?? index">
                <button type="button" class="flex h-14 min-w-0 items-center justify-center rounded border text-[12px] transition"
                    :class="column === selectedColumn ? 'border-2 border-[var(--theme-color-4)] text-[var(--theme-color-4)]' : 'border-gray-300 bg-white text-gray-600 hover:border-[var(--theme-color-4)]'"
                    :style="{ flex: `${columnWidthPercent(column)} 1 0` }" :aria-pressed="column === selectedColumn"
                    :aria-label="`${ctrans('Column')} ${index + 1}, ${Math.round(columnWidthPercent(column))}%`"
                    @click="selectedIndex = index">
                    {{ Math.round(columnWidthPercent(column)) }}%
                </button>
                <div v-if="index < row.columns.length - 1" role="separator" aria-orientation="vertical" tabindex="0"
                    :aria-valuenow="Math.round(columnWidthPercent(column))" :aria-valuemin="MIN_COLUMN_WIDTH" :aria-label="ctrans('Resize columns')"
                    class="flex w-3 shrink-0 cursor-col-resize touch-none items-center justify-center text-gray-400 hover:text-[var(--theme-color-4)] focus:text-[var(--theme-color-4)] focus:outline-none"
                    :class="resizingDivider === index ? 'text-[var(--theme-color-4)]' : ''"
                    @pointerdown.prevent="startResize(index, $event)" @pointermove="onResize" @pointerup="stopResize" @pointercancel="stopResize"
                    @keydown.left.prevent="nudgeDivider(index, -1)" @keydown.right.prevent="nudgeDivider(index, 1)">
                    <FontAwesomeIcon icon="fal fa-grip-lines-vertical" class="text-[11px]" fixed-width aria-hidden="true" />
                </div>
            </template>
        </div>

        <div v-if="ratioPresets.length" class="grid grid-cols-3 gap-1.5">
            <button v-for="widths in ratioPresets" :key="ratioLabel(widths)" type="button"
                class="flex flex-col gap-y-1 rounded border p-1.5 transition"
                :class="isActiveRatio(widths) ? 'border-[var(--theme-color-4)] bg-[color-mix(in_srgb,var(--theme-color-4)_8%,white)]' : 'border-gray-200 hover:border-[var(--theme-color-4)]'"
                :aria-pressed="isActiveRatio(widths)" @click="setColumnWidths(row, widths)">
                <span class="flex w-full gap-x-0.5">
                    <span v-for="(width, index) in widths" :key="index" class="h-3 rounded-sm"
                        :class="isActiveRatio(widths) ? 'bg-[var(--theme-color-4)]' : 'bg-gray-300'" :style="{ width: `${width}%` }" />
                </span>
                <span class="text-[10px] text-gray-600">{{ ratioLabel(widths) }}</span>
            </button>
        </div>

        <div v-if="selectedColumn" class="rounded border border-gray-200 px-3 py-1">
            <div class="flex items-center justify-between pt-1.5">
                <span class="text-[12px] font-semibold uppercase text-gray-700">{{ ctrans('Column') }} {{ row.columns.indexOf(selectedColumn) + 1 }}</span>
                <button v-if="row.columns.length > 1" type="button" class="flex items-center gap-x-1 text-[12px] text-red-500 hover:text-red-700"
                    v-tooltip="ctrans('Its blocks move to the column next to it')" @click="deleteSelectedColumn">
                    <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                    {{ ctrans('Delete') }}
                </button>
            </div>
            <EmailWorkshopField v-if="row.columns.length > 1" type="number" :label="ctrans('Width (%)')" :min="MIN_COLUMN_WIDTH" :step="5"
                :modelValue="Math.round(columnWidthPercent(selectedColumn))"
                @update:modelValue="(width: number) => resizeColumn(row, row.columns.indexOf(selectedColumn), width)" />
            <EmailWorkshopField v-model="selectedColumn.style!['background-color']" type="color" :label="ctrans('Column background')" />
            <EmailWorkshopPadding :target="selectedColumn.style!" />
        </div>
    </div>
</template>
