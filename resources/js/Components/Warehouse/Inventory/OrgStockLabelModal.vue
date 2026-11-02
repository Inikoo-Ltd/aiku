<!--
  - Author Louis Perez
  - Created on 23-09-2026-11h-34m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref, watch, onUnmounted } from "vue"
import { routeType } from "@/types/route"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import Checkbox from "primevue/checkbox"
import InputText from "primevue/inputtext"
import Textarea from "primevue/textarea"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRobot } from "@fal"
library.add(faRobot)

type LabelField = {
    key: string
    label: string
    available: boolean
    checked: boolean
    reason: string | null
    preview?: string | null
}

type LabelLevel = {
    key: string
    label: string
    printable: boolean
}

type LabelOptions = {
    sizes: Record<string, { key: string; label: string }[]>
    default_size: string
    layouts: Record<string, { key: string; label: string }[]>
    fields: Record<string, LabelField[]>
    levels: LabelLevel[]
    custom_text_max_length: number
    carton_organisations?: { id: number; code: string; name: string }[]
    carton_organisation_id?: number
    unified?: {
        batch: string | null
        batch_pattern: string
        levels: Record<string, { size: string; blockers: string[] }>
        translate_names?: { route: routeType; blockers: Record<string, string> } | null
    }
}

const props = defineProps<{
    isOpen: boolean
    level: string
    labelRoute: routeType
    options: LabelOptions
}>()

const emits = defineEmits<{ (e: "onClose"): void }>()

const standard = ref<"unified" | "localised">("unified")
const batch = ref("")

const isUnified = computed(() => standard.value === "unified" && !!props.options?.unified)

const standardOptions = computed(() => [
    { label: ctrans("Unified"), value: "unified" as const },
    { label: ctrans("Localised"), value: "localised" as const },
])

const printableLevels = computed<LabelLevel[]>(() => (props.options?.levels ?? []).filter((level) =>
    isUnified.value ? !!props.options.unified?.levels[level.key] : level.printable))

const levelOptions = computed(() => printableLevels.value.map((level) => ({ label: level.label, value: level.key })))

const unifiedLevel = computed(() => props.options?.unified?.levels[activeLevel.value])

const isBatchValid = computed(() => new RegExp(props.options?.unified?.batch_pattern ?? ".").test(batch.value.trim().toUpperCase()))

const translatedNameBlockers = ref<string[]>([])
const isTranslatingNames = ref(false)

const translateNames = computed(() => props.options?.unified?.translate_names ?? null)

watch(() => props.options, () => { translatedNameBlockers.value = [] })

const hasMissingNames = computed(() => !!translateNames.value
    && Object.values(translateNames.value.blockers).some((blocker) => (unifiedLevel.value?.blockers ?? []).includes(blocker) && !translatedNameBlockers.value.includes(blocker)))

const listeningTo = ref<string | null>(null)

const stopListening = () => {
    if (listeningTo.value && window.Echo) {
        window.Echo.leave(listeningTo.value)
    }
    listeningTo.value = null
}

onUnmounted(stopListening)

const translateMissingNames = async () => {
    if (!translateNames.value) {
        return
    }

    const blockersByLanguage = translateNames.value.blockers
    const channel = `grp.trade-unit.${translateNames.value.route.parameters.tradeUnit}`

    isTranslatingNames.value = true
    stopListening()
    if (window.Echo) {
        listeningTo.value = channel
        window.Echo.private(channel).listen(".name-translation-progress", (event: { state: string; failed: string[] }) => {
            if (event.state !== "done") {
                return
            }

            translatedNameBlockers.value = Object.entries(blockersByLanguage)
                .filter(([language]) => !event.failed.includes(language))
                .map(([, blocker]) => blocker)
            isTranslatingNames.value = false
            stopListening()

            if (event.failed.length) {
                notify({ title: ctrans("Some names could not be translated"), text: event.failed.join(", ").toUpperCase(), type: "error" })
            }
        })
    }

    try {
        await axios.post(route(translateNames.value.route.name, translateNames.value.route.parameters))
    } catch {
        isTranslatingNames.value = false
        stopListening()
        notify({ title: ctrans("Something went wrong"), text: ctrans("The names could not be translated"), type: "error" })
    }
}

const blockers = computed<string[]>(() => {
    if (!isUnified.value) {
        return []
    }

    return [
        ...(unifiedLevel.value?.blockers ?? []).filter((blocker) => !translatedNameBlockers.value.includes(blocker)),
        ...(isBatchValid.value ? [] : [ctrans("The batch code takes up to 20 characters: A to Z, 0 to 9 and hyphen")]),
    ]
})

const activeLevel = ref(props.level)

const fields = computed<LabelField[]>(() => props.options?.fields?.[activeLevel.value] ?? [])

const levelSizes = computed(() => props.options?.sizes?.[activeLevel.value] ?? [])

const selected = ref<Record<string, boolean>>({})
const customText = ref("")
const size = ref<string | undefined>(props.options?.default_size)
const layout = ref("single")

const layouts = computed(() => (size.value ? props.options?.layouts?.[size.value] : undefined) ?? [])

const sizeOptions = computed(() => levelSizes.value.map((option) => ({ label: option.label, value: option.key })))

const layoutOptions = computed(() => layouts.value.map((option) => ({ label: option.label, value: option.key })))

const cartonOrganisationId = ref<string | undefined>()

const cartonOrganisations = computed(() => props.options?.carton_organisations ?? [])

const cartonOrganisationOptions = computed(() =>
    cartonOrganisations.value.map((organisation) => ({ label: organisation.code, value: String(organisation.id) })))

const cartonOrganisationName = computed(() =>
    cartonOrganisations.value.find((organisation) => String(organisation.id) === cartonOrganisationId.value)?.name)

const resetFromOptions = () => {
    selected.value = Object.fromEntries(fields.value.map((field) => [field.key, field.available && field.checked]))

    const sizes = levelSizes.value
    size.value = sizes.some((option) => option.key === props.options?.default_size)
        ? props.options.default_size
        : sizes[0]?.key

    layout.value = "single"
    cartonOrganisationId.value = props.options?.carton_organisation_id ? String(props.options.carton_organisation_id) : undefined
}

const pickLevel = () => {
    const preferred = printableLevels.value.find((level) => level.key === props.level)
    activeLevel.value = (preferred ?? printableLevels.value[0])?.key ?? props.level
    resetFromOptions()
}

watch(() => [props.isOpen, props.level], ([isOpen]) => {
    if (!isOpen) {
        return
    }

    standard.value = props.options?.unified ? "unified" : "localised"
    batch.value = props.options?.unified?.batch ?? ""
    pickLevel()
}, { immediate: true })

watch(standard, () => {
    if (!printableLevels.value.some((level) => level.key === activeLevel.value)) {
        pickLevel()
    }
})

watch(activeLevel, resetFromOptions)

watch(layouts, (options) => {
    if (!options.some((option) => option.key === layout.value)) {
        layout.value = "single"
    }
})

const levelTitles = computed<Record<string, string>>(() => ({
    sko: ctrans("SKO label"),
    unit: ctrans("Unit label"),
    carton: ctrans("Carton label"),
}))

const customTextField = computed(() => fields.value.find((field) => field.key === "with_custom_text"))

const pdfUrl = computed(() => {
    if (isUnified.value) {
        return route(props.labelRoute.name, {
            ...props.labelRoute.parameters,
            level: activeLevel.value,
            standard: "unified",
            batch: batch.value.trim().toUpperCase(),
        })
    }

    const query: Record<string, string> = {
        ...props.labelRoute.parameters,
        level: activeLevel.value,
        layout: layout.value,
        size: size.value,
    }

    fields.value.forEach((field) => {
        query[field.key] = selected.value[field.key] ? "1" : "0"
    })

    if (activeLevel.value === "carton" && cartonOrganisationId.value) {
        query.commercialised_by = cartonOrganisationId.value
    }

    if (selected.value.with_custom_text && customText.value.trim()) {
        query.custom_text = customText.value.trim()
    }

    return route(props.labelRoute.name, query)
})

const openPdf = () => {
    window.open(pdfUrl.value, "_blank")
}

const checkboxPt = {
    box: ({ context }: { context: { checked: boolean } }) => ({
        class: context.checked ? "!border-[--app-accent] !bg-[--app-accent]" : "",
    }),
    icon: { class: "!text-[--app-accent-text]" },
}

</script>

<template>
    <Modal :isOpen="isOpen" @onClose="emits('onClose')" width="w-full max-w-md">
        <div class="flex flex-col gap-4 p-2">
            <div class="flex items-center justify-between gap-3">
                <div class="text-lg font-semibold">
                    {{ levelTitles[activeLevel] ?? ctrans("SKO label") }}
                </div>
                <SegmentedToggle
                    v-if="printableLevels.length > 1"
                    v-model="activeLevel"
                    :options="levelOptions"
                    :ariaLabel="ctrans('Label')" />
            </div>

            <SegmentedToggle
                v-if="options.unified"
                v-model="standard"
                :options="standardOptions"
                :ariaLabel="ctrans('Label standard')"
                class="w-max" />

            <div v-if="!printableLevels.length" class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                {{ ctrans("This item has no barcode yet, so there is nothing to print.") }}
            </div>

            <template v-if="isUnified">
                <div class="text-sm text-gray-600">
                    {{ ctrans("One label for every centre: all importers, every language and the batch, on a 100 mm roll.") }}
                    <span v-if="unifiedLevel" class="tabular-nums text-gray-400">{{ unifiedLevel.size }} mm</span>
                </div>

                <label class="flex flex-col gap-1 text-sm text-gray-700">
                    {{ ctrans("Batch code") }}
                    <InputText
                        v-model="batch"
                        maxlength="20"
                        :invalid="!isBatchValid"
                        class="!text-sm uppercase focus:!border-[--app-accent]" />
                </label>

                <div v-if="blockers.length" class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    <div class="font-medium">{{ ctrans("This label cannot be printed yet:") }}</div>
                    <ul class="list-disc pl-5">
                        <li v-for="blocker in blockers" :key="blocker">{{ blocker }}</li>
                    </ul>
                    <Button
                        v-if="hasMissingNames"
                        type="tertiary"
                        size="xs"
                        class="mt-2"
                        :label="isTranslatingNames ? ctrans('Translating names…') : ctrans('Translate missing names')"
                        :loading="isTranslatingNames"
                        @click="translateMissingNames">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-robot" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </template>

            <template v-else>
            <div v-if="activeLevel === 'carton' && cartonOrganisationName">
                <div class="mb-1.5 text-xs font-medium uppercase tracking-wide text-gray-400">
                    {{ ctrans("Commercialised by") }}
                </div>
                <SegmentedToggle
                    v-if="cartonOrganisationOptions.length > 1"
                    v-model="cartonOrganisationId"
                    :options="cartonOrganisationOptions"
                    :ariaLabel="ctrans('Commercialised by')"
                    class="flex-wrap" />
                <div class="mt-1 text-sm text-gray-600">{{ cartonOrganisationName }}</div>
            </div>

            <div class="flex flex-col gap-1.5">
                <div v-for="field in fields" :key="field.key" class="flex flex-col gap-1.5">
                    <label
                        class="flex w-fit max-w-full flex-wrap items-center gap-x-2 gap-y-0.5 text-sm"
                        :class="field.available ? 'text-gray-700' : 'cursor-not-allowed text-gray-400'">
                        <Checkbox
                            v-model="selected[field.key]"
                            :binary="true"
                            :disabled="!field.available"
                            :pt="checkboxPt" />
                        <span>{{ field.label }}</span>
                        <span v-if="field.reason" class="text-xs italic text-gray-400">({{ field.reason }})</span>
                    </label>

                    <Textarea
                        v-if="field.key === 'with_custom_text' && customTextField?.available"
                        v-model="customText"
                        rows="2"
                        :maxlength="options.custom_text_max_length"
                        :disabled="!selected.with_custom_text"
                        :placeholder="ctrans('Custom text')"
                        class="ml-6 !w-[calc(100%-1.5rem)] !text-sm focus:!border-[--app-accent]" />

                    <div
                        v-if="field.preview"
                        class="ml-6 whitespace-pre-line rounded-md border border-dashed px-2 py-1.5 text-xs leading-snug transition"
                        :class="selected[field.key] ? 'border-gray-300 text-gray-600' : 'border-gray-200 text-gray-300'">
                        {{ field.preview }}
                    </div>
                </div>
            </div>

            <div>
                <div class="mb-1.5 text-xs font-medium uppercase tracking-wide text-gray-400">
                    {{ ctrans("Size (mm)") }}
                </div>
                <SegmentedToggle v-model="size" :options="sizeOptions" :ariaLabel="ctrans('Size (mm)')" class="flex-wrap" />
            </div>

            <div>
                <div class="mb-1.5 text-xs font-medium uppercase tracking-wide text-gray-400">
                    {{ ctrans("Layout") }}
                </div>
                <SegmentedToggle v-model="layout" :options="layoutOptions" :ariaLabel="ctrans('Layout')" class="flex-wrap" />
            </div>
            </template>

            <div class="flex justify-end gap-2">
                <Button type="tertiary" :label="ctrans('Cancel')" icon="fal fa-times" @click="emits('onClose')" />
                <Button type="tertiary" :label="ctrans('PDF')" icon="fal fa-file-pdf" :disabled="!printableLevels.length || blockers.length > 0" @click="openPdf" />
            </div>
        </div>
    </Modal>
</template>
