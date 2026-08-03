<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { ref, computed, watch, nextTick, defineAsyncComponent } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import Table from "@/Components/Table/Table.vue"
import Image from "@common/Components/Image.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureInputNumber from "@/Components/Pure/PureInputNumber.vue"
import TaxPresetEditModal from "@/Components/Utils/TaxPresetEditModal.vue"
import TaxSweepProgressModal from "@/Components/Utils/TaxSweepProgressModal.vue"
import { notify } from "@kyvg/vue3-notification"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPercent, faHamburger, faFlowerTulip, faPencil, faArrowRight, faUndoAlt } from "@fal"
import { taxPresetIcon } from "@/Composables/taxPresets"

library.add(faPercent, faHamburger, faFlowerTulip, faPencil, faArrowRight, faUndoAlt)

// The bulk edit tab: fields that make sense across many rows, tax first. Text and units are
// edited straight in the cell like a spreadsheet; tax opens the same card picker the product
// edit page uses, for one row or for the whole selection.
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
    standard: { icon: faPercent, title: trans('Standard rate') },
    food: { icon: faHamburger, title: trans('Food') },
    dried_flowers: { icon: faFlowerTulip, title: trans('Dried flowers') },
    custom: { icon: faPercent, title: trans('Custom') },
}

const presetMeta = (value: string) => PRESET_META[value] ?? PRESET_META.standard

// Every editable cell is an input from the start, spreadsheet style. Nothing is sent until the
// page-head save button fires; until then a touched field stays amber and keeps its old value
// one undo away, keyed by product id so edits survive paging away and back.
type EditableField = 'name' | 'description' | 'description_extra' | 'units' | 'unit'

const pendingEdits = ref<Record<number, { code: string; fields: Partial<Record<EditableField, any>> }>>({})
const isSavingEdits = ref(false)

/** units is numeric(,3) in the database, so it arrives as "1.000" and shows as 1, or 0.5. */
const fieldValue = (item: any, field: EditableField): any => {
    const value = pendingEdits.value[item.id]?.fields[field] ?? item[field] ?? ''

    return field === 'units' && value !== '' ? Number(value) : value
}

const isFieldDirty = (item: any, field: EditableField): boolean =>
    pendingEdits.value[item.id]?.fields[field] !== undefined

const dirtyField = (isDirty: boolean): string => isDirty ? '!bg-amber-50 !ring-amber-400' : ''

const dirtyCount = (item: any): number => Object.keys(pendingEdits.value[item.id]?.fields ?? {}).length

const setFieldValue = (item: any, field: EditableField, value: any) => {
    const original = item[field] ?? ''
    const edit = pendingEdits.value[item.id] ?? { code: item.code, fields: {} }

    /** "1" typed over a stored "1.000" is not a change, so units compare as numbers. */
    const isUnchanged = field === 'units'
        ? Number(value) === Number(original)
        : String(value ?? '') === String(original)

    if (isUnchanged) {
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

const EditorV2 = defineAsyncComponent(() => import("@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"))

const RICH_TEXT_FIELDS: EditableField[] = ['description', 'description_extra']
const RICH_TEXT_TOOLBAR = [
    'bold', 'italic', 'underline', 'bulletList', 'orderedList',
    'link', 'alignLeft', 'alignCenter', 'alignRight', 'clear', 'undo', 'redo',
]

const activeEditorCell = ref<string | null>(null)
const activeEditor = ref<any>(null)

const cellKey = (item: any, field: EditableField): string => `${item.id}:${field}`

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

    const entries = Object.entries(pendingEdits.value)
    if (!entries.length) return

    isSavingEdits.value = true
    const failures: string[] = []

    for (const [id, edit] of entries) {
        try {
            await axios.patch(
                route('grp.models.master_asset.update', { masterAsset: Number(id) }),
                edit.fields,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            )

            const row = props.data?.data?.find((item: any) => item.id === Number(id))
            if (row) {
                Object.assign(row, edit.fields)
            }
            delete pendingEdits.value[Number(id)]
        } catch (error: any) {
            const errors = error?.response?.data?.errors
            failures.push(`${edit.code}: ${errors ? Object.values(errors).flat().join(' ') : error?.response?.data?.message}`)
        }
    }

    isSavingEdits.value = false

    if (failures.length) {
        notify({
            title: trans("Something went wrong"),
            text: failures.join(' · '),
            type: "error",
        })

        return
    }

    notify({
        title: trans("Success"),
        text: trans(':count products updated', { count: String(entries.length) }),
        type: "success",
    })
}

watch(() => props.bulkEditSaveSignal, () => saveEdits())

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
        : trans(':count products', { count: String(editingItems.value.length) }))

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
            title: trans("Something went wrong"),
            text: error?.response?.data?.message ?? '',
            type: "error",
        })
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
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
                    v-tooltip="trans(':count unsaved changes', { count: `${dirtyCount(item)}` })" />
                {{ item.code }}
            </span>
        </template>

        <template #cell(name)="{ item }">
            <!-- Info: like pricing, minus stock and sales; shops and composition under the name. -->
            <div class="flex items-start gap-x-2">
                <Image
                    v-if="item.image_thumbnail"
                    :src="item.image_thumbnail"
                    class="mt-0.5 w-9 aspect-square shrink-0 rounded overflow-hidden shadow"
                />
                <div class="flex min-w-[14rem] flex-col gap-y-2">
                    <div class="flex items-start gap-x-2">
                        <PureInput
                            class="cell-input"
                            :class="dirtyField(isFieldDirty(item, 'name'))"
                            :modelValue="fieldValue(item, 'name')"
                            :maxLength="250"
                            :disabled="isSavingEdits"
                            @update:modelValue="(value: any) => setFieldValue(item, 'name', value)" />
                        <button
                            v-if="isFieldDirty(item, 'name')"
                            type="button"
                            :disabled="isSavingEdits"
                            @click="revertFields(item, ['name'])"
                            v-tooltip="trans('Undo')"
                            class="mt-1.5 text-amber-600 hover:text-amber-800">
                            <FontAwesomeIcon :icon="faUndoAlt" class="h-3 w-3" />
                        </button>
                    </div>
                    <span class="px-1.5 text-xs text-gray-400">
                        {{ trans('In :n shops', { n: `${item.used_in ?? 0}` }) }}
                        <span
                            v-if="item.trade_units_label"
                            class="ml-1 whitespace-nowrap rounded border border-emerald-300 px-1 py-px text-emerald-700 tabular-nums"
                            v-tooltip="trans('Trade units')">
                            {{ item.trade_units_label }}
                        </span>
                    </span>
                </div>
            </div>
        </template>

        <template v-for="field in RICH_TEXT_FIELDS" :key="field" #[`cell(${field})`]="{ item }">
            <div class="flex items-start gap-x-1">
                <div
                    class="w-full min-w-[16rem] rounded-md px-1.5 py-1 ring-1 ring-transparent hover:ring-gray-300"
                    :class="[
                        dirtyField(isFieldDirty(item, field)),
                        activeEditorCell === cellKey(item, field) ? 'ring-indigo-400' : '',
                    ]">
                    <EditorV2
                        v-if="activeEditorCell === cellKey(item, field)"
                        :ref="bindEditor"
                        :modelValue="fieldValue(item, field)"
                        :toggle="RICH_TEXT_TOOLBAR"
                        :placeholder="trans('Click to write')"
                        class="max-h-40 overflow-y-auto"
                        @update:modelValue="(value: string) => setFieldValue(item, field, value)" />
                    <div
                        v-else
                        @click="openEditor(item, field)"
                        class="rich-preview max-h-24 cursor-text overflow-y-auto text-sm text-gray-700">
                        <div v-if="fieldValue(item, field)" v-html="fieldValue(item, field)" />
                        <span v-else class="text-gray-300">{{ trans('Click to write') }}</span>
                    </div>
                </div>
                <button
                    v-if="isFieldDirty(item, field)"
                    type="button"
                    :disabled="isSavingEdits"
                    @click="revertFields(item, [field])"
                    v-tooltip="trans('Undo')"
                    class="mt-1.5 text-amber-600 hover:text-amber-800">
                    <FontAwesomeIcon :icon="faUndoAlt" class="h-3 w-3" />
                </button>
            </div>
        </template>

        <template #cell(units)="{ item }">
            <div class="flex items-start gap-x-2">
                <PureInputNumber
                    class="cell-input !w-24"
                    :class="dirtyField(isFieldDirty(item, 'units'))"
                    :modelValue="fieldValue(item, 'units')"
                    :minValue="0"
                    :disabled="isSavingEdits"
                    v-tooltip="trans('Units per outer, also applied to the shop products')"
                    @update:modelValue="(value: number) => Number.isFinite(value) && setFieldValue(item, 'units', value)" />
                <PureInput
                    class="cell-input !w-24"
                    :class="dirtyField(isFieldDirty(item, 'unit'))"
                    :modelValue="fieldValue(item, 'unit')"
                    :placeholder="trans('unit')"
                    :disabled="isSavingEdits"
                    v-tooltip="trans('Unit label, for example piece or ball')"
                    @update:modelValue="(value: any) => setFieldValue(item, 'unit', value)" />
                <button
                    v-if="isFieldDirty(item, 'units') || isFieldDirty(item, 'unit')"
                    type="button"
                    :disabled="isSavingEdits"
                    @click="revertFields(item, ['units', 'unit'])"
                    v-tooltip="trans('Undo')"
                    class="mt-1.5 text-amber-600 hover:text-amber-800">
                    <FontAwesomeIcon :icon="faUndoAlt" class="h-3 w-3" />
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
                    v-tooltip="sweepRunning ? undefined : trans('Click to dismiss')">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="flex items-center gap-1" :class="sweepRunning ? 'text-indigo-700' : 'text-green-700'">
                            <template v-if="sweptTransitions[item.id]">
                                <FontAwesomeIcon :icon="taxPresetIcon(sweptTransitions[item.id].from)" class="h-3 w-3" />
                                <FontAwesomeIcon :icon="faArrowRight" class="h-2.5 w-2.5 text-gray-400" />
                                <FontAwesomeIcon :icon="taxPresetIcon(sweptTransitions[item.id].to)" class="h-3 w-3" />
                            </template>
                            {{ sweepRunning ? trans("Retaxing…") : trans("Done") }}
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
                        <FontAwesomeIcon :icon="presetMeta(item.tax_preset).icon" class="h-3.5 w-3.5" />
                    </span>
                    <span class="flex-1 text-left text-sm text-gray-700">{{ presetMeta(item.tax_preset).title }}</span>
                    <FontAwesomeIcon :icon="faPencil" class="h-3 w-3 text-gray-300 group-hover:text-indigo-500" />
                </button>
            </div>
        </template>
    </Table>

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
</template>

<style scoped>
/* The row is one line of a spreadsheet, so the fields lose the form-sized padding. */
.cell-input :deep(input) {
    @apply py-1 text-sm;
}

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
