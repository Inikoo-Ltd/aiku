<!--
  - Author Louis Perez
  - Created on 23-10-2026-16h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import Button from "@/Components/Elements/Buttons/Button.vue"
import BarcodeDisplay from "@/Components/DataDisplay/BarcodeDisplay.vue"
import { useCopyText } from "@/Composables/useCopyText"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCopy, faCheckCircle, faExclamationTriangle, faSpinner } from "@fal"
library.add(faCopy, faCheckCircle, faExclamationTriangle, faSpinner)

interface LabelContent {
    sku: { code: string, name: string }
    approved: boolean
    missing: string[]
    text: { key: string, label: string, value: string }[]
    pictograms: { label: string, icon: string }[]
    barcodes: { level: string, label: string, value: string | null, note: string | null }[]
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
    try {
        const { data } = await axios.get(route(requested.name, requested.parameters))
        if (requested === props.contentRoute) {
            content.value = data
        }
    } catch {
        failed.value = true
    }
}

watch(() => props.contentRoute, load, { immediate: true })

const allText = computed(() => (content.value?.text ?? []).map((line) => `${line.label}: ${line.value}`).join("\n\n"))
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

            <section>
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Text on the label") }}</h4>
                    <Button v-if="content.text.length" type="tertiary" size="xs" :label="ctrans('Copy all')" @click="useCopyText(allText)">
                        <template #icon><FontAwesomeIcon icon="fal fa-copy" class="text-[--app-accent]" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
                <dl class="divide-y divide-gray-100 rounded-md border border-gray-200">
                    <div v-for="line in content.text" :key="line.key" class="flex items-start gap-3 px-3 py-2">
                        <div class="min-w-0 flex-1">
                            <dt class="text-xs text-gray-500">{{ line.label }}</dt>
                            <dd class="whitespace-pre-line break-words text-sm text-gray-900">{{ line.value }}</dd>
                        </div>
                        <button type="button" class="flex h-7 w-7 shrink-0 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-[--app-accent]" v-tooltip="ctrans('Copy')" :aria-label="ctrans('Copy :label', { label: line.label })" @click="useCopyText(line.value)">
                            <FontAwesomeIcon icon="fal fa-copy" fixed-width aria-hidden="true" />
                        </button>
                    </div>
                    <p v-if="!content.text.length" class="px-3 py-3 text-xs text-gray-400">{{ ctrans("Nothing marked to go on the label yet") }}</p>
                </dl>
            </section>

            <section v-if="content.pictograms.length">
                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Hazard pictograms") }}</h4>
                <div class="flex flex-wrap gap-3">
                    <figure v-for="pictogram in content.pictograms" :key="pictogram.label" class="flex w-20 flex-col items-center gap-1 text-center">
                        <img :src="pictogram.icon" :alt="pictogram.label" class="h-12 w-12 object-contain" />
                        <figcaption class="text-[11px] leading-tight text-gray-600">{{ pictogram.label }}</figcaption>
                    </figure>
                </div>
            </section>

            <section>
                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Barcodes") }}</h4>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div v-for="barcode in content.barcodes" :key="barcode.level" class="rounded-md border border-gray-200 p-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs text-gray-600">{{ barcode.label }}</span>
                            <button v-if="barcode.value" type="button" class="flex h-6 w-6 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-[--app-accent]" v-tooltip="ctrans('Copy')" :aria-label="ctrans('Copy :label', { label: barcode.label })" @click="useCopyText(barcode.value)">
                                <FontAwesomeIcon icon="fal fa-copy" fixed-width aria-hidden="true" />
                            </button>
                        </div>
                        <BarcodeDisplay v-if="barcode.value" :value="barcode.value" :height="40" class="mt-1" />
                        <p v-else class="mt-0.5 text-xs text-amber-700">{{ barcode.note ?? ctrans("Not set yet") }}</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
