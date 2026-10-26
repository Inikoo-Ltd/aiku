<!--
  - Author Louis Perez
  - Created on 17-10-2026-23h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import { Link } from "@inertiajs/vue3"
import BarcodeDisplay from "@/Components/DataDisplay/BarcodeDisplay.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle, faExclamationTriangle, faChevronDown } from "@fal"
library.add(faCheckCircle, faExclamationTriangle, faChevronDown)

const props = defineProps<{
    collapsed?: boolean
    data: {
        sku: { code: string, name: string }
        approved: boolean
        missing: string[]
        text: { label: string, value: string }[]
        info?: { key: string, label: string, value: string }[]
        marks?: { key: string, label: string }[]
        pictograms: { label: string, icon: string }[]
        barcodes: { level: string, label: string, value: string | null, note: string | null }[]
        compliance_route: routeType
    }
}>()

const open = ref(!props.collapsed)
watch(() => props.collapsed, (collapsed) => open.value = !collapsed)
</script>

<template>
    <section class="rounded-lg border border-gray-200 bg-white">
        <header class="px-4 py-2.5" :class="open ? 'border-b border-gray-100' : ''">
            <button type="button" class="flex w-full items-center gap-2 text-left" :aria-expanded="open" @click="open = !open">
                <h2 class="flex-1 text-sm font-semibold text-gray-800">{{ ctrans("Label content for :sku", { sku: data.sku.code }) }}</h2>
                <FontAwesomeIcon icon="fal fa-chevron-down" class="shrink-0 text-gray-400 transition-transform" :class="open ? '' : '-rotate-90'" fixed-width aria-hidden="true" />
            </button>
            <p v-if="data.approved" class="mt-0.5 flex items-center gap-1 text-xs text-green-700">
                <FontAwesomeIcon icon="fal fa-check-circle" fixed-width aria-hidden="true" />{{ ctrans("Pack text approved by compliance") }}
            </p>
            <p v-else class="mt-0.5 flex items-start gap-1 text-xs text-amber-700">
                <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5" fixed-width aria-hidden="true" />
                <span>
                    {{ ctrans("Compliance has not finished: :items", { items: data.missing.join(", ") }) }}
                    <Link :href="route(data.compliance_route.name, data.compliance_route.parameters)" class="primaryLink">{{ ctrans("Open compliance") }}</Link>
                </span>
            </p>
        </header>

        <div v-show="open">
        <dl class="space-y-2.5 px-4 py-3 text-sm">
            <div v-for="line in data.text" :key="line.label">
                <dt class="text-xs text-gray-500">{{ line.label }}</dt>
                <dd class="whitespace-pre-line break-words text-gray-900">{{ line.value }}</dd>
            </div>
            <div v-for="line in data.info ?? []" :key="line.key">
                <dt class="text-xs text-gray-500">{{ line.label }}</dt>
                <dd class="break-words text-gray-900">{{ line.value }}</dd>
            </div>
            <div v-if="data.marks?.length">
                <dt class="text-xs text-gray-500">{{ ctrans("Marks on the label") }}</dt>
                <dd class="break-words text-gray-900">{{ data.marks.map((mark) => mark.label).join(", ") }}</dd>
            </div>
            <div v-if="data.pictograms.length">
                <dt class="text-xs text-gray-500">{{ ctrans("Hazard pictograms") }}</dt>
                <dd class="mt-1 flex flex-wrap gap-2">
                    <img v-for="pictogram in data.pictograms" :key="pictogram.label" :src="pictogram.icon" :alt="pictogram.label" v-tooltip="pictogram.label" class="h-9 w-9 object-contain" />
                </dd>
            </div>
        </dl>

        <div class="space-y-3 border-t border-gray-100 px-4 py-3">
            <h3 class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Barcodes") }}</h3>
            <div v-for="barcode in data.barcodes" :key="barcode.level">
                <div class="text-xs text-gray-600">{{ barcode.label }}</div>
                <BarcodeDisplay v-if="barcode.value" :value="barcode.value" :height="40" class="mt-1" />
                <p v-else class="mt-0.5 text-xs text-amber-700">{{ barcode.note ?? ctrans("Not set yet") }}</p>
            </div>
        </div>
        </div>
    </section>
</template>
