<!--
  - Author Louis Perez
  - Created on 22-10-2026-18h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import TicketAttachmentPreview, { type TicketAttachment } from "@/Components/Tickets/TicketAttachmentPreview.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPaperclip, faFilePdf, faPlus, faTimes, faEye, faDownload, faSpinner, faCheck, faImage, faPencil, faFilm } from "@fal"
library.add(faPaperclip, faFilePdf, faPlus, faTimes, faEye, faDownload, faSpinner, faCheck, faImage, faPencil, faFilm)

export interface BriefFile {
    id: number
    name: string | null
    mime: string | null
    size: number | null
    url: string | null
    preview_url: string | null
    remove_route: routeType | null
}

export interface BriefEditor {
    can_edit: boolean
    can_upload: boolean
    references: BriefFile[]
    products: {
        id: number
        code: string
        name: string
        image: (Record<string, string> & { original: string }) | null
        brief: string | null
        images: BriefFile[]
        brief_route: routeType | null
    }[]
    routes: { update: routeType | null, upload: routeType | null }
}

const props = defineProps<{
    brief: string | null
    editor: BriefEditor
}>()

const fileIcon = (file: BriefFile) => (file.mime ?? "").startsWith("image/") ? "fal fa-image" : (file.mime ?? "").startsWith("video/") ? "fal fa-film" : "fal fa-file-pdf"
const formatSize = (bytes: number | null) => !bytes ? "" : bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`
const firstError = (errors: Record<string, string>) => Object.values(errors)[0]

/* General brief: saved when the box is left */

const editingGeneral = ref(false)
const generalBrief = ref(props.brief ?? "")
const savingBrief = ref(false)

function editGeneralBrief() {
    generalBrief.value = props.brief ?? ""
    editingGeneral.value = true
}

function saveGeneralBrief() {
    const route_ = props.editor.routes.update
    if (!route_) {
        return
    }
    router.patch(route(route_.name, route_.parameters), { brief: generalBrief.value || null }, {
        preserveScroll: true,
        onStart: () => savingBrief.value = true,
        onFinish: () => savingBrief.value = false,
        onSuccess: () => editingGeneral.value = false,
        onError: (errors) => notify({ title: ctrans("Brief not saved"), text: firstError(errors), type: "error" }),
    })
}

/* Product briefs: each saved when its box is left */

const editingProduct = ref<number | null>(null)
const productBrief = ref("")
const savingProduct = ref<number | null>(null)

function editProductBrief(product: BriefEditor["products"][number]) {
    productBrief.value = product.brief ?? ""
    editingProduct.value = product.id
}

function saveProductBrief(product: BriefEditor["products"][number]) {
    if (!product.brief_route) {
        return
    }
    router.patch(route(product.brief_route.name, product.brief_route.parameters), { brief: productBrief.value || null }, {
        preserveScroll: true,
        onStart: () => savingProduct.value = product.id,
        onFinish: () => savingProduct.value = null,
        onSuccess: () => editingProduct.value = null,
        onError: (errors) => notify({ title: ctrans("Brief not saved"), text: firstError(errors), type: "error" }),
    })
}

/* Uploads: brief PDFs for the range, reference images per product */

const uploading = ref<string | null>(null)
const pdfInput = ref<HTMLInputElement | null>(null)
const imageInput = ref<HTMLInputElement | null>(null)
const imageFor = ref<number | null>(null)

function pickImages(productId: number) {
    imageFor.value = productId
    imageInput.value?.click()
}

function upload(files: File[], scope: "brief_reference" | "brief_image", itemId: number | null) {
    const route_ = props.editor.routes.upload
    if (!route_ || !files.length) {
        return
    }
    router.post(route(route_.name, route_.parameters), { files, scope, product_development_item_id: itemId }, {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => uploading.value = itemId ? `item-${itemId}` : "references",
        onFinish: () => uploading.value = null,
        onError: (errors) => notify({ title: ctrans("Not uploaded"), text: firstError(errors), type: "error" }),
    })
}

function onPdfsPicked(event: Event) {
    const input = event.target as HTMLInputElement
    upload([...(input.files ?? [])], "brief_reference", null)
    input.value = ""
}

function onImagesPicked(event: Event) {
    const input = event.target as HTMLInputElement
    upload([...(input.files ?? [])], "brief_image", imageFor.value)
    input.value = ""
}

function remove(file: BriefFile) {
    if (!file.remove_route) {
        return
    }
    router.delete(route(file.remove_route.name, file.remove_route.parameters), {
        preserveScroll: true,
        onError: (errors) => notify({ title: ctrans("Not removed"), text: firstError(errors), type: "error" }),
    })
}

/* Preview: the same viewer as the Files tab */

const preview = ref<{ files: TicketAttachment[], index: number | null }>({ files: [], index: null })

function openPreview(files: BriefFile[], file: BriefFile) {
    const viewable = files.filter((candidate) => candidate.preview_url)
    preview.value = {
        files: viewable.map((candidate) => ({ name: candidate.name ?? "", url: candidate.preview_url!, mime: candidate.mime })),
        index: viewable.findIndex((candidate) => candidate.id === file.id),
    }
}
</script>

<template>
    <div class="space-y-4">
        <section class="rounded-lg border border-gray-200 bg-white px-4 py-4">
            <div class="mb-2 flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-gray-900">{{ ctrans("General brief") }}</h3>
                <button v-if="editor.can_edit && !editingGeneral" type="button" class="flex h-6 w-6 items-center justify-center rounded text-[--app-accent] transition-colors hover:bg-[--app-accent-soft] hover:text-[--app-accent-strong]" :aria-label="ctrans('Edit the general brief')" @click="editGeneralBrief">
                    <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                </button>
            </div>
            <div v-if="editingGeneral" class="space-y-2">
                <Textarea v-model="generalBrief" rows="5" autoResize class="w-full text-sm" :placeholder="ctrans('Write the general brief for the range here…')" />
                <div class="flex justify-end gap-2">
                    <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="editingGeneral = false">
                        <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="tertiary" size="xs" :label="ctrans('Save')" :loading="savingBrief" @click="saveGeneralBrief">
                        <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </div>
            <p v-else-if="brief" class="whitespace-pre-line break-words text-sm text-gray-800">{{ brief }}</p>
            <p v-else class="text-sm text-gray-400">{{ ctrans("No brief written yet") }}</p>

            <div v-if="editingGeneral || editor.references.length" class="mt-3 rounded-md border border-dashed border-gray-300 px-3 py-3">
                <div v-if="editingGeneral" class="flex flex-wrap items-center gap-3">
                    <FontAwesomeIcon icon="fal fa-paperclip" class="text-lg text-[--app-accent]" fixed-width aria-hidden="true" />
                    <div class="min-w-0 flex-1">
                        <div class="text-sm text-gray-900">{{ ctrans("Attach files") }} <span class="text-xs text-gray-400">{{ ctrans("(optional)") }}</span></div>
                        <div class="text-xs text-gray-500">{{ ctrans("A concept, mood board or other reference for the designer: PDF, image or video.") }}</div>
                    </div>
                    <Button v-if="editor.routes.upload" type="tertiary" size="xs" :label="ctrans('Upload')" :loading="uploading === 'references'" @click="pdfInput?.click()">
                        <template #icon><FontAwesomeIcon icon="fal fa-paperclip" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
                <h4 v-else class="text-xs font-medium text-gray-500">{{ ctrans("Attachments") }}</h4>
                <ul v-if="editor.references.length" class="mt-2 space-y-1.5">
                    <li v-for="file in editor.references" :key="file.id" class="flex items-center gap-2 rounded-md bg-gray-50 px-2.5 py-1.5 text-sm">
                        <FontAwesomeIcon :icon="fileIcon(file)" :class="fileIcon(file) === 'fal fa-file-pdf' ? 'text-red-500' : 'text-gray-500'" fixed-width aria-hidden="true" />
                        <button type="button" class="min-w-0 flex-1 truncate text-left text-gray-800 hover:text-[--app-accent]" @click="openPreview(editor.references, file)">{{ file.name }}</button>
                        <span class="shrink-0 text-xs tabular-nums text-gray-400">{{ formatSize(file.size) }}</span>
                        <a v-if="file.url" :href="file.url" class="flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-gray-100" v-tooltip="ctrans('Download')" :aria-label="ctrans('Download')">
                            <FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
                        </a>
                        <button v-if="editingGeneral && file.remove_route" type="button" class="flex h-7 w-7 items-center justify-center rounded text-red-500 hover:bg-red-50" v-tooltip="ctrans('Remove')" :aria-label="ctrans('Remove')" @click="remove(file)">
                            <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                        </button>
                    </li>
                </ul>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white px-4 py-4">
            <h3 class="mb-3 text-sm font-semibold text-gray-900">{{ ctrans("Products") }}</h3>
            <ul class="space-y-2">
                <li v-for="product in editor.products" :key="product.id" class="grid items-start gap-3 rounded-md border border-gray-200 p-3 md:grid-cols-[minmax(0,14rem)_auto_minmax(0,1fr)]">
                    <div class="flex items-center gap-3">
                        <div class="h-14 w-14 shrink-0 overflow-hidden rounded-md bg-gray-100">
                            <Image v-if="product.image" :src="product.image" :alt="product.code" class="h-full w-full object-cover" />
                            <div v-else class="flex h-full w-full items-center justify-center text-gray-300"><FontAwesomeIcon icon="fal fa-image" fixed-width aria-hidden="true" /></div>
                        </div>
                        <div class="min-w-0">
                            <div class="whitespace-nowrap font-medium text-gray-900">{{ product.code }}</div>
                            <div class="line-clamp-2 text-sm text-gray-500" :title="product.name">{{ product.name }}</div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div v-for="file in product.images" :key="file.id" class="group relative h-14 w-14 overflow-hidden rounded-md border border-gray-200">
                            <button type="button" class="h-full w-full" :aria-label="ctrans('Preview')" @click="openPreview(product.images, file)">
                                <img v-if="file.preview_url" :src="file.preview_url" :alt="file.name ?? ''" class="h-full w-full object-cover" loading="lazy" />
                            </button>
                            <button
                                v-if="editingProduct === product.id && file.remove_route"
                                type="button"
                                class="absolute right-0.5 top-0.5 hidden h-5 w-5 items-center justify-center rounded-full bg-white/90 text-xs text-red-500 shadow group-hover:flex"
                                :aria-label="ctrans('Remove')"
                                @click="remove(file)"
                            >
                                <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                            </button>
                        </div>
                        <button
                            v-if="editingProduct === product.id && editor.routes.upload"
                            type="button"
                            class="flex h-14 w-14 items-center justify-center rounded-md border border-dashed border-gray-300 text-gray-400 hover:border-[--app-accent] hover:text-[--app-accent]"
                            v-tooltip="ctrans('Add reference images')"
                            :aria-label="ctrans('Add reference images')"
                            @click="pickImages(product.id)"
                        >
                            <FontAwesomeIcon :icon="uploading === `item-${product.id}` ? 'fal fa-spinner' : 'fal fa-plus'" :spin="uploading === `item-${product.id}`" fixed-width aria-hidden="true" />
                        </button>
                    </div>
                    <div v-if="editingProduct === product.id" class="space-y-2">
                        <Textarea v-model="productBrief" rows="2" autoResize class="w-full text-sm" :placeholder="ctrans('Add a short brief for this product…')" />
                        <div class="flex justify-end gap-2">
                            <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="editingProduct = null">
                                <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                            </Button>
                            <Button type="tertiary" size="xs" :label="ctrans('Save')" :loading="savingProduct === product.id" @click="saveProductBrief(product)">
                                <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                            </Button>
                        </div>
                    </div>
                    <div v-else class="flex items-start gap-2">
                        <p class="min-w-0 flex-1 whitespace-pre-line text-sm" :class="product.brief ? 'text-gray-800' : 'text-gray-400'">{{ product.brief || ctrans("No product brief yet") }}</p>
                        <button v-if="product.brief_route" type="button" class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-[--app-accent] transition-colors hover:bg-[--app-accent-soft] hover:text-[--app-accent-strong]" :aria-label="ctrans('Edit the brief of :sku', { sku: product.code })" @click="editProductBrief(product)">
                            <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                        </button>
                    </div>
                </li>
                <li v-if="!editor.products.length" class="text-sm text-gray-400">{{ ctrans("No products yet") }}</li>
            </ul>
        </section>

        <input ref="pdfInput" type="file" accept="application/pdf,image/*,video/*" multiple class="hidden" @change="onPdfsPicked" />
        <input ref="imageInput" type="file" accept="image/*" multiple class="hidden" @change="onImagesPicked" />
        <TicketAttachmentPreview v-model:index="preview.index" :files="preview.files" />
    </div>
</template>
