<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { ref, computed, watch } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import Table from "@/Components/Table/Table.vue"
import Image from "@common/Components/Image.vue"
import TaxPresetEditModal from "@/Components/Utils/TaxPresetEditModal.vue"
import TaxSweepProgressModal from "@/Components/Utils/TaxSweepProgressModal.vue"
import { notify } from "@kyvg/vue3-notification"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPercent, faHamburger, faFlowerTulip, faPencil, faArrowRight } from "@fal"
import { taxPresetIcon } from "@/Composables/taxPresets"

library.add(faPercent, faHamburger, faFlowerTulip, faPencil, faArrowRight)

// The bulk edit tab: fields that make sense across many rows, tax first. Each row shows a
// mini version of the preset card; clicking it, or editing a whole selection, opens the
// same card picker the product edit page uses.
const props = defineProps<{
    data: any
    tab?: string
    taxPresetOptions?: { value: string; title: string; description?: string }[]
    taxBulkSignal?: number
}>()

const emits = defineEmits<{
    (e: 'selectedRow', value: Record<string, boolean>): void
}>()

const PRESET_META: Record<string, { icon: any; title: string }> = {
    standard: { icon: faPercent, title: trans('Standard rate') },
    food: { icon: faHamburger, title: trans('Food') },
    dried_flowers: { icon: faFlowerTulip, title: trans('Dried flowers') },
    custom: { icon: faPercent, title: trans('Custom') },
}

const presetMeta = (value: string) => PRESET_META[value] ?? PRESET_META.standard

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
            <span class="whitespace-nowrap font-medium">{{ item.code }}</span>
        </template>

        <template #cell(name)="{ item }">
            <!-- Info: like pricing, minus stock and sales; shops and composition on row two. -->
            <div class="flex items-start gap-x-2">
                <Image
                    v-if="item.image_thumbnail"
                    :src="item.image_thumbnail"
                    class="mt-0.5 w-9 aspect-square shrink-0 rounded overflow-hidden shadow"
                />
                <div class="flex flex-col gap-y-0.5">
                    <span class="font-medium">{{ item.name }}</span>
                    <span class="text-xs text-gray-400">
                        {{ trans('In :n shops', { n: `${item.used_in ?? 0}` }) }}
                        <span
                            v-if="item.trade_units_label"
                            class="ml-1 whitespace-nowrap rounded border border-emerald-300 px-1 py-px text-emerald-700 tabular-nums"
                            v-tooltip="trans('Trade units')">
                            {{ item.trade_units_label }}
                            <span class="text-gray-600">| {{ item.units }} {{ item.unit }}</span>
                        </span>
                    </span>
                </div>
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
