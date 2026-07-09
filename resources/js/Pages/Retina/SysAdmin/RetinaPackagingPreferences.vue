<!--
  - Author: Andi Ferdiawan
  - Created: Thu, 09 Jul 2026 10:30:00 Central Indonesia Time, Bali, Indonesia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { Head } from "@inertiajs/vue3"
import { useLayoutStore } from "@/Stores/retinaLayout"
import { capitalize } from "@/Composables/capitalize"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { PageHeadingTypes } from "@/types/PageHeading"
import type { Image as ImageProxy } from "@/types/Image"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import Textarea from "primevue/textarea"
import Checkbox from "primevue/checkbox"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
    faBoxOpen,
    faCheck,
    faEllipsisV,
    faFileAlt,
    faFilePdf,
    faGift,
    faInfoCircle,
    faPencil,
    faUpload,
} from "@fal"

library.add(faBoxOpen, faCheck, faEllipsisV, faFileAlt, faFilePdf, faGift, faInfoCircle, faPencil, faUpload)

interface PackagingOption {
    family_code: string
    type: string
    label: string
    sizes: string | null
    price_min: number
    price_max: number
    image: { thumbnail?: ImageProxy, source?: ImageProxy } | null
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    packagingOptions?: PackagingOption[]
    currencyCode?: string
}>()

const locale = inject("locale", aikuLocaleStructure)

const packagingOptions = computed(() => props.packagingOptions ?? [])

const layout = useLayoutStore()
const accentColor = computed(() => layout?.app?.theme?.[4] ?? "#f97316")
const accentTextColor = computed(() => layout?.app?.theme?.[5] ?? "#ffffff")
const accentStyle = computed(() => ({
    softBg: `color-mix(in srgb, ${accentColor.value} 8%, white)`,
    softBorder: `color-mix(in srgb, ${accentColor.value} 35%, white)`,
    text: `color-mix(in srgb, ${accentColor.value} 85%, black)`,
}))

const fallbackIconColors: Record<string, string> = {
    standard: "text-amber-600",
    eco: "text-green-600",
    premium: "text-purple-600",
    gift: "text-pink-500",
    branded: "text-blue-600",
}

const formatPriceRange = (option: PackagingOption) => {
    if (option.price_max === 0) {
        return trans("No extra charge")
    }

    const currencyCode = props.currencyCode ?? "USD"
    const min = locale.currencyFormat(currencyCode, option.price_min)
    if (option.price_min === option.price_max) {
        return min
    }

    return `${min} – ${locale.currencyFormat(currencyCode, option.price_max)}`
}

const selectedPackaging = ref(packagingOptions.value[0]?.family_code ?? null)

const inserts = ref([
    { type: "thank_you_card", label: "Thank You Card", description: "Upload your thank you card.", price: "£0.20", enabled: true },
    { type: "promotional_leaflet", label: "Promotional Leaflet", description: "Upload your promotional leaflet.", price: "£0.20", enabled: true },
    { type: "care_instructions", label: "Care Instructions", description: "Upload product care and safety information.", price: "£0.20", enabled: true },
    { type: "custom_leaflet", label: "Custom Leaflet", description: "Upload your custom leaflet.", price: "£0.30", enabled: true },
])

const personalisedMessage = ref("Thank you for your order!\nWe hope you love our products.")
const maxMessageLength = 200

const leaflets = ref([
    { name: "Thank_You_Card_AW.pdf", type: "Thank You Card", size: "0.8 MB", uploadedAt: "12/05/2026", status: "Active" },
    { name: "Promotional_Leaflet_AW.pdf", type: "Promotional Leaflet", size: "1.2 MB", uploadedAt: "12/05/2026", status: "Active" },
    { name: "Care_Instructions_AW.pdf", type: "Care Instructions", size: "0.8 MB", uploadedAt: "05/05/2026", status: "Active" },
    { name: "Custom_Leaflet_AW.pdf", type: "Custom Leaflet", size: "0.9 MB", uploadedAt: "01/05/2026", status: "Active" },
])

const summary = computed(() => ({
    defaultPackaging: packagingOptions.value.find(option => option.family_code === selectedPackaging.value)?.label,
    insertsSelected: inserts.value.filter(insert => insert.enabled).length,
    hasMessage: personalisedMessage.value.trim().length > 0,
    leafletsUploaded: leaflets.value.length,
}))

// Static save simulation, will submit to backend later
const isSaving = ref(false)
const justSaved = ref(false)
const saveSettings = () => {
    isSaving.value = true
    setTimeout(() => {
        isSaving.value = false
        justSaved.value = true
        setTimeout(() => justSaved.value = false, 2000)
    }, 600)
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <!-- Page header -->
    <div class="flex items-start gap-4 border-b border-gray-200 px-4 py-5 sm:px-6">
        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl" :style="{ backgroundColor: accentColor, color: accentTextColor }">
            <FontAwesomeIcon :icon="['fal', 'gift']" class="text-3xl" fixed-width aria-hidden="true" />
        </div>
        <div>
            <h1 class="text-2xl font-bold">{{ trans("Packaging & Personalisation Preferences") }}</h1>
            <div class="mt-1 text-sm text-gray-600">
                <p>{{ trans("Choose your default packaging and add-ons that will be automatically applied to all your orders.") }}</p>
                <p>{{ trans("These settings can be overridden on any order in your basket.") }}</p>
            </div>
        </div>
    </div>

    <div class="p-4 sm:p-6 space-y-6">

        <!-- Info banner -->
        <div class="rounded-md border px-4 py-2.5 text-xs flex gap-2 items-start" :style="{ backgroundColor: accentStyle.softBg, borderColor: accentStyle.softBorder, color: accentStyle.text }">
            <FontAwesomeIcon :icon="['fal', 'info-circle']" class="mt-0.5" fixed-width aria-hidden="true" />
            <span>
                {{ trans("If an item does not fit any of the selected packaging options, it will be sent in our standard packaging at") }}
                <span class="font-semibold">{{ trans("no extra charge") }}.</span>
            </span>
        </div>

        <!-- Section 1: Default packaging -->
        <section class="rounded-lg border border-gray-200 bg-white p-4 sm:p-6">
            <h2 class="text-base font-semibold">1. {{ trans("Default Packaging Option") }}</h2>
            <p class="text-sm text-gray-500 mb-4">{{ trans("This packaging will be applied to all orders by default.") }}</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <button
                    v-for="option in packagingOptions"
                    :key="option.family_code"
                    type="button"
                    class="relative rounded-lg border p-4 text-center transition"
                    :class="selectedPackaging === option.family_code ? '' : 'border-gray-200 hover:border-gray-300'"
                    :style="selectedPackaging === option.family_code ? { borderColor: accentColor, boxShadow: `0 0 0 1px ${accentColor}` } : undefined"
                    @click="selectedPackaging = option.family_code"
                >
                    <!-- Radio indicator -->
                    <span
                        class="absolute left-3 top-3 flex h-4 w-4 items-center justify-center rounded-full border"
                        :class="selectedPackaging === option.family_code ? '' : 'border-gray-300'"
                        :style="selectedPackaging === option.family_code ? { borderColor: accentColor } : undefined"
                    >
                        <span v-if="selectedPackaging === option.family_code" class="h-2 w-2 rounded-full" :style="{ backgroundColor: accentColor }" />
                    </span>

                    <div class="mx-auto mb-3 flex h-24 w-20 items-center justify-center">
                        <Image
                            v-if="option.image"
                            :src="option.image.source"
                            :alt="option.label"
                            class="h-full w-full rounded object-contain"
                        />
                        <FontAwesomeIcon
                            v-else
                            :icon="['fal', 'box-open']"
                            class="text-5xl"
                            :class="fallbackIconColors[option.type] ?? 'text-amber-600'"
                            aria-hidden="true"
                        />
                    </div>

                    <div class="text-sm font-medium">{{ option.label }}</div>
                    <div class="text-xs text-gray-500">{{ option.sizes ?? trans("One size") }}</div>
                    <div class="mt-1 text-sm font-semibold" :style="{ color: accentStyle.text }">{{ formatPriceRange(option) }}</div>
                </button>
            </div>

            <div v-if="!packagingOptions.length" class="rounded-md border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">
                {{ trans("No packaging options are available yet.") }}
            </div>

            <div class="mt-4 rounded-md px-4 py-2 text-xs flex gap-2 items-start" :style="{ backgroundColor: accentStyle.softBg, color: accentStyle.text }">
                <FontAwesomeIcon :icon="['fal', 'info-circle']" class="mt-0.5" fixed-width aria-hidden="true" />
                <span>{{ trans("We will always use the most suitable size for your order based on the packaging you select.") }}</span>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <!-- Section 2: Inserts & leaflets -->
            <section class="lg:col-span-3 rounded-lg border border-gray-200 bg-white p-4 sm:p-6">
                <h2 class="text-base font-semibold">
                    2. {{ trans("Inserts & Leaflets") }}
                    <span class="font-normal text-gray-500">({{ trans("Automatic Add-ons") }})</span>
                </h2>
                <p class="text-sm text-gray-500 mb-4">{{ trans("These items will be included with every order.") }}</p>

                <div class="divide-y divide-gray-100">
                    <label
                        v-for="insert in inserts"
                        :key="insert.type"
                        class="flex items-center gap-3 py-3 cursor-pointer"
                    >
                        <Checkbox v-model="insert.enabled" :binary="true" />
                        <span class="flex h-8 w-8 items-center justify-center rounded border border-gray-200 text-gray-400">
                            <FontAwesomeIcon :icon="['fal', 'file-alt']" fixed-width aria-hidden="true" />
                        </span>
                        <span class="flex-1">
                            <span class="block text-sm">
                                <span class="font-medium">{{ insert.label }}</span>
                                <span class="ml-1 text-xs font-medium" :style="{ color: accentStyle.text }">({{ trans("Size to be confirmed") }})</span>
                            </span>
                            <span class="block text-xs text-gray-500">{{ insert.description }}</span>
                        </span>
                        <span class="text-sm text-gray-600">{{ insert.price }}</span>
                        <button type="button" class="p-1 text-gray-400 hover:text-gray-600" :aria-label="trans('More actions')">
                            <FontAwesomeIcon :icon="['fal', 'ellipsis-v']" fixed-width aria-hidden="true" />
                        </button>
                    </label>
                </div>

                <div class="mt-3 rounded-md px-4 py-2 text-xs flex gap-2 items-start" :style="{ backgroundColor: accentStyle.softBg, color: accentStyle.text }">
                    <FontAwesomeIcon :icon="['fal', 'info-circle']" class="mt-0.5" fixed-width aria-hidden="true" />
                    <span>
                        {{ trans("All leaflets and inserts must be uploaded by you.") }}<br />
                        {{ trans("Size information for printing will be added here once our printer is confirmed.") }}
                    </span>
                </div>
            </section>

            <!-- Section 3: Personalised message -->
            <section class="lg:col-span-2 rounded-lg border border-gray-200 bg-white p-4 sm:p-6">
                <h2 class="text-base font-semibold">
                    3. {{ trans("Personalised Message") }}
                    <span class="font-normal text-gray-500">({{ trans("Optional") }})</span>
                </h2>
                <p class="text-sm text-gray-500 mb-4">{{ trans("This message will be printed and included with all orders.") }}</p>

                <div class="relative">
                    <Textarea
                        v-model="personalisedMessage"
                        :maxlength="maxMessageLength"
                        rows="5"
                        class="w-full text-sm"
                        :placeholder="trans('Write your message here')"
                    />
                    <div class="absolute bottom-2 right-3 text-xs text-gray-400">
                        {{ personalisedMessage.length }}/{{ maxMessageLength }}
                    </div>
                </div>

                <div class="mt-3 rounded-md px-4 py-2 text-xs flex gap-2 items-start" :style="{ backgroundColor: accentStyle.softBg, color: accentStyle.text }">
                    <FontAwesomeIcon :icon="['fal', 'info-circle']" class="mt-0.5" fixed-width aria-hidden="true" />
                    <span>{{ trans("This message can be changed or removed at any time.") }}</span>
                </div>
            </section>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <!-- Section 4: Manage leaflets -->
            <section class="lg:col-span-3 rounded-lg border border-gray-200 bg-white p-4 sm:p-6">
                <div class="mb-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-base font-semibold">4. {{ trans("Manage Your Leaflets & Inserts") }}</h2>
                        <Button type="tertiary" icon="fal fa-upload" :label="trans('Upload new leaflet')" />
                    </div>
                    <p class="text-sm text-gray-500">{{ trans("Upload and manage the leaflets and inserts available for your orders.") }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <th class="py-2 pr-4 font-medium">{{ trans("File name") }}</th>
                                <th class="py-2 pr-4 font-medium">{{ trans("Type") }}</th>
                                <th class="py-2 pr-4 font-medium">{{ trans("Size") }}</th>
                                <th class="py-2 pr-4 font-medium">{{ trans("Uploaded") }}</th>
                                <th class="py-2 pr-4 font-medium">{{ trans("Status") }}</th>
                                <th class="py-2 font-medium text-right">{{ trans("Actions") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="leaflet in leaflets" :key="leaflet.name">
                                <td class="py-3 pr-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-2">
                                        <FontAwesomeIcon :icon="['fal', 'file-pdf']" class="text-red-500" fixed-width aria-hidden="true" />
                                        {{ leaflet.name }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4 whitespace-nowrap">{{ leaflet.type }}</td>
                                <td class="py-3 pr-4 whitespace-nowrap">{{ leaflet.size }}</td>
                                <td class="py-3 pr-4 whitespace-nowrap">{{ leaflet.uploadedAt }}</td>
                                <td class="py-3 pr-4 whitespace-nowrap">
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                        {{ leaflet.status }}
                                    </span>
                                </td>
                                <td class="py-3 text-right whitespace-nowrap">
                                    <button type="button" class="p-1 text-gray-400 hover:text-gray-600" :aria-label="trans('Edit')">
                                        <FontAwesomeIcon :icon="['fal', 'pencil']" fixed-width aria-hidden="true" />
                                    </button>
                                    <button type="button" class="p-1 text-gray-400 hover:text-gray-600" :aria-label="trans('More actions')">
                                        <FontAwesomeIcon :icon="['fal', 'ellipsis-v']" fixed-width aria-hidden="true" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Default summary -->
            <aside class="lg:col-span-2 rounded-lg border p-4 sm:p-6 h-fit" :style="{ backgroundColor: accentStyle.softBg, borderColor: accentStyle.softBorder }">
                <h2 class="text-base font-semibold mb-4" :style="{ color: accentStyle.text }">{{ trans("Your Default Summary") }}</h2>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600">{{ trans("Default Packaging") }}</dt>
                        <dd class="font-medium text-right">{{ summary.defaultPackaging }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600">{{ trans("Inserts & Add-ons") }}</dt>
                        <dd class="font-medium">{{ summary.insertsSelected }} {{ trans("selected") }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600">{{ trans("Personalised Message") }}</dt>
                        <dd class="font-medium">{{ summary.hasMessage ? trans("Yes") : trans("No") }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-600">{{ trans("Leaflets Uploaded") }}</dt>
                        <dd class="font-medium">{{ summary.leafletsUploaded }}</dd>
                    </div>
                </dl>

                <p class="mt-4 text-sm font-medium" :style="{ color: accentStyle.text }">
                    {{ trans("These preferences will be applied to all new orders.") }}
                </p>

                <Button
                    type="save"
                    class="mt-4"
                    full
                    :loading="isSaving"
                    :icon="justSaved ? 'fal fa-check' : undefined"
                    :label="justSaved ? trans('Saved') : trans('Save settings')"
                    @click="saveSettings"
                />
            </aside>
        </div>
    </div>
</template>
