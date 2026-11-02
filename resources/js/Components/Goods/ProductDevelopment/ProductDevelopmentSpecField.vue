<!--
  - Author Louis Perez
  - Created on 17-10-2026-16h-00m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import type { RequestPayload } from "@inertiajs/core"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import Textarea from "primevue/textarea"
import Select from "primevue/select"
import Popover from "primevue/popover"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import ProductDevelopmentFactoryPhotos from "@/Components/Goods/ProductDevelopment/ProductDevelopmentFactoryPhotos.vue"
import ProductDevelopmentSpecGroup from "@/Components/Goods/ProductDevelopment/ProductDevelopmentSpecGroup.vue"
import { formatChecklistValue, type ChecklistDimensions, type ChecklistGroupOptions, type ChecklistMaterial, type ChecklistRow } from "@/Components/Goods/ProductDevelopment/productDevelopmentChecklist"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSpinner, faPencil, faTimes, faInfoCircle, faPlus } from "@fal"
import { faAsterisk, faSave } from "@fas"
library.add(faSpinner, faPencil, faTimes, faInfoCircle, faAsterisk, faSave, faPlus)

const props = defineProps<{
    row: ChecklistRow
    updateRoute: string
    countries: { value: number, label: string }[]
    options?: ChecklistGroupOptions
    hideLabel?: boolean
}>()

const draft = ref<string | number | null>(null)
const dimensions = ref<ChecklistDimensions>({ units: "cm" })
const saving = ref(false)
const error = ref<string | null>(null)
const naReason = ref("")
const naPopover = ref<InstanceType<typeof Popover> | null>(null)

const editing = ref(false)
const materials = ref<ChecklistMaterial[]>([])
const groupDraft = ref<Record<string, any>>({})
const isGroup = computed(() => props.row.input_type === "pictograms" || props.row.input_type === "label_marks" || props.row.input_type === "ingredients")
const groupType = computed(() => props.row.input_type as "pictograms" | "label_marks" | "ingredients")
const ingredientList = computed(() => (Array.isArray(props.row.value) ? props.row.value : []) as { slug: string, name: string }[])
const isMissing = computed(() => props.row.state === "missing")
const displayValue = computed(() => formatChecklistValue(props.row, props.countries))
const showInput = computed(() => isMissing.value || editing.value)
const isNotApplicable = computed(() => props.row.state === "not_applicable")
const editable = computed(() => props.row.can_edit && !props.row.needs_supplier_product && !isNotApplicable.value)

function reset() {
    const value = props.row.value
    if (isGroup.value) {
        groupDraft.value = props.row.input_type === "ingredients"
            ? { ingredients: ingredientList.value.map((ingredient) => ingredient.slug), errors: {}, recentlySuccessful: false }
            : JSON.parse(JSON.stringify(value ?? {}))
        return
    }
    if (props.row.input_type === "materials") {
        const current = (Array.isArray(value) ? value : []) as ChecklistMaterial[]
        materials.value = current.length ? current.map((line) => ({ ...line })) : [{ material: "", percentage: null }]
        return
    }
    if (props.row.input_type === "dimensions") {
        const current = ((value && typeof value === "object" && !Array.isArray(value)) ? value : {}) as ChecklistDimensions
        dimensions.value = { l: current.l ?? null, w: current.w ?? null, h: current.h ?? null, units: current.units ?? "cm" }
    } else if (props.row.input_type === "integer" || props.row.input_type === "decimal" || props.row.input_type === "country") {
        draft.value = value === null || value === "" ? null : Number(value)
    } else {
        draft.value = (value as string | null) ?? ""
    }
}

reset()
watch(() => props.row, () => {
    reset()
    editing.value = false
}, { deep: true })

function startEditing() {
    reset()
    error.value = null
    editing.value = true
}

function cancelEditing() {
    reset()
    error.value = null
    editing.value = false
}

function send(payload: RequestPayload, onSuccess?: () => void) {
    error.value = null
    router.patch(route(props.updateRoute, { productDevelopmentChecklistItem: props.row.id }), payload, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => saving.value = true,
        onFinish: () => saving.value = false,
        onError: (errors) => error.value = Object.values(errors)[0] ?? null,
        onSuccess: () => onSuccess?.(),
    })
}

function currentPayloadValue(): unknown {
    if (isGroup.value) {
        return props.row.input_type === "ingredients" ? (groupDraft.value.ingredients ?? []) : groupDraft.value
    }
    if (props.row.input_type === "materials") {
        const lines = materials.value.filter((line) => line.material.trim() !== "").map((line) => ({ material: line.material.trim(), percentage: line.percentage }))
        return lines.length ? lines : null
    }
    if (props.row.input_type === "dimensions") {
        const sizes = [dimensions.value.l, dimensions.value.w, dimensions.value.h]
        if (!sizes.every((size) => Number(size ?? 0) > 0)) {
            return null
        }
        return { ...dimensions.value, type: "rectangular" }
    }
    if (draft.value === null || String(draft.value).trim() === "") {
        return null
    }
    return typeof draft.value === "string" ? draft.value.trim() : draft.value
}

function commitOnBlur() {
    if (isMissing.value) {
        commit(true)
    }
}

function commit(fromBlur = false) {
    if (!editable.value || saving.value) {
        return
    }
    const value = currentPayloadValue()
    if (value === null) {
        if (fromBlur) {
            return
        }
        error.value = props.row.input_type === "dimensions" ? ctrans("Fill in width, length and height first") : props.row.input_type === "materials" ? ctrans("Add at least one material") : ctrans("Fill this in first")
        return
    }
    error.value = null
    if (isGroup.value) {
        const original = props.row.input_type === "ingredients" ? JSON.stringify(ingredientList.value.map((ingredient) => ingredient.slug)) : JSON.stringify(props.row.value ?? {})
        if (original === JSON.stringify(value) && !isMissing.value) {
            editing.value = false
            return
        }
        send({ operation: "save", value } as RequestPayload, () => editing.value = false)
        return
    }
    const original = props.row.input_type === "materials"
        ? JSON.stringify(props.row.value ?? [])
        : props.row.input_type === "dimensions"
        ? JSON.stringify([Number((props.row.value as ChecklistDimensions | null)?.l ?? 0), Number((props.row.value as ChecklistDimensions | null)?.w ?? 0), Number((props.row.value as ChecklistDimensions | null)?.h ?? 0), (props.row.value as ChecklistDimensions | null)?.units ?? "cm"])
        : String(props.row.value ?? "")
    const next = props.row.input_type === "materials"
        ? JSON.stringify(value)
        : props.row.input_type === "dimensions"
        ? JSON.stringify([Number(dimensions.value.l), Number(dimensions.value.w), Number(dimensions.value.h), dimensions.value.units ?? "cm"])
        : String(value)
    if (original === next && !isMissing.value) {
        editing.value = false
        return
    }
    send({ operation: "save", value } as RequestPayload, () => editing.value = false)
}

function markNotApplicable() {
    if (naReason.value.trim() === "") {
        return
    }
    send({ operation: "not_applicable", na_reason: naReason.value }, () => {
        naPopover.value?.hide()
        naReason.value = ""
    })
}

const reopen = () => send({ operation: "reopen" })

const destination = computed(() => props.row.writes_to === "supplier_product" ? ctrans("goes to supplier product") : props.row.writes_to === "trade_unit" ? ctrans("goes to trade unit") : null)

const savedLine = computed(() => {
    if (!props.row.saved_at) {
        return props.row.writes_to === "supplier_product" ? ctrans("On the supplier product") : props.row.writes_to === "trade_unit" ? ctrans("On the trade unit") : null
    }
    const when = useFormatTime(props.row.saved_at, { formatTime: "d MMM" })
    return props.row.saved_by ? ctrans("Saved :date by :user", { date: when, user: props.row.saved_by }) : ctrans("Saved :date", { date: when })
})

const unitLabel = computed(() => props.row.unit ?? props.row.currency)
const missingClass = computed(() => isMissing.value && editable.value && props.row.required ? "!border-orange-300 !bg-orange-50/60" : "")
</script>

<template>
    <div :id="`pd-check-${row.id}`" tabindex="-1" class="min-w-0 scroll-mt-24 space-y-1 focus:outline-none" @keydown.esc="editing && cancelEditing()">
        <div class="flex items-center justify-between gap-2" :class="hideLabel && !saving ? 'sr-only' : ''">
            <label class="flex min-w-0 items-center gap-1 text-sm text-gray-700" :class="hideLabel ? 'sr-only' : ''" :for="`spec-${row.id}`">
                <span class="truncate">{{ row.label }}<template v-if="row.input_type === 'dimensions'"> W × L × H</template></span>
                <FontAwesomeIcon v-if="row.required" icon="fas fa-asterisk" class="shrink-0 text-[9px] text-red-500" :aria-label="ctrans('Required')" />
                <span v-else class="shrink-0 text-xs text-gray-400">({{ ctrans("optional") }})</span>
                <FontAwesomeIcon v-if="row.information" v-tooltip="row.information" icon="fal fa-info-circle" class="shrink-0 cursor-help text-xs text-gray-400" fixed-width :aria-label="row.information" />
            </label>
            <FontAwesomeIcon v-if="saving" icon="fal fa-spinner" spin class="shrink-0 text-xs text-gray-400" fixed-width aria-hidden="true" />
        </div>

        <div v-if="isNotApplicable" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">{{ ctrans("Not applicable") }}</div>

        <ProductDevelopmentFactoryPhotos v-else-if="row.input_type === 'photos'" :value="row.value as any" :editable="row.can_edit" @error="(message) => error = message" />

        <template v-else-if="isGroup">
            <div v-if="!showInput" class="flex items-start gap-2 rounded-md bg-gray-50 px-3 py-2">
                <ProductDevelopmentSpecGroup v-model="groupDraft" :type="groupType" :options="options ?? {}" :ingredientOptions="ingredientList" readonly class="min-w-0 flex-1" />
                <button
                    v-if="editable"
                    type="button"
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-[--app-accent] transition-colors hover:bg-[--app-accent-soft] hover:text-[--app-accent-strong]"
                    :aria-label="ctrans('Edit :field', { field: row.label })"
                    @click="startEditing"
                >
                    <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                </button>
            </div>
            <template v-else>
                <div class="rounded-md border p-3" :class="isMissing && row.required ? 'border-orange-300 bg-orange-50/40' : 'border-gray-200'">
                    <ProductDevelopmentSpecGroup v-model="groupDraft" :type="groupType" :options="options ?? {}" :ingredientOptions="ingredientList" :readonly="!editable" />
                </div>
                <div v-if="editable" class="flex justify-end gap-1.5">
                    <Button v-if="editing" type="negative" size="xs" icon="fal fa-times" :label="ctrans('Cancel')" @click="cancelEditing" />
                    <Button type="primary" size="xs" icon="fas fa-save" :label="isMissing ? ctrans('Confirm') : ctrans('Save')" :tooltip="isMissing ? ctrans('Confirm') : ctrans('Save')" hide_label :loading="saving" @click="commit()" />
                </div>
            </template>
        </template>

        <div v-else-if="!showInput" class="flex min-h-[2.6rem] items-center gap-2 rounded-md border border-transparent bg-gray-50 px-3 py-2">
            <span class="min-w-0 flex-1 whitespace-pre-line break-words text-sm text-gray-900">{{ displayValue ?? "—" }}</span>
            <button
                v-if="editable"
                type="button"
                class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-[--app-accent] transition-colors hover:bg-[--app-accent-soft] hover:text-[--app-accent-strong]"
                :aria-label="ctrans('Edit :field', { field: row.label })"
                @click="startEditing"
            >
                <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
            </button>
        </div>

        <template v-else>
            <div class="flex items-center gap-1.5">
                <div class="min-w-0 flex-1">
                    <div v-if="row.input_type === 'materials'" class="space-y-1.5">
                        <div v-for="(line, index) in materials" :key="index" class="flex items-center gap-1.5">
                            <InputText v-model="line.material" :id="index === 0 ? `spec-${row.id}` : undefined" :disabled="!editable" :placeholder="ctrans('e.g. Mango wood')" size="small" class="min-w-0 flex-1" :class="missingClass" />
                            <InputNumber v-model="line.percentage" :disabled="!editable" :min="0" :max="100" :maxFractionDigits="2" suffix="%" :placeholder="ctrans('% (optional)')" size="small" class="w-32 shrink-0" inputClass="w-full" />
                            <button v-if="editable && materials.length > 1" type="button" class="flex h-7 w-7 shrink-0 items-center justify-center rounded text-red-500 hover:bg-red-50" :aria-label="ctrans('Remove')" @click="materials.splice(index, 1)">
                                <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                            </button>
                        </div>
                        <button v-if="editable" type="button" class="flex items-center gap-1 text-xs text-[--app-accent] hover:underline" @click="materials.push({ material: '', percentage: null })">
                            <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                            {{ ctrans("Add material") }}
                        </button>
                    </div>

                    <div v-else-if="row.input_type === 'dimensions'" class="flex items-center gap-1.5">
                        <InputNumber v-for="side in (['w', 'l', 'h'] as const)" :key="side" v-model="dimensions[side]" :inputId="side === 'w' ? `spec-${row.id}` : undefined" :disabled="!editable" :min="0" :maxFractionDigits="2" :placeholder="side.toUpperCase()" size="small" class="min-w-0 flex-1" :inputClass="['w-full', missingClass]" @blur="commitOnBlur" @keydown.enter="commit()" />
                        <Select v-model="dimensions.units" :options="['mm', 'cm']" :disabled="!editable" size="small" class="w-24 shrink-0" overlayClass="text-sm" @change="commitOnBlur" />
                    </div>

                    <Select v-else-if="row.input_type === 'country'" v-model="draft" :inputId="`spec-${row.id}`" :options="countries" optionLabel="label" optionValue="value" filter :disabled="!editable" :placeholder="editable ? (row.placeholder ?? '') : '—'" class="w-full" :class="missingClass" overlayClass="text-sm" @change="commitOnBlur" />

                    <Textarea v-else-if="row.input_type === 'textarea'" :id="`spec-${row.id}`" v-model="draft as string" :disabled="!editable" rows="1" autoResize :placeholder="editable ? (row.placeholder ?? '') : '—'" class="w-full text-sm" :class="missingClass" @blur="commitOnBlur" />

                    <InputText v-else-if="row.input_type === 'text'" :id="`spec-${row.id}`" v-model="draft as string" :disabled="!editable" :placeholder="editable ? (row.placeholder ?? '') : '—'" class="w-full" :class="missingClass" @blur="commitOnBlur" @keydown.enter="commit()" />

                    <div v-else class="flex items-center gap-2">
                        <InputNumber
                            v-model="draft as number | null"
                            :inputId="`spec-${row.id}`"
                            :disabled="!editable"
                            :min="0"
                            :useGrouping="false"
                            :maxFractionDigits="row.input_type === 'decimal' ? 4 : 0"
                            :placeholder="editable ? (row.placeholder ?? '') : '—'"
                            class="min-w-0 flex-1"
                            :inputClass="['w-full', missingClass]"
                            @blur="commitOnBlur"
                            @keydown.enter="commit()"
                        />
                        <span v-if="unitLabel" class="shrink-0 text-sm text-gray-500">{{ unitLabel }}</span>
                    </div>
                </div>
                <Button v-if="editable && !editing" type="primary" size="s" icon="fas fa-save" :label="ctrans('Save')" :tooltip="ctrans('Save')" hide_label :loading="saving" class="shrink-0" @click="commit()" />
            </div>

            <div v-if="editing" class="flex justify-end gap-1.5">
                <Button type="negative" size="xs" icon="fal fa-times" :label="ctrans('Cancel')" @click="cancelEditing" />
                <Button type="primary" size="xs" icon="fas fa-save" :label="ctrans('Save')" :tooltip="ctrans('Save')" hide_label :loading="saving" @click="commit()" />
            </div>
        </template>

        <div class="flex min-h-4 flex-wrap items-center gap-x-2 text-xs">
            <span v-if="error" class="text-red-600">{{ error }}</span>
            <template v-else-if="isNotApplicable">
                <span class="text-gray-500">{{ ctrans("N/A") }}: {{ row.na_reason }}</span>
                <button v-if="row.can_edit" type="button" class="text-[--app-accent] hover:text-[--app-accent-strong]" @click="reopen">{{ ctrans("Undo") }}</button>
            </template>
            <span v-else-if="row.needs_supplier_product" class="text-amber-700">{{ ctrans("Link a supplier product first") }}</span>
            <span v-else-if="isMissing && !row.required" class="text-gray-500">{{ ctrans("Optional") }}<template v-if="destination"> · {{ destination }}</template></span>
            <span v-else-if="isMissing" class="text-orange-700">{{ ctrans("Missing") }}<template v-if="destination"> · {{ destination }}</template></span>
            <span v-else class="text-gray-500">{{ savedLine }}</span>
            <button v-if="isMissing && row.can_edit" type="button" class="ml-auto text-gray-500 hover:text-[--app-accent]" @click="(event) => naPopover?.toggle(event)">{{ ctrans("Not applicable?") }}</button>
        </div>

        <Popover ref="naPopover">
            <div class="w-64 space-y-2">
                <Textarea v-model="naReason" rows="2" autoResize class="w-full text-sm" :placeholder="ctrans('Why does this not apply?')" />
                <div class="flex justify-end">
                    <Button type="primary" size="xs" icon="fal fa-ban" :label="ctrans('Mark not applicable')" :disabled="naReason.trim() === ''" :loading="saving" class="disabled:cursor-not-allowed disabled:opacity-50" @click="markNotApplicable" />
                </div>
            </div>
        </Popover>
    </div>
</template>
