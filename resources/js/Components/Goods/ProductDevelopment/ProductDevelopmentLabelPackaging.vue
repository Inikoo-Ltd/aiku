<!--
  - Author Louis Perez
  - Created on 23-10-2026-16h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import JsBarcode from "jsbarcode"
import Select from "primevue/select"
import Button from "@/Components/Elements/Buttons/Button.vue"
import BarcodeDisplay from "@/Components/DataDisplay/BarcodeDisplay.vue"
import { useCopyText } from "@/Composables/useCopyText"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCopy, faCheckCircle, faExclamationTriangle, faSpinner, faDownload, faLanguage, faCertificate, faRecycle, faTrashAlt, faTint, faBarcodeAlt, faShieldCheck } from "@fal"
library.add(faCopy, faCheckCircle, faExclamationTriangle, faSpinner, faDownload, faLanguage, faCertificate, faRecycle, faTrashAlt, faTint, faBarcodeAlt, faShieldCheck)

interface LabelContent {
    sku: { code: string, name: string }
    approved: boolean
    missing: string[]
    label_text: string
    info: { key: string, label: string, value: string }[]
    marks: { key: string, label: string }[]
    pictograms: { label: string, icon: string }[]
    barcodes: { level: string, label: string, type: string, value: string | null, note: string | null }[]
    translate_route?: routeType
    languages?: { value: string, label: string }[]
}

const props = defineProps<{
    contentRoute: routeType
}>()

const content = ref<LabelContent | null>(null)
const failed = ref(false)

async function load() {
    const requested = props.contentRoute
    content.value = null
    failed.value = false
    translation.value = null
    try {
        const { data } = await axios.get(route(requested.name, requested.parameters))
        if (requested === props.contentRoute) {
            content.value = data
        }
    } catch {
        failed.value = true
    }
}

/* Quick translate: on request, not saved */

const language = ref<string | null>(null)
const translation = ref<{ text: string, language: string } | null>(null)
const translating = ref(false)
const translateError = ref<string | null>(null)

async function translate() {
    if (!content.value?.translate_route || !language.value) {
        return
    }
    translating.value = true
    translateError.value = null
    try {
        const { data } = await axios.get(route(content.value.translate_route.name, content.value.translate_route.parameters), { params: { language: language.value } })
        translation.value = data
    } catch {
        translateError.value = ctrans("The translation failed, please try again")
    } finally {
        translating.value = false
    }
}

watch(() => props.contentRoute, load, { immediate: true })

/* Barcode artwork as a PNG */

const markIcons: Record<string, string> = {
    ce_marking: "fal fa-certificate",
    ukca_marking: "fal fa-certificate",
    weee_symbol: "fal fa-trash-alt",
    ip_rating: "fal fa-tint",
    sorting_recycling_information: "fal fa-recycle",
    safety_icons: "fal fa-exclamation-triangle",
    batch_number: "fal fa-barcode-alt",
}

function downloadBarcode(barcode: LabelContent["barcodes"][number]) {
    if (!barcode.value) {
        return
    }
    const canvas = document.createElement("canvas")
    JsBarcode(canvas, barcode.value, {
        format: barcode.type === "EAN-13" && /^\d{12,13}$/.test(barcode.value) ? "EAN13" : "CODE128",
        width: 4,
        height: 160,
        fontSize: 36,
        margin: 24,
    })
    const link = document.createElement("a")
    link.href = canvas.toDataURL("image/png")
    link.download = `${content.value?.sku.code ?? "barcode"}-${barcode.level}-${barcode.value}.png`
    link.click()
}
</script>

<template>
    <div>
        <p v-if="failed" class="text-sm text-red-600">{{ ctrans("The label information could not be loaded") }}</p>
        <p v-else-if="!content" class="flex items-center gap-2 text-sm text-gray-400">
            <FontAwesomeIcon icon="fal fa-spinner" spin fixed-width aria-hidden="true" /> {{ ctrans("Loading…") }}
        </p>
        <div v-else class="space-y-4">
            <p v-if="content.approved" class="flex items-center gap-1.5 text-xs text-green-700">
                <FontAwesomeIcon icon="fal fa-check-circle" fixed-width aria-hidden="true" />{{ ctrans("Pack text approved by compliance") }}
            </p>
            <p v-else class="flex items-start gap-1.5 text-xs text-amber-700">
                <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5" fixed-width aria-hidden="true" />
                {{ ctrans("Compliance has not finished: :items", { items: content.missing.join(", ") }) }}
            </p>

            <div class="grid gap-4 xl:grid-cols-2">
                <section class="rounded-lg border border-gray-200 p-4">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">{{ ctrans("Label text (English)") }}</h4>
                            <p class="text-xs text-gray-500">{{ ctrans("Ready to use on the front and back of the pack.") }}</p>
                        </div>
                        <Button v-if="content.label_text" type="tertiary" size="xs" :label="ctrans('Copy all text')" @click="useCopyText(content.label_text)">
                            <template #icon><FontAwesomeIcon icon="fal fa-copy" class="text-[--app-accent]" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </div>
                    <pre v-if="content.label_text" class="max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-gray-50 p-3 font-mono text-xs leading-relaxed text-gray-800">{{ content.label_text }}</pre>
                    <p v-else class="rounded-md bg-gray-50 px-3 py-4 text-center text-xs text-gray-400">{{ ctrans("Nothing marked to go on the label yet") }}</p>
                    <dl v-if="content.info.length" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                        <div v-for="line in content.info" :key="line.key" class="flex gap-1">
                            <dt class="text-gray-500">{{ line.label }}:</dt>
                            <dd class="text-gray-800">{{ line.value }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-lg border border-gray-200 p-4">
                    <div class="mb-2">
                        <h4 class="flex items-center gap-1.5 text-sm font-semibold text-gray-900">
                            <FontAwesomeIcon icon="fal fa-language" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                            {{ ctrans("Quick translate") }}
                        </h4>
                        <p class="text-xs text-gray-500">{{ ctrans("A machine translation to copy. It is not saved or approved: check it before it goes on the pack.") }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Select v-model="language" :options="content.languages ?? []" optionLabel="label" optionValue="value" filter :placeholder="ctrans('Pick a language')" size="small" class="min-w-[12rem] flex-1" overlayClass="text-sm" />
                        <Button type="tertiary" size="xs" :label="ctrans('Translate')" :loading="translating" :disabled="!language || !content.label_text" @click="translate">
                            <template #icon><FontAwesomeIcon icon="fal fa-language" class="text-[--app-accent]" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </div>
                    <p v-if="translateError" class="mt-2 text-xs text-red-600">{{ translateError }}</p>
                    <template v-if="translation">
                        <div class="mt-3 mb-1 flex items-center justify-between gap-2">
                            <span class="text-xs text-gray-500">{{ translation.language }}</span>
                            <Button type="tertiary" size="xs" :label="ctrans('Copy translation')" @click="useCopyText(translation.text)">
                                <template #icon><FontAwesomeIcon icon="fal fa-copy" class="text-[--app-accent]" fixed-width aria-hidden="true" /></template>
                            </Button>
                        </div>
                        <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-gray-50 p-3 font-mono text-xs leading-relaxed text-gray-800">{{ translation.text }}</pre>
                    </template>
                </section>

                <section class="rounded-lg border border-gray-200 p-4">
                    <h4 class="text-sm font-semibold text-gray-900">{{ ctrans("Barcodes") }}</h4>
                    <p class="mb-3 text-xs text-gray-500">{{ ctrans("Use the approved barcodes of this SKU.") }}</p>
                    <div class="space-y-3">
                        <div v-for="barcode in content.barcodes" :key="barcode.level" class="grid gap-3 rounded-md bg-gray-50 p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                            <dl class="grid grid-cols-[6rem_minmax(0,1fr)] gap-y-1 text-xs">
                                <dt class="text-gray-500">{{ ctrans("Barcode") }}</dt>
                                <dd class="font-medium text-gray-900">{{ barcode.label }}</dd>
                                <dt class="text-gray-500">{{ ctrans("Type") }}</dt>
                                <dd class="text-gray-800">{{ barcode.type }}</dd>
                                <dt class="text-gray-500">{{ ctrans("Number") }}</dt>
                                <dd class="flex items-center gap-1 tabular-nums text-gray-800">
                                    {{ barcode.value ?? "—" }}
                                    <button v-if="barcode.value" type="button" class="text-gray-400 hover:text-[--app-accent]" v-tooltip="ctrans('Copy')" :aria-label="ctrans('Copy :label', { label: barcode.label })" @click="useCopyText(barcode.value)">
                                        <FontAwesomeIcon icon="fal fa-copy" fixed-width aria-hidden="true" />
                                    </button>
                                </dd>
                            </dl>
                            <div v-if="barcode.value" class="flex flex-col items-center gap-1.5">
                                <BarcodeDisplay :value="barcode.value" :height="40" class="rounded bg-white p-1" />
                                <Button type="tertiary" size="xs" :label="ctrans('Download (PNG)')" @click="downloadBarcode(barcode)">
                                    <template #icon><FontAwesomeIcon icon="fal fa-download" class="text-[--app-accent]" fixed-width aria-hidden="true" /></template>
                                </Button>
                            </div>
                            <p v-else class="text-xs text-amber-700">{{ barcode.note ?? ctrans("Not set yet") }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 p-4">
                    <h4 class="text-sm font-semibold text-gray-900">{{ ctrans("Required pictograms / symbols") }}</h4>
                    <p class="mb-3 text-xs text-gray-500">{{ ctrans("Include these on the packaging.") }}</p>
                    <div v-if="content.pictograms.length || content.marks.length" class="grid grid-cols-[repeat(auto-fill,minmax(6.5rem,1fr))] gap-2">
                        <figure v-for="pictogram in content.pictograms" :key="pictogram.label" class="flex flex-col items-center gap-1.5 rounded-md border border-gray-200 p-2 text-center">
                            <img :src="pictogram.icon" :alt="pictogram.label" class="h-12 w-12 object-contain" />
                            <figcaption class="text-[11px] leading-tight text-gray-700">{{ pictogram.label }}</figcaption>
                        </figure>
                        <figure v-for="mark in content.marks" :key="mark.key" class="flex flex-col items-center gap-1.5 rounded-md border border-gray-200 p-2 text-center">
                            <FontAwesomeIcon :icon="markIcons[mark.key] ?? 'fal fa-shield-check'" class="h-10 text-3xl text-gray-700" fixed-width aria-hidden="true" />
                            <figcaption class="text-[11px] leading-tight text-gray-700">{{ mark.label }}</figcaption>
                        </figure>
                    </div>
                    <p v-else class="text-xs text-gray-400">{{ ctrans("No pictograms or marks needed") }}</p>
                </section>
            </div>
        </div>
    </div>
</template>
