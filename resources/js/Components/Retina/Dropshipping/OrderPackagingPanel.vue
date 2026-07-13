<!--
  -  Author: Andi Ferdiawan
  -  Created: Fri, 10 Jul 2026 20:00:00 Central Indonesia Time, Bali, Indonesia
  -  Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed, inject, ref, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Select from "primevue/select"
import Checkbox from "primevue/checkbox"
import Textarea from "primevue/textarea"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
    faArrowRight,
    faExclamationCircle,
    faFileAlt,
    faFileImage,
    faFilePdf,
    faInfoCircle,
    faLeaf,
    faPrint,
    faUpload,
} from "@fal"

library.add(faArrowRight, faExclamationCircle, faFileAlt, faFileImage, faFilePdf, faInfoCircle, faLeaf, faPrint, faUpload)

interface PackagingOption {
    value: string | number
    label: string
    price: number
    family_code: string | null
}

interface LeafletOption {
    id: number
    label: string
    price: number
    family_codes: string[]
}

interface CustomerLeaflet {
    id: number
    leaflet_id: number
    family_code: string | null
    name: string
    mime_type?: string | null
    meta?: string | null
    state: string
    state_label: string
}

const props = withDefaults(defineProps<{
    accentColor?: string
    currencyCode?: string
    packagingOptions?: PackagingOption[]
    selectedPackaging?: string | number | null
    leafletOptions?: LeafletOption[]
    defaultLeafletsByFamily?: Record<string, number[]>
    personalisedMessage?: string
    maxMessageLength?: number
    customerLeaflets?: CustomerLeaflet[]
    packagingPreferencesHref?: string | null
}>(), {
    accentColor: "#f97316",
    currencyCode: "GBP",
    packagingOptions: () => [],
    selectedPackaging: null,
    leafletOptions: () => [],
    defaultLeafletsByFamily: () => ({}),
    personalisedMessage: "",
    maxMessageLength: 200,
    customerLeaflets: () => [],
    packagingPreferencesHref: null,
})

const locale = inject("locale", aikuLocaleStructure)

const accentStyle = computed(() => ({
    softBg: `color-mix(in srgb, ${props.accentColor} 5%, white)`,
    text: `color-mix(in srgb, ${props.accentColor} 70%, #374151)`,
}))

const formatPrice = (amount: number) =>
    amount === 0 ? trans("Free") : locale.currencyFormat(props.currencyCode, amount)

// Local UI state (no backend mutations here).
const selected = ref<string | number | null>(props.selectedPackaging)
const message = ref(props.personalisedMessage)

const selectedPackagingOption = computed(() =>
    props.packagingOptions.find(option => option.value === selected.value)
)

// Family of the selected packaging, used to scope inserts & files.
const selectedFamily = computed(() => selectedPackagingOption.value?.family_code ?? null)

// Inserts applicable to the selected packaging family.
const inserts = computed(() =>
    props.leafletOptions.filter(leaflet =>
        selectedFamily.value != null && leaflet.family_codes.includes(selectedFamily.value)
    )
)

// Checkbox state, seeded from the customer's default selection for that family.
const defaultLeafletIds = (family: string | null) =>
    family == null ? [] : (props.defaultLeafletsByFamily[family] ?? [])

const buildEnabledInserts = (family: string | null) =>
    Object.fromEntries(
        props.leafletOptions
            .filter(leaflet => family != null && leaflet.family_codes.includes(family))
            .map(leaflet => [leaflet.id, defaultLeafletIds(family).includes(leaflet.id)])
    )

const enabledInserts = ref<Record<number, boolean>>(buildEnabledInserts(selectedFamily.value))

watch(selectedFamily, (family) => {
    enabledInserts.value = buildEnabledInserts(family)
})

// Uploaded files for the selected family only.
const leaflets = computed(() =>
    props.customerLeaflets.filter(leaflet => leaflet.family_code === selectedFamily.value)
)

// Checked inserts that will be printed, paired with their uploaded file (if any).
const printableInserts = computed(() =>
    inserts.value
        .filter(insert => enabledInserts.value[insert.id])
        .map(insert => ({
            ...insert,
            file: leaflets.value.find(leaflet => leaflet.leaflet_id === insert.id) ?? null,
        }))
)

const packagingPrice = computed(() => selectedPackagingOption.value?.price ?? 0)

const addOnsPrice = computed(() =>
    inserts.value
        .filter(insert => enabledInserts.value[insert.id])
        .reduce((sum, insert) => sum + insert.price, 0)
)

const addOnsCount = computed(() =>
    inserts.value.filter(insert => enabledInserts.value[insert.id]).length
)

const packagingTotal = computed(() => packagingPrice.value + addOnsPrice.value)

const leafletFileIcon = (leaflet: CustomerLeaflet) =>
    leaflet.mime_type?.startsWith("image/") ? "file-image" : "file-pdf"
</script>

<template>
    <div class="space-y-4">
        <!-- Packaging & Personalisation -->
        <section class="rounded-lg border border-gray-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-gray-700">
                {{ trans("Packaging & Personalisation") }}
            </h2>

            <!-- Packaging option -->
            <div class="mt-3">
                <label class="flex items-center gap-1 text-xs font-medium text-gray-600 mb-1">
                    {{ trans("Packaging option") }}
                    <FontAwesomeIcon
                        :icon="['fal', 'info-circle']"
                        class="text-gray-400"
                        v-tooltip="trans('The default packaging used for this order')"
                        fixed-width
                        aria-hidden="true"
                    />
                </label>
                <Select
                    v-model="selected"
                    :options="packagingOptions"
                    optionLabel="label"
                    optionValue="value"
                    :placeholder="trans('Select packaging')"
                    class="w-full"
                >
                    <template #value="{ value }">
                        <div v-if="selectedPackagingOption" class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <FontAwesomeIcon :icon="['fal', 'leaf']" class="text-green-500" fixed-width aria-hidden="true" />
                                {{ selectedPackagingOption.label }}
                            </span>
                            <span class="text-gray-500">{{ formatPrice(selectedPackagingOption.price) }}</span>
                        </div>
                        <span v-else class="text-gray-400">{{ trans("Select packaging") }}</span>
                    </template>
                    <template #option="{ option }">
                        <div class="flex w-full items-center justify-between gap-2">
                            <span>{{ option.label }}</span>
                            <span class="text-gray-500">{{ formatPrice(option.price) }}</span>
                        </div>
                    </template>
                </Select>
            </div>

            <!-- Include with order -->
            <div class="mt-4">
                <label class="flex items-center gap-1 text-xs font-medium text-gray-600 mb-2">
                    {{ trans("Include with order") }}
                    <FontAwesomeIcon
                        :icon="['fal', 'info-circle']"
                        class="text-gray-400"
                        v-tooltip="trans('These inserts will be added to every order')"
                        fixed-width
                        aria-hidden="true"
                    />
                </label>
                <div class="divide-y divide-gray-100">
                    <label
                        v-for="insert in inserts"
                        :key="insert.id"
                        class="flex items-center gap-3 py-2 cursor-pointer"
                    >
                        <Checkbox v-model="enabledInserts[insert.id]" :binary="true" />
                        <span class="flex h-7 w-7 items-center justify-center rounded border border-gray-200 text-gray-400">
                            <FontAwesomeIcon :icon="['fal', 'file-alt']" fixed-width aria-hidden="true" />
                        </span>
                        <span class="flex-1 text-sm">{{ insert.label }}</span>
                        <span class="text-sm text-gray-500">{{ formatPrice(insert.price) }}</span>
                    </label>
                    <p v-if="!inserts.length" class="py-3 text-center text-xs text-gray-400">
                        {{ trans("No inserts available for this packaging") }}
                    </p>
                </div>
            </div>

            <!-- Personalised message -->
            <div class="mt-4">
                <label class="flex items-center gap-1 text-xs font-medium text-gray-600 mb-1">
                    {{ trans("Personalised message") }}
                    <span class="text-gray-400">({{ trans("optional") }})</span>
                    <FontAwesomeIcon
                        :icon="['fal', 'info-circle']"
                        class="text-gray-400"
                        v-tooltip="trans('Printed and included with all orders')"
                        fixed-width
                        aria-hidden="true"
                    />
                </label>
                <div class="relative">
                    <Textarea
                        v-model="message"
                        :maxlength="maxMessageLength"
                        rows="3"
                        class="w-full text-sm"
                        :placeholder="trans('Write your message here')"
                    />
                    <div class="absolute bottom-2 right-3 text-xs text-gray-400">
                        {{ message.length }}/{{ maxMessageLength }}
                    </div>
                </div>
            </div>
        </section>

        <!-- Leaflets & inserts to be printed -->
        <section class="rounded-lg border border-gray-200 bg-white p-4">
            <h2 class="flex items-center gap-1 text-sm font-semibold text-gray-700">
                {{ trans("Leaflets to be printed") }}
                <FontAwesomeIcon
                    :icon="['fal', 'info-circle']"
                    class="text-gray-400"
                    v-tooltip="trans('The selected inserts and their files that will be printed with this order')"
                    fixed-width
                    aria-hidden="true"
                />
            </h2>

            <div class="mt-3 divide-y divide-gray-100">
                <div
                    v-for="insert in printableInserts"
                    :key="insert.id"
                    class="flex items-center gap-3 py-2"
                >
                    <FontAwesomeIcon
                        v-if="insert.file"
                        :icon="['fal', leafletFileIcon(insert.file)]"
                        :class="insert.file.mime_type?.startsWith('image/') ? 'text-blue-500' : 'text-red-500'"
                        class="text-lg"
                        fixed-width
                        aria-hidden="true"
                    />
                    <FontAwesomeIcon
                        v-else
                        :icon="['fal', 'file-alt']"
                        class="text-lg text-gray-300"
                        fixed-width
                        aria-hidden="true"
                    />
                    <span class="flex-1 min-w-0">
                        <span class="block truncate text-sm font-medium">{{ insert.label }}</span>
                        <span v-if="insert.file" class="block text-xs text-gray-400">
                            {{ insert.file.name }}<template v-if="insert.file.meta"> · {{ insert.file.meta }}</template>
                        </span>
                        <span v-else class="flex items-center gap-1 text-xs text-amber-600">
                            <FontAwesomeIcon :icon="['fal', 'exclamation-circle']" fixed-width aria-hidden="true" />
                            {{ trans("Leaflet file not uploaded yet") }}
                        </span>
                    </span>
                    <span
                        v-if="insert.file"
                        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700"
                    >
                        <FontAwesomeIcon :icon="['fal', 'print']" fixed-width aria-hidden="true" />
                        {{ trans("Will be printed") }}
                    </span>
                </div>
                <p v-if="!printableInserts.length" class="py-3 text-center text-xs text-gray-400">
                    {{ trans("No inserts selected for this order") }}
                </p>
            </div>

            <a
                v-if="packagingPreferencesHref"
                :href="packagingPreferencesHref"
                class="group mt-3 flex items-center justify-between gap-2 rounded-md border border-dashed border-gray-300 px-3 py-2 text-xs font-medium text-gray-600 transition hover:border-gray-400 hover:bg-gray-50"
            >
                <span class="flex items-center gap-2">
                    <FontAwesomeIcon :icon="['fal', 'upload']" class="text-gray-400" fixed-width aria-hidden="true" />
                    {{ trans("Upload or manage leaflet files") }}
                </span>
                <FontAwesomeIcon
                    :icon="['fal', 'arrow-right']"
                    class="text-gray-400 transition group-hover:translate-x-0.5"
                    :style="{ color: accentStyle.text }"
                    fixed-width
                    aria-hidden="true"
                />
            </a>
        </section>

        <!-- Packaging summary -->
        <section
            class="rounded-lg border border-gray-200 px-4 py-3"
            :style="{ backgroundColor: accentStyle.softBg }"
        >
            <dl class="space-y-1.5 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-gray-600">
                        {{ trans("Packaging") }}
                        <span v-if="selectedPackagingOption" class="text-gray-400">({{ selectedPackagingOption.label }})</span>
                    </dt>
                    <dd class="font-medium text-gray-700">{{ formatPrice(packagingPrice) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-gray-600">
                        {{ trans("Add-ons") }}
                        <span class="text-gray-400">({{ addOnsCount }})</span>
                    </dt>
                    <dd class="font-medium text-gray-700">{{ formatPrice(addOnsPrice) }}</dd>
                </div>
            </dl>
            <div class="mt-2 flex items-center justify-between border-t border-gray-200 pt-2">
                <span class="text-sm font-semibold text-gray-700">{{ trans("Packaging total") }}</span>
                <span class="text-base font-semibold text-gray-700">{{ formatPrice(packagingTotal) }}</span>
            </div>
        </section>
    </div>
</template>
