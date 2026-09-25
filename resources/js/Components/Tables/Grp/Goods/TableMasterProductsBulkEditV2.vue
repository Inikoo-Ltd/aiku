<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { ref, computed, watch, nextTick, defineAsyncComponent } from "vue"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import Table from "@/Components/Table/Table.vue"
import Image from "@common/Components/Image.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import TaxPresetEditModal from "@/Components/Utils/TaxPresetEditModal.vue"
import TaxSweepProgressModal from "@/Components/Utils/TaxSweepProgressModal.vue"
import { notify } from "@kyvg/vue3-notification"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPercent, faHamburger, faFlowerTulip, faPencil, faArrowRight, faUndoAlt, faExclamationTriangle } from "@fal"
import { taxPresetIcon } from "@/Composables/taxPresets"

library.add(faPercent, faHamburger, faFlowerTulip, faPencil, faArrowRight, faUndoAlt, faExclamationTriangle)

// The bulk edit tab: fields that make sense across many rows, tax first. Each row shows a
// mini version of the preset card; clicking it, or editing a whole selection, opens the
// same card picker the product edit page uses.
const props = defineProps<{
    data: any
    tab?: string
    taxPresetOptions?: { value: string; title: string; description?: string }[]
    taxBulkSignal?: number
    bulkEditSaveSignal?: number
}>()

const emits = defineEmits<{
    (e: 'selectedRow', value: Record<string, boolean>): void
    (e: 'bulkEditState', value: { dirty: number; saving: boolean }): void
}>()

const PRESET_META: Record<string, { icon: any; title: string }> = {
    standard: { icon: faPercent, title: ctrans('Standard rate') },
    food: { icon: faHamburger, title: ctrans('Food') },
    dried_flowers: { icon: faFlowerTulip, title: ctrans('Dried flowers') },
    custom: { icon: faPercent, title: ctrans('Custom') },
}

const presetMeta = (value: string) => PRESET_META[value] ?? PRESET_META.standard

// editable fields
type EditableField = 'name' | 'description' | 'description_extra' | 'unit'

const pendingEdits = ref<Record<number, { code: string; fields: Partial<Record<EditableField, any>> }>>({})
const isSavingEdits = ref(false)

const fieldValue = (item: any, field: EditableField): string =>
    pendingEdits.value[item.id]?.fields[field] ?? item[field] ?? ''

const isFieldDirty = (item: any, field: EditableField): boolean =>
    pendingEdits.value[item.id]?.fields[field] !== undefined

const dirtyField = (isDirty: boolean): string => isDirty ? '!bg-amber-50 !ring-amber-400' : ''

const dirtyCount = (item: any): number => Object.keys(pendingEdits.value[item.id]?.fields ?? {}).length

const setFieldValue = (item: any, field: EditableField, value: any) => {
    const original = item[field] ?? ''
    const edit = pendingEdits.value[item.id] ?? { code: item.code, fields: {} }

    if (String(value ?? '') === String(original)) {
        delete edit.fields[field]
    } else {
        edit.fields[field] = value
    }

    if (Object.keys(edit.fields).length) {
        pendingEdits.value[item.id] = edit
    } else {
        delete pendingEdits.value[item.id]
    }
}

// Names are not unique in the database, so a clash is only a warning. It is checked over the rows
const namesOnPage = computed(() => {
    const codesByName = new Map<string, string[]>()

    for (const item of props.data?.data ?? []) {
        const name = fieldValue(item, 'name').trim().toLowerCase()
        if (name) {
            codesByName.set(name, [...(codesByName.get(name) ?? []), item.code])
        }
    }

    return codesByName
})

const sameNameAs = (item: any): string[] =>
    (namesOnPage.value.get(fieldValue(item, 'name').trim().toLowerCase()) ?? [])
        .filter((code) => code !== item.code)

const EditorV2 = defineAsyncComponent(() => import("@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"))

const RICH_TEXT_FIELDS: EditableField[] = ['description', 'description_extra']
const RICH_TEXT_TOOLBAR = [
    'heading1', 'heading2', 'heading3',
    'bold', 'italic', 'underline', 'fontSize', 'bulletList', 'fontFamily', 'blockquote', 'divider', 'orderedList', 'color', 'highlight', 'link', 'alignLeft', 'alignCenter', 'alignRight', 'clear', 'undo', 'redo',
]

const activeEditorCell = ref<string | null>(null)
const activeEditor = ref<any>(null)

const cellKey = (item: any, field: EditableField): string => `${item.id}:${field}`

/** The editor stores "<p></p>" for an empty text, which renders to nothing: show the hint instead. */
const previewHtml = (item: any, field: EditableField): string => {
    const html = fieldValue(item, field)

    return /<(img|iframe|table|hr)\b/i.test(html) || html.replace(/<[^>]*>|&nbsp;|\s/g, '')
        ? html
        : ''
}

const openEditor = (item: any, field: EditableField) => {
    if (!isSavingEdits.value) {
        activeEditorCell.value = cellKey(item, field)
    }
}

const bindEditor = (instance: any) => {
    if (!instance || activeEditor.value === instance) return

    activeEditor.value = instance
    nextTick(() => instance.editor?.commands.focus('end'))
}

const revertFields = (item: any, fields: EditableField[]) => {
    const edit = pendingEdits.value[item.id]
    if (!edit) return

    for (const field of fields) {
        delete edit.fields[field]
        
        if (activeEditorCell.value === cellKey(item, field)) {
            activeEditorCell.value = null
        }
    }

    if (!Object.keys(edit.fields).length) {
        delete pendingEdits.value[item.id]
    }
}

const saveEdits = async () => {
    if (isSavingEdits.value) return

    const clashing = (props.data?.data ?? [])
        .filter((item: any) => isFieldDirty(item, 'name') && sameNameAs(item).length)

    if (clashing.length) {
        notify({
            title: ctrans("Cannot save"),
            text: ctrans("Same name on :codes, fix them first", { codes: clashing.map((item: any) => item.code).join(', ') }),
            type: "error",
        })

        return
    }

    const entries = Object.entries(pendingEdits.value)
    if (!entries.length) return

    isSavingEdits.value = true

    try {
        await axios.patch(
            route('grp.models.master_asset.bulk_update'),
            { products: entries.map(([id, edit]) => ({ id: Number(id), ...edit.fields })) },
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        )

        for (const [id, edit] of entries) {
            Object.assign(props.data?.data?.find((item: any) => item.id === Number(id)) ?? {}, edit.fields)
            delete pendingEdits.value[Number(id)]
        }

        notify({
            title: ctrans("Success"),
            text: ctrans(':count products updated', { count: String(entries.length) }),
            type: "success",
        })
    } catch (error: any) {
        const errors = error?.response?.data?.errors ?? {}

        notify({
            title: ctrans("Something went wrong"),
            text: Object.entries(errors)
                .map(([key, messages]) => `${entries[Number(key.split('.')[1])]?.[1].code}: ${(messages as string[]).join(' ')}`)
                .join(' · ') || error?.response?.data?.message,
            type: "error",
        })
    } finally {
        isSavingEdits.value = false
    }
}

watch(() => props.bulkEditSaveSignal, () => saveEdits())

const unitForSelection = ref('')

const applyUnitToSelection = () => {
    const unit = unitForSelection.value.trim()
    if (!unit) return

    for (const item of props.data?.data ?? []) {
        if (compSelectedIds.value.includes(String(item.id))) {
            setFieldValue(item, 'unit', unit)
        }
    }

    unitForSelection.value = ''
}

const RESPONSIBILITY_PHRASE = 'yes I accept responsibility'

const quantityForSelection = ref('')
const quantityRows = ref<any[]>([])
const openOrdersByMasterAsset = ref<Record<number, number> | null>(null)
const responsibilityTyped = ref('')
const quantitySaving = ref(false)
const quantitySavedCount = ref(0)
const quantitySavingTotal = ref(0)
const lastBatch = ref<{ item: any; tradeUnitId: number; units: number }[]>([])
const isUndoing = ref(false)

const isResponsibilityAccepted = computed(() =>
    responsibilityTyped.value.trim().toLowerCase() === RESPONSIBILITY_PHRASE.toLowerCase())

const outerPrice = (item: any): number => Number(item.price) || 0

const quantityPreview = computed(() => {
    const newUnits = Number(quantityForSelection.value)

    return quantityRows.value.map((item) => {
        const oldUnits = Number(item.units) || 1
        const openOrders = openOrdersByMasterAsset.value?.[item.id] ?? null
        const skipReason = !item.single_trade_unit_id
            ? ctrans('Several trade units, edit it on the product composition page')
            : openOrders
                ? ctrans('Open orders placed at the current pack size, edit it on the product composition page')
                : oldUnits === newUnits
                    ? ctrans('Already :units', { units: `${newUnits}` })
                    : null

        return {
            item,
            oldUnits,
            newUnits,
            price: outerPrice(item),
            openOrders,
            skipReason,
        }
    })
})

const quantityToApply = computed(() => quantityPreview.value.filter((row) => !row.skipReason))

const openQuantityPreview = async () => {
    const quantity = Number(quantityForSelection.value)
    if (!(quantity > 0)) return

    quantityRows.value = props.data?.data?.filter((item: any) => compSelectedIds.value.includes(String(item.id))) ?? []
    openOrdersByMasterAsset.value = null
    responsibilityTyped.value = ''
    quantitySavedCount.value = 0

    try {
        const response = await axios.get(
            route('grp.json.master_assets.open_orders_affected_by_units_change'),
            { params: { ids: quantityRows.value.map((item) => item.id) } }
        )
        openOrdersByMasterAsset.value = response.data
    } catch (error: any) {
        quantityRows.value = []
        notify({
            title: ctrans("Something went wrong"),
            text: error?.response?.data?.message ?? '',
            type: "error",
        })
    }
}

const setItemUnits = (item: any, units: number) => {
    item.units = units
    item.trade_units_label = item.trade_units_label?.replace(/×[\d.]+$/, `×${units}`)
}

const undoLastBatch = async () => {
    if (!lastBatch.value.length || isUndoing.value) return

    isUndoing.value = true
    const undone: number[] = []

    try {
        const response = await axios.get(
            route('grp.json.master_assets.open_orders_affected_by_units_change'),
            { params: { ids: lastBatch.value.map((entry) => entry.item.id) } }
        )
        const withOpenOrders = lastBatch.value.filter((entry) => response.data[entry.item.id])

        for (const entry of lastBatch.value.filter((entry) => !response.data[entry.item.id])) {
            await axios.patch(
                route('grp.models.master_asset.update', { masterAsset: entry.item.id }),
                { trade_units: [{ id: entry.tradeUnitId, quantity: entry.units }] },
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            )
            setItemUnits(entry.item, entry.units)
            undone.push(entry.item.id)
        }

        lastBatch.value = withOpenOrders

        notify({
            title: withOpenOrders.length ? ctrans("Partly undone") : ctrans("Undone"),
            text: withOpenOrders.length
                ? ctrans(':count products restored. Not restored because they have open orders now: :codes', { count: `${undone.length}`, codes: withOpenOrders.map((entry) => entry.item.code).join(', ') })
                : ctrans(':count products restored', { count: `${undone.length}` }),
            type: withOpenOrders.length ? "warn" : "success",
        })
    } catch (error: any) {
        lastBatch.value = lastBatch.value.filter((entry) => !undone.includes(entry.item.id))
        notify({
            title: ctrans("Something went wrong"),
            text: ctrans(':count products restored before the error', { count: `${undone.length}` }) + ' ' + (error?.response?.data?.message ?? ''),
            type: "error",
        })
    } finally {
        isUndoing.value = false
    }
}

const applyQuantity = async () => {
    if (!isResponsibilityAccepted.value || quantitySaving.value) return

    const rowsToApply = [...quantityToApply.value]
    lastBatch.value = []
    quantitySavingTotal.value = rowsToApply.length
    quantitySaving.value = true

    try {
        for (const row of rowsToApply) {
            await axios.patch(
                route('grp.models.master_asset.update', { masterAsset: row.item.id }),
                { trade_units: [{ id: row.item.single_trade_unit_id, quantity: row.newUnits }] },
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            )
            lastBatch.value.push({ item: row.item, tradeUnitId: row.item.single_trade_unit_id, units: row.oldUnits })
            setItemUnits(row.item, row.newUnits)
            quantitySavedCount.value++
        }

        notify({
            title: ctrans("Success"),
            text: ctrans(':count products updated', { count: `${quantitySavedCount.value}` }),
            type: "success",
        })
        quantityRows.value = []
        quantityForSelection.value = ''
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: ctrans(':count products updated before the error', { count: `${quantitySavedCount.value}` }) + ' ' + (error?.response?.data?.message ?? ''),
            type: "error",
        })
    } finally {
        quantitySaving.value = false
    }
}

const bulkEditState = computed(() => ({
    dirty: Object.keys(pendingEdits.value).length,
    saving: isSavingEdits.value,
}))

watch(bulkEditState, (state) => emits('bulkEditState', state), { immediate: true })

// Selection via the table's own checkboxes, the pricing pattern.
const selectedIds = ref<Record<string, boolean>>({})
const compSelectedIds = computed(() =>
    Object.keys(selectedIds.value).filter((key) => selectedIds.value[key]))

// The modal edits either one row or the whole selection.
const editingItems = ref<any[]>([])
const isSaving = ref(false)
const modalPhase = ref<'edit' | 'sweep'>('edit')
const sweepMasterAssetId = ref<number | null>(null)
const sweepRunning = ref(false)
const sweepProgress = ref<any>(null)
// The rows the current sweep covers, so their cells can carry the progress when the
// dialog is dismissed - the pricing behaviour.
const sweptIds = ref<number[]>([])
// What each swept row changed from and to, for the transition icons on the bars.
const sweptTransitions = ref<Record<number, { from: string; to: string }>>({})
const sweepToPreset = ref<string | null>(null)
const sweepFromPreset = ref<string | null>(null)

const sweepPct = computed(() =>
    sweepProgress.value?.baskets_total
        ? Math.round(sweepProgress.value.baskets_done / sweepProgress.value.baskets_total * 100)
        : 0)

const modalInitial = computed<string | null>(() => {
    const presets = editingItems.value.map((item) => item.tax_preset)
    return presets.length && presets.every((preset) => preset === presets[0]) ? presets[0] : null
})

const modalSubject = computed(() =>
    editingItems.value.length === 1
        ? editingItems.value[0].code
        : ctrans(':count products', { count: String(editingItems.value.length) }))

const openForRow = (item: any) => {
    if (sweepRunning.value) return
    modalPhase.value = 'edit'
    editingItems.value = [item]
}

const openForSelection = () => {
    if (!compSelectedIds.value.length || sweepRunning.value) return
    modalPhase.value = 'edit'
    editingItems.value = props.data?.data?.filter((item: any) => compSelectedIds.value.includes(String(item.id))) ?? []
}

// The page-head button triggers the selection edit from outside the table, the pricing pattern.
watch(() => props.taxBulkSignal, () => openForSelection())

const onSave = async (presetValue: string) => {
    isSaving.value = true
    const items = [...editingItems.value]
    sweptIds.value = items.map((item) => item.id)
    sweptTransitions.value = Object.fromEntries(items.map((item) => [item.id, { from: item.tax_preset, to: presetValue }]))
    sweepToPreset.value = presetValue
    const froms = items.map((item) => item.tax_preset)
    sweepFromPreset.value = froms.every((from) => from === froms[0]) ? froms[0] : null

    try {
        for (const item of items) {
            await axios.patch(
                route('grp.models.master_asset.update', { masterAsset: item.id }),
                { tax_preset: presetValue },
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            )
            item.tax_preset = presetValue
            sweepProgress.value = null
            sweepMasterAssetId.value = item.id
        }

        /** The dialog stays open and becomes the sweep progress: that is the feedback. */
        modalPhase.value = 'sweep'
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: error?.response?.data?.message ?? '',
            type: "error",
        })
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <div class="overflow-x-auto">
    <div v-if="lastBatch.length" class="mt-2 flex items-center gap-x-2 px-4 text-sm">
        <span class="text-gray-500">
            {{ ctrans('Last trade unit quantity change: :count products', { count: `${lastBatch.length}` }) }}
        </span>
        <button
            type="button"
            :disabled="isUndoing || quantitySaving"
            @click="undoLastBatch"
            class="rounded border border-amber-400 px-2 py-1 text-amber-700 hover:bg-amber-50 disabled:opacity-50">
            <FontAwesomeIcon :icon="faUndoAlt" class="h-3 w-3" fixed-width :class="isUndoing ? 'animate-spin' : ''" />
            {{ ctrans('Undo last batch') }}
        </button>
    </div>
    <div v-if="compSelectedIds.length" class="mt-2 flex items-center gap-x-2 px-4 text-sm">
        <span class="text-gray-500">{{ ctrans('Unit label for :count selected', { count: `${compSelectedIds.length}` }) }}</span>
        <PureInput
            v-model="unitForSelection"
            classInput="!h-7 !text-sm !w-32"
            :placeholder="ctrans('unit')"
            :disabled="isSavingEdits"
            @keydown.enter="applyUnitToSelection" />
        <button
            type="button"
            :disabled="isSavingEdits || !unitForSelection.trim()"
            @click="applyUnitToSelection"
            class="rounded border border-gray-300 px-2 py-1 hover:bg-gray-50 disabled:opacity-50">
            <FontAwesomeIcon :icon="faArrowRight" class="h-3 w-3" fixed-width />
            {{ ctrans('Apply') }}
        </button>
        <span class="ml-4 text-gray-500">{{ ctrans('Trade unit quantity') }}</span>
        <PureInput
            v-model="quantityForSelection"
            type="number"
            classInput="!h-7 !text-sm !w-24"
            :disabled="isSavingEdits || quantitySaving"
            @keydown.enter="openQuantityPreview" />
        <button
            type="button"
            :disabled="isSavingEdits || quantitySaving || !(Number(quantityForSelection) > 0)"
            @click="openQuantityPreview"
            class="rounded border border-gray-300 px-2 py-1 hover:bg-gray-50 disabled:opacity-50">
            <FontAwesomeIcon :icon="faArrowRight" class="h-3 w-3" fixed-width />
            {{ ctrans('Preview') }}
        </button>
    </div>
    <Table
        :resource="data"
        :name="tab"
        class="mt-2"
        :isCheckBox="true"
        checkboxKey="id"
        @onSelectRow="(items: Record<string, boolean>) => { selectedIds = items; emits('selectedRow', items) }">
        <template #cell(code)="{ item }">
            <span class="flex items-center gap-x-1.5 whitespace-nowrap font-medium">
                <span
                    v-if="dirtyCount(item)"
                    class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"
                    v-tooltip="ctrans(':count unsaved changes', { count: `${dirtyCount(item)}` })" />
                {{ item.code }}
            </span>
        </template>

        <template #cell(name)="{ item }">
            <!-- Info: like pricing, minus stock and sales; shops and composition under the name. -->
            <div class="flex items-start gap-x-2">
                <Image
                    v-if="item.image_thumbnail?.main?.thumbnail"
                    :src="item.image_thumbnail?.main?.thumbnail ?? ''"
                    class="mt-0.5 w-9 aspect-square shrink-0 rounded overflow-hidden shadow"
                />
                <div class="flex w-full min-w-[11rem] flex-col gap-y-2">
                    <div class="flex items-start gap-x-2">                        
                        <PureInput
                            classInput="!h-7 !text-sm"
                            :class="dirtyField(isFieldDirty(item, 'name'))"
                            :modelValue="fieldValue(item, 'name')"
                            :maxLength="250"
                            :disabled="isSavingEdits"
                            @update:modelValue="(value: any) => setFieldValue(item, 'name', value)" />
                        <FontAwesomeIcon
                            v-if="sameNameAs(item).length"
                            :icon="faExclamationTriangle"
                            class="mt-1.5 h-3 w-3 shrink-0 text-amber-500"
                            v-tooltip="ctrans('Same name as :codes', { codes: sameNameAs(item).join(', ') })" fixed-width />
                        <button
                            v-if="isFieldDirty(item, 'name')"
                            type="button"
                            :disabled="isSavingEdits"
                            @click="revertFields(item, ['name'])"
                            v-tooltip="ctrans('Undo')"
                            class="mt-1.5 text-amber-600 hover:text-amber-800">
                            <FontAwesomeIcon :icon="faUndoAlt" class="h-3 w-3" fixed-width />
                        </button>
                    </div>
                    <span class="px-1.5 text-xs text-gray-400">
                        {{ ctrans('In :n shops', { n: `${item.used_in ?? 0}` }) }}
                        <span
                            v-if="item.trade_units_label"
                            class="ml-1 whitespace-nowrap rounded border border-emerald-300 px-1 py-px text-emerald-700 tabular-nums"
                            v-tooltip="ctrans('Trade units')">
                            {{ item.trade_units_label }}
                        </span>
                    </span>
                </div>
            </div>
        </template>

        <template v-for="field in RICH_TEXT_FIELDS" :key="field" #[`cell(${field})`]="{ item }">
            <div class="flex items-start gap-x-1">
                <div
                    class="w-full min-w-[11rem] rounded-md px-1.5 py-1 ring-1 ring-transparent hover:ring-gray-300"
                    :class="[
                        dirtyField(isFieldDirty(item, field)),
                        activeEditorCell === cellKey(item, field) ? 'ring-indigo-400' : '',
                    ]">
                    <EditorV2
                        v-if="activeEditorCell === cellKey(item, field)"
                        :ref="bindEditor"
                        :modelValue="fieldValue(item, field)"
                        :toggle="RICH_TEXT_TOOLBAR"
                        :placeholder="ctrans('Click to write')"
                        class="min-h-[1.5rem] max-h-40 overflow-y-auto"
                        @update:modelValue="(value: string) => setFieldValue(item, field, value)" />
                    <div
                        v-else
                        @click="openEditor(item, field)"
                        class="rich-preview min-h-[1.5rem] max-h-24 cursor-text overflow-y-auto text-sm text-gray-700">
                        <div v-if="previewHtml(item, field)" v-html="previewHtml(item, field)" />
                        <span v-else class="text-gray-300">{{ ctrans('Click to write') }}</span>
                    </div>
                </div>
                <button
                    v-if="isFieldDirty(item, field)"
                    type="button"
                    :disabled="isSavingEdits"
                    @click="revertFields(item, [field])"
                    v-tooltip="ctrans('Undo')"
                    class="mt-1.5 text-amber-600 hover:text-amber-800">
                    <FontAwesomeIcon :icon="faUndoAlt" class="h-3 w-3" fixed-width />
                </button>
            </div>
        </template>

        <template #cell(units)="{ item }">
            <div class="flex items-start justify-center gap-x-2">
                <span
                    class="mt-1.5 w-10 shrink-0 text-right text-sm tabular-nums text-gray-500"
                    v-tooltip="ctrans('Units per outer, edited on the product page')">
                    {{ Number(item.units) }}
                </span>
                <PureInput
                    classInput="!h-7 !text-sm !w-24"
                    :class="dirtyField(isFieldDirty(item, 'unit'))"
                    :modelValue="fieldValue(item, 'unit')"
                    :placeholder="ctrans('unit')"
                    :disabled="isSavingEdits"
                    v-tooltip="ctrans('Unit label, for example piece or ball')"
                    @update:modelValue="(value: any) => setFieldValue(item, 'unit', value)" />
                <button
                    v-if="isFieldDirty(item, 'unit')"
                    type="button"
                    :disabled="isSavingEdits"
                    @click="revertFields(item, ['unit'])"
                    v-tooltip="ctrans('Undo')"
                    class="mt-1.5 text-amber-600 hover:text-amber-800">
                    <FontAwesomeIcon :icon="faUndoAlt" class="h-3 w-3" fixed-width />
                </button>
            </div>
        </template>

        <template #cell(tax_preset)="{ item }">
            <div class="flex justify-end">
                <!-- While this row's sweep runs (or just finished), the cell carries the progress. -->
                <div
                    v-if="sweptIds.includes(item.id) && sweepProgress"
                    @click="!sweepRunning && (sweptIds = [])"
                    class="w-48 rounded-lg border px-3 py-1.5"
                    :class="[sweepRunning ? 'border-indigo-200 bg-indigo-50' : 'border-green-200 bg-green-50 cursor-pointer hover:bg-green-100']"
                    v-tooltip="sweepRunning ? undefined : ctrans('Click to dismiss')">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="flex items-center gap-1" :class="sweepRunning ? 'text-indigo-700' : 'text-green-700'">
                            <template v-if="sweptTransitions[item.id]">
                                <FontAwesomeIcon :icon="taxPresetIcon(sweptTransitions[item.id].from)" class="h-3 w-3" fixed-width />
                                <FontAwesomeIcon :icon="faArrowRight" class="h-2.5 w-2.5 text-gray-400" fixed-width />
                                <FontAwesomeIcon :icon="taxPresetIcon(sweptTransitions[item.id].to)" class="h-3 w-3" fixed-width />
                            </template>
                            {{ sweepRunning ? ctrans("Retaxing…") : ctrans("Done") }}
                        </span>
                        <span class="tabular-nums text-gray-600">{{ sweepProgress.baskets_done }}/{{ sweepProgress.baskets_total }}</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-white overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-300"
                            :class="sweepRunning ? 'bg-indigo-500' : 'bg-green-500'"
                            :style="{ width: sweepPct + '%' }" />
                    </div>
                </div>

                <!-- Mini preset card: same visual language as the edit page, click to change. -->
                <button
                    v-else
                    type="button"
                    @click="openForRow(item)"
                    :disabled="sweepRunning"
                    class="group inline-flex w-48 items-center gap-2 rounded-lg border border-gray-300 bg-white py-1.5 pl-1.5 pr-3 shadow-sm transition-all hover:bg-gray-50 hover:border-indigo-300 disabled:opacity-60 disabled:cursor-not-allowed">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-gray-500 group-hover:bg-indigo-100 group-hover:text-indigo-600">
                        <FontAwesomeIcon :icon="presetMeta(item.tax_preset).icon" class="h-3.5 w-3.5" fixed-width />
                    </span>
                    <span class="flex-1 text-left text-sm text-gray-700">{{ presetMeta(item.tax_preset).title }}</span>
                    <FontAwesomeIcon :icon="faPencil" class="h-3 w-3 text-gray-300 group-hover:text-indigo-500" fixed-width />
                </button>
            </div>
        </template>
    </Table>
    </div>

    <TaxPresetEditModal
        :isOpen="!!editingItems.length"
        :options="taxPresetOptions ?? []"
        :initial="modalInitial"
        :subjectLabel="modalSubject"
        :isSaving="isSaving"
        :phase="modalPhase"
        :progress="sweepProgress"
        :fromPreset="sweepFromPreset"
        :toPreset="sweepToPreset"
        @close="editingItems = []"
        @save="onSave" />

    <TaxSweepProgressModal
        :masterAssetId="sweepMasterAssetId"
        :autoOpen="false"
        :mini="false"
        @progress="sweepProgress = $event"
        @update:running="sweepRunning = $event" />

    <Dialog
        :visible="!!quantityRows.length"
        @update:visible="(visible: boolean) => { if (!visible && !quantitySaving) quantityRows = [] }"
        modal
        :closable="!quantitySaving"
        :header="ctrans('Set trade unit quantity to :units', { units: quantityForSelection })"
        :style="{ width: '52rem' }">
        <div class="space-y-3 text-sm">
            <div class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-amber-800">
                {{ ctrans('This changes what each product physically is: how many SKOs the warehouse picks per outer, in every shop that follows the master. The price does not change by itself, so the price per unit does. Only use it to fix a wrong composition; a product being repacked is edited one by one on its composition page.') }}
            </div>

            <div v-if="openOrdersByMasterAsset === null" class="py-6 text-center text-gray-500">
                <FontAwesomeIcon icon="fad fa-spinner-third" class="animate-spin" fixed-width />
                {{ ctrans('Checking open orders') }}
            </div>

            <table v-else class="w-full">
                <thead class="text-left text-xs text-gray-500">
                    <tr>
                        <th class="py-1">{{ ctrans('Product') }}</th>
                        <th class="py-1 text-right">{{ ctrans('Units') }}</th>
                        <th class="py-1 text-right">{{ ctrans('Price') }}</th>
                        <th class="py-1 text-right">{{ ctrans('Per unit') }}</th>
                        <th class="py-1"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in quantityPreview" :key="row.item.id" :class="row.skipReason ? 'text-gray-400' : ''">
                        <td class="py-1 font-medium">{{ row.item.code }}</td>
                        <td class="py-1 text-right tabular-nums">{{ row.oldUnits }} → {{ row.newUnits }}</td>
                        <td class="py-1 text-right tabular-nums">{{ row.price.toFixed(2) }}</td>
                        <td class="py-1 text-right tabular-nums">
                            {{ (row.price / row.oldUnits).toFixed(2) }} → {{ (row.price / row.newUnits).toFixed(2) }}
                        </td>
                        <td class="py-1 pl-3 text-xs">
                            <span v-if="row.skipReason">{{ ctrans('Skipped') }}: {{ row.skipReason }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="openOrdersByMasterAsset !== null" class="space-y-2 border-t border-gray-200 pt-3">
                <div class="font-medium">
                    {{ ctrans(':count products will change, :skipped skipped', { count: `${quantityToApply.length}`, skipped: `${quantityPreview.length - quantityToApply.length}` }) }}
                </div>
                <template v-if="quantityToApply.length">
                    <label class="block text-gray-700">
                        {{ ctrans('To apply it, type: :phrase', { phrase: RESPONSIBILITY_PHRASE }) }}
                    </label>
                    <PureInput v-model="responsibilityTyped" :disabled="quantitySaving" :placeholder="RESPONSIBILITY_PHRASE" />
                </template>
            </div>
        </div>

        <template #footer>
            <div class="flex items-center justify-end gap-2">
                <span v-if="quantitySaving" class="text-sm text-gray-500">
                    {{ ctrans(':done of :total', { done: `${quantitySavedCount}`, total: `${quantitySavingTotal}` }) }}
                </span>
                <Button :label="ctrans('Cancel')" type="tertiary" :disabled="quantitySaving" @click="quantityRows = []" />
                <Button
                    :label="ctrans('Change :count products', { count: `${quantityToApply.length}` })"
                    type="negative"
                    :loading="quantitySaving"
                    :disabled="!quantityToApply.length || !isResponsibilityAccepted"
                    @click="applyQuantity" />
            </div>
        </template>
    </Dialog>
</template>

<style scoped>
.rich-preview :deep(ul),
.rich-preview :deep(ol) {
    @apply ml-4 list-disc;
}

.rich-preview :deep(ol) {
    @apply list-decimal;
}

.rich-preview :deep(p) {
    @apply m-0;
}
</style>
