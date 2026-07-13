<!--
  - Author: Andi Ferdiawan
  - Created: Thu, 09 Jul 2026 10:30:00 Central Indonesia Time, Bali, Indonesia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed, inject, ref, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import { useLayoutStore } from "@/Stores/retinaLayout"
import { capitalize } from "@/Composables/capitalize"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { PageHeadingTypes } from "@/types/PageHeading"
import type { Image as ImageProxy } from "@/types/Image"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import Image from "@common/Components/Image.vue"
import Textarea from "primevue/textarea"
import Checkbox from "primevue/checkbox"
import Select from "primevue/select"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
    faBoxOpen,
    faCheck,
    faDownload,
    faEllipsisV,
    faFileAlt,
    faFileImage,
    faFilePdf,
    faGift,
    faInfoCircle,
    faPencil,
    faTrashAlt,
    faUpload,
} from "@fal"

library.add(faBoxOpen, faCheck, faDownload, faEllipsisV, faFileAlt, faFileImage, faFilePdf, faGift, faInfoCircle, faPencil, faTrashAlt, faUpload)

interface PackagingOption {
    family_code: string
    type: string
    label: string
    sizes: string | null
    price_min: number
    price_max: number
    image: { thumbnail?: ImageProxy, source?: ImageProxy } | null
}

interface LeafletOption {
    id: number
    label: string
    type: string
    type_label: string
    price: number
    family_codes: string[]
}

interface CustomerLeaflet {
    id: number
    leaflet_id: number
    family_code: string | null
    name: string
    mime_type: string | null
    type_label: string
    size: string | null
    uploaded_at: string | null
    state: string
    state_label: string
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    packagingOptions?: PackagingOption[]
    leafletOptions?: LeafletOption[]
    selectedFamilyCode?: string | null
    personalisedMessage?: string | null
    selectedLeafletIds?: number[]
    customerLeaflets?: CustomerLeaflet[]
    currencyCode?: string
    updateRoute?: { name: string }
    uploadRoute?: { name: string }
    leafletUpdateRoute?: { name: string }
    leafletDeleteRoute?: { name: string }
    leafletDownloadRoute?: { name: string }
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

const selectedPackaging = ref(props.selectedFamilyCode ?? packagingOptions.value[0]?.family_code ?? null)

const leafletOptions = computed(() => props.leafletOptions ?? [])

const savedEnabledInserts = (familyCode: string | null) =>
    Object.fromEntries(
        leafletOptions.value.map(leaflet => [
            leaflet.id,
            familyCode === props.selectedFamilyCode
                && (props.selectedLeafletIds ?? []).includes(leaflet.id),
        ])
    )

const enabledInserts = ref<Record<number, boolean>>(savedEnabledInserts(selectedPackaging.value))

watch(selectedPackaging, (familyCode) => {
    enabledInserts.value = savedEnabledInserts(familyCode)
})

const inserts = computed(() =>
    leafletOptions.value.filter(leaflet =>
        selectedPackaging.value && leaflet.family_codes.includes(selectedPackaging.value)
    )
)

const formatInsertPrice = (price: number) => {
    if (price === 0) {
        return trans("Free")
    }

    return locale.currencyFormat(props.currencyCode ?? "USD", price)
}

const personalisedMessage = ref(props.personalisedMessage ?? "Thank you for your order!\nWe hope you love our products.")
const maxMessageLength = 200

const leaflets = computed(() =>
    (props.customerLeaflets ?? []).filter(leaflet => leaflet.family_code === selectedPackaging.value)
)

const summary = computed(() => ({
    defaultPackaging: packagingOptions.value.find(option => option.family_code === selectedPackaging.value)?.label,
    insertsSelected: inserts.value.filter(insert => enabledInserts.value[insert.id]).length,
    hasMessage: personalisedMessage.value.trim().length > 0,
    leafletsUploaded: leaflets.value.length,
}))

const uploadedLeafletIds = computed(() => new Set(leaflets.value.map(leaflet => leaflet.leaflet_id)))

const availableUploadLeaflets = computed(() =>
    inserts.value.filter(insert => !uploadedLeafletIds.value.has(insert.id))
)

const isUploadOpen = ref(false)
const uploadLeafletId = ref<number | null>(null)
const uploadFile = ref<File | null>(null)
const uploadFileName = computed(() => uploadFile.value?.name ?? null)
const isUploading = ref(false)

const onUploadFileSelected = (event: Event) => {
    uploadFile.value = (event.target as HTMLInputElement).files?.[0] ?? null
}

const submitUpload = () => {
    if (!props.uploadRoute?.name || !uploadLeafletId.value || !uploadFile.value || !selectedPackaging.value) {
        return
    }

    router.post(
        route(props.uploadRoute.name),
        {
            leaflet_id: uploadLeafletId.value,
            family_code: selectedPackaging.value,
            file: uploadFile.value,
            active: !!enabledInserts.value[uploadLeafletId.value],
        },
        {
            preserveScroll: true,
            forceFormData: true,
            onStart: () => isUploading.value = true,
            onFinish: () => isUploading.value = false,
            onSuccess: () => {
                isUploadOpen.value = false
                uploadLeafletId.value = null
                uploadFile.value = null
            },
        }
    )
}

const editRow = ref<CustomerLeaflet | null>(null)
const editName = ref("")
const editFile = ref<File | null>(null)
const editFileName = computed(() => editFile.value?.name ?? null)
const isEditingLeaflet = ref(false)

const openEdit = (leaflet: CustomerLeaflet) => {
    editRow.value = leaflet
    editName.value = leaflet.name
    editFile.value = null
}

const onEditFileSelected = (event: Event) => {
    editFile.value = (event.target as HTMLInputElement).files?.[0] ?? null
}

const submitEdit = () => {
    if (!props.leafletUpdateRoute?.name || !editRow.value || !selectedPackaging.value) {
        return
    }

    router.post(
        route(props.leafletUpdateRoute.name),
        {
            leaflet_id: editRow.value.leaflet_id,
            family_code: selectedPackaging.value,
            name: editName.value,
            ...(editFile.value ? { file: editFile.value } : {}),
        },
        {
            preserveScroll: true,
            forceFormData: true,
            onStart: () => isEditingLeaflet.value = true,
            onFinish: () => isEditingLeaflet.value = false,
            onSuccess: () => {
                editRow.value = null
                editFile.value = null
            },
        }
    )
}

const leafletFileIcon = (leaflet: CustomerLeaflet) =>
    leaflet.mime_type?.startsWith("image/") ? "file-image" : "file-pdf"

const leafletDownloadUrl = (leaflet: CustomerLeaflet) =>
    props.leafletDownloadRoute?.name ? route(props.leafletDownloadRoute.name, [leaflet.id]) : "#"

const deletingLeafletId = ref<number | null>(null)

const deleteLeaflet = (leaflet: CustomerLeaflet) => {
    if (!props.leafletDeleteRoute?.name || !selectedPackaging.value) {
        return
    }

    router.post(
        route(props.leafletDeleteRoute.name),
        {
            leaflet_id: leaflet.leaflet_id,
            family_code: selectedPackaging.value,
        },
        {
            preserveScroll: true,
            onStart: () => deletingLeafletId.value = leaflet.id,
            onFinish: () => deletingLeafletId.value = null,
        }
    )
}

const isSaving = ref(false)
const justSaved = ref(false)
const saveSettings = () => {
    if (!props.updateRoute?.name || !selectedPackaging.value) {
        return
    }

    router.post(
        route(props.updateRoute.name),
        {
            family_code: selectedPackaging.value,
            leaflet_ids: inserts.value
                .filter(insert => enabledInserts.value[insert.id])
                .map(insert => insert.id),
            personalised_message: personalisedMessage.value.trim() || null,
        },
        {
            preserveScroll: true,
            onStart: () => isSaving.value = true,
            onFinish: () => isSaving.value = false,
            onSuccess: () => {
                justSaved.value = true
                setTimeout(() => justSaved.value = false, 2000)
            },
        }
    )
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
                        :key="insert.id"
                        class="flex items-center gap-3 py-3 cursor-pointer"
                    >
                        <Checkbox v-model="enabledInserts[insert.id]" :binary="true" />
                        <span class="flex h-8 w-8 items-center justify-center rounded border border-gray-200 text-gray-400">
                            <FontAwesomeIcon :icon="['fal', 'file-alt']" fixed-width aria-hidden="true" />
                        </span>
                        <span class="flex-1">
                            <span class="block text-sm">
                                <span class="font-medium">{{ insert.label }}</span>
                                <span class="ml-1 text-xs font-medium" :style="{ color: accentStyle.text }">({{ trans("Size to be confirmed") }})</span>
                            </span>
                            <span class="block text-xs text-gray-500">{{ insert.type_label }}</span>
                        </span>
                        <span class="text-sm text-gray-600">{{ formatInsertPrice(insert.price) }}</span>
                        <button type="button" class="p-1 text-gray-400 hover:text-gray-600" :aria-label="trans('More actions')">
                            <FontAwesomeIcon :icon="['fal', 'ellipsis-v']" fixed-width aria-hidden="true" />
                        </button>
                    </label>
                </div>

                <div v-if="!inserts.length" class="rounded-md border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">
                    {{ trans("No inserts or leaflets are available for the selected packaging.") }}
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
                        <Button
                            type="tertiary"
                            icon="fal fa-upload"
                            :label="trans('Upload new leaflet')"
                            @click="isUploadOpen = !isUploadOpen"
                        />
                    </div>
                    <p class="text-sm text-gray-500">{{ trans("Upload and manage the leaflets and inserts available for your orders.") }}</p>
                </div>

                <div v-if="isUploadOpen" class="mb-4 rounded-lg border border-gray-200 p-4">
                    <p class="mb-3 text-xs text-gray-500">
                        {{ trans("This file will be linked to the selected packaging") }}:
                        <span class="font-medium" :style="{ color: accentStyle.text }">{{ summary.defaultPackaging }}</span>
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">
                                {{ trans("Leaflet") }} <span class="text-red-500">*</span>
                            </label>
                            <Select
                                v-model="uploadLeafletId"
                                :options="availableUploadLeaflets"
                                optionLabel="label"
                                optionValue="id"
                                :placeholder="trans('Select a leaflet')"
                                :emptyMessage="trans('All leaflets already have a file for this packaging')"
                                class="w-full"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">
                                {{ trans("File") }} <span class="text-red-500">*</span>
                            </label>
                            <label class="flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 text-sm hover:border-gray-400">
                                <FontAwesomeIcon :icon="['fal', 'upload']" class="text-gray-400" fixed-width aria-hidden="true" />
                                <span class="truncate" :class="uploadFileName ? '' : 'text-gray-400'">
                                    {{ uploadFileName ?? trans("Choose a PDF or image (max 20MB)") }}
                                </span>
                                <input type="file" accept=".pdf,image/*" class="hidden" @change="onUploadFileSelected" />
                            </label>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end gap-2">
                        <Button type="cancel" :label="trans('Cancel')" @click="isUploadOpen = false" />
                        <Button
                            type="upload"
                            :loading="isUploading"
                            :disabled="!uploadLeafletId || !uploadFile"
                            :label="trans('Upload')"
                            @click="submitUpload"
                        />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <th class="py-2 pr-4 font-medium">{{ trans("File name") }}</th>
                                <th class="py-2 pr-4 font-medium">{{ trans("Type") }}</th>
                                <!-- <th class="py-2 pr-4 font-medium">{{ trans("Size") }}</th> -->
                                <th class="py-2 pr-4 font-medium">{{ trans("Uploaded") }}</th>
                                <th class="py-2 pr-4 font-medium">{{ trans("Status") }}</th>
                                <th class="py-2 font-medium text-right">{{ trans("Actions") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template v-for="leaflet in leaflets" :key="leaflet.id">
                                <tr>
                                    <td class="py-3 pr-4 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-2">
                                            <FontAwesomeIcon
                                                :icon="['fal', leafletFileIcon(leaflet)]"
                                                :class="leaflet.mime_type?.startsWith('image/') ? 'text-blue-500' : 'text-red-500'"
                                                fixed-width
                                                aria-hidden="true"
                                            />
                                            {{ leaflet.name }}
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 whitespace-nowrap">{{ leaflet.type_label }}</td>
                                    <td class="py-3 pr-4 whitespace-nowrap">{{ leaflet.uploaded_at ?? "-" }}</td>
                                    <td class="py-3 pr-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="leaflet.state === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                                        >
                                            {{ leaflet.state_label }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-right whitespace-nowrap">
                                        <a
                                            :href="leafletDownloadUrl(leaflet)"
                                            target="_blank"
                                            class="p-1 text-gray-400 hover:text-gray-600"
                                            :aria-label="trans('Download')"
                                            v-tooltip="trans('Download')"
                                        >
                                            <FontAwesomeIcon :icon="['fal', 'download']" fixed-width aria-hidden="true" />
                                        </a>
                                        <button type="button" class="p-1 text-gray-400 hover:text-gray-600" :aria-label="trans('Edit')" @click="openEdit(leaflet)">
                                            <FontAwesomeIcon :icon="['fal', 'pencil']" fixed-width aria-hidden="true" />
                                        </button>
                                        <button
                                            type="button"
                                            class="p-1 text-red-400 hover:text-red-600 disabled:text-gray-300"
                                            :aria-label="trans('Delete')"
                                            :disabled="deletingLeafletId === leaflet.id"
                                            @click="deleteLeaflet(leaflet)"
                                        >
                                            <FontAwesomeIcon :icon="['fal', 'trash-alt']" fixed-width aria-hidden="true" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="editRow?.id === leaflet.id">
                                    <td colspan="5" class="pb-4">
                                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium mb-1">{{ trans("File name") }}</label>
                                                    <PureInput v-model="editName" :placeholder="trans('File name')" />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium mb-1">{{ trans("Replace file") }}</label>
                                                    <label class="flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 text-sm hover:border-gray-400 bg-white">
                                                        <FontAwesomeIcon :icon="['fal', 'upload']" class="text-gray-400" fixed-width aria-hidden="true" />
                                                        <span class="truncate" :class="editFileName ? '' : 'text-gray-400'">
                                                            {{ editFileName ?? trans("Keep current file (optional)") }}
                                                        </span>
                                                        <input type="file" accept=".pdf,image/*" class="hidden" @change="onEditFileSelected" />
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="mt-3 flex justify-end gap-2">
                                                <Button type="cancel" :label="trans('Cancel')" @click="editRow = null" />
                                                <Button
                                                    type="save"
                                                    :loading="isEditingLeaflet"
                                                    :disabled="!editName.trim()"
                                                    :label="trans('Save')"
                                                    @click="submitEdit"
                                                />
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div v-if="!leaflets.length" class="rounded-md border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">
                        {{ trans("You have not uploaded any leaflets yet.") }}
                    </div>
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
