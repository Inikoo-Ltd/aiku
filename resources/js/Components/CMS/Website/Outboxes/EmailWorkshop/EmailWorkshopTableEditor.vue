<script setup lang="ts">
import { computed } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPlus, faTimes } from '@fal'
import { ctrans } from '@/Composables/useTrans'
import { EmailModule, EmailTableSettings, addTableColumn, addTableRow, removeTableColumn, removeTableRow, tableColumnCount } from './emailWorkshopBlocks'
import { modulePaddingStyle, tableCellStyles } from './renderEmailHtml'

library.add(faPlus, faTimes)

const props = defineProps<{
    module: EmailModule
}>()

const table = computed(() => props.module.descriptor.aikuTable as EmailTableSettings)
const columnIndexes = computed(() => Array.from({ length: tableColumnCount(table.value) }, (_, index) => index))
const styles = computed(() => tableCellStyles(table.value))
const paddingStyle = computed(() => modulePaddingStyle(props.module))

const updateHeaderCell = (columnIndex: number, value: string) => {
    table.value.header[columnIndex] = value
}

const updateBodyCell = (rowIndex: number, columnIndex: number, value: string) => {
    table.value.rows[rowIndex][columnIndex] = value
}
</script>

<template>
    <div class="email-inline-editor" :style="paddingStyle" @click.stop>
        <table class="w-full" :style="styles.table">
            <tr v-if="table.hasHeader" class="group/table-row">
                <th v-for="columnIndex in columnIndexes" :key="`header-${columnIndex}`" class="group/table-column relative" :style="styles.header">
                    <input :value="table.header[columnIndex] ?? ''" type="text" :aria-label="ctrans('Header cell')"
                        class="w-full border-0 bg-transparent p-0 focus:ring-1 focus:ring-[var(--theme-color-4)]"
                        :style="{ font: 'inherit', color: 'inherit', textAlign: 'inherit' }"
                        @input="updateHeaderCell(columnIndex, ($event.target as HTMLInputElement).value)" />
                    <button v-if="columnIndexes.length > 1" type="button"
                        class="absolute -top-3 left-1/2 hidden h-5 w-5 -translate-x-1/2 items-center justify-center rounded-full bg-red-500 text-[10px] text-white shadow group-hover/table-column:flex"
                        v-tooltip="ctrans('Remove column')" @click="removeTableColumn(table, columnIndex)">
                        <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                    </button>
                </th>
            </tr>
            <tr v-for="(row, rowIndex) in table.rows" :key="`row-${rowIndex}`" class="group/table-row relative">
                <td v-for="columnIndex in columnIndexes" :key="`cell-${rowIndex}-${columnIndex}`" class="relative" :style="styles.cell(rowIndex)">
                    <input :value="row[columnIndex] ?? ''" type="text" :aria-label="ctrans('Table cell')"
                        class="w-full border-0 bg-transparent p-0 focus:ring-1 focus:ring-[var(--theme-color-4)]"
                        :style="{ font: 'inherit', color: 'inherit', textAlign: 'inherit' }"
                        @input="updateBodyCell(rowIndex, columnIndex, ($event.target as HTMLInputElement).value)" />
                    <button v-if="columnIndex === columnIndexes.length - 1 && table.rows.length > 1" type="button"
                        class="absolute -right-3 top-1/2 hidden h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full bg-red-500 text-[10px] text-white shadow group-hover/table-row:flex"
                        v-tooltip="ctrans('Remove row')" @click="removeTableRow(table, rowIndex)">
                        <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                    </button>
                </td>
            </tr>
        </table>
        <div class="mt-2 flex gap-x-2">
            <button type="button"
                class="flex items-center gap-x-1 rounded border border-dashed border-gray-300 bg-white px-2 py-1 text-[11px] text-gray-600 hover:border-[var(--theme-color-4)] hover:text-[var(--theme-color-4)]"
                @click="addTableRow(table)">
                <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                {{ ctrans('Row') }}
            </button>
            <button type="button"
                class="flex items-center gap-x-1 rounded border border-dashed border-gray-300 bg-white px-2 py-1 text-[11px] text-gray-600 hover:border-[var(--theme-color-4)] hover:text-[var(--theme-color-4)]"
                @click="addTableColumn(table)">
                <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                {{ ctrans('Column') }}
            </button>
        </div>
    </div>
</template>
