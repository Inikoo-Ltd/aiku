<!--
  - Author Louis Perez
  - Created on 23-10-2026-11h-20m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import Drawer from "primevue/drawer"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import TicketUserHoverCard from "@/Components/Tickets/TicketUserHoverCard.vue"
import TicketBody from "@/Components/Tickets/TicketBody.vue"
import ProductDevelopmentPhotoStrip, { type ProductDevelopmentPhoto } from "@/Components/Goods/ProductDevelopment/ProductDevelopmentPhotoStrip.vue"
import ProductDevelopmentCommentComposer, { type Mentionable } from "@/Components/Goods/ProductDevelopment/ProductDevelopmentCommentComposer.vue"
import { productDevelopmentColor } from "@/Components/Goods/ProductDevelopment/productDevelopmentColors"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFilePdf, faEye, faDownload, faPencilRuler, faCloudUpload, faCheck, faUndo, faExclamationCircle, faSpinner, faImage, faColumns, faComments, faTimes, faBoxOpen } from "@fal"
library.add(faFilePdf, faEye, faDownload, faPencilRuler, faCloudUpload, faCheck, faUndo, faExclamationCircle, faSpinner, faImage, faColumns, faComments, faTimes, faBoxOpen)

export interface ArtworkStatus {
    value: string
    label: string
    color: string
    icon: string
}

export interface Artwork {
    id: number
    source: "upload" | "aiku" | null
    version: number
    file_name: string | null
    original_name: string | null
    size: number | null
    updated_at: string | null
    updated_by: string | null
    update_note: string | null
    review_comment: string | null
    reviewed_by: string | null
    reviewed_at: string | null
    pdf_route: routeType | null
    editor_route: routeType | null
    can_edit_layout: boolean
    can_review: boolean
    approval_blocked: string | null
    review_route: routeType
    comments: number
    comments_route: routeType
    versions: ArtworkVersion[]
    can_cancel_aiku: boolean
    cancel_aiku_route: routeType
    needs_recheck: boolean
    packaging_sample: PackagingSample
}

export interface PackagingSample {
    status: "approved" | "changes_required" | null
    note: string | null
    reviewed_by: string | null
    reviewed_at: string | null
    photos: ProductDevelopmentPhoto[]
    can_check: boolean
    routes: { photos: routeType, review: routeType }
}

export interface ArtworkVersion {
    version: number
    is_current: boolean
    status: ArtworkStatus
    source: "upload" | "aiku" | null
    update_note: string | null
    submitted_by: string | null
    submitted_at: string | null
    review_comment: string | null
    reviewed_by: string | null
    reviewed_at: string | null
    pdf_route: routeType | null
}

export interface ArtworkRow {
    item_id: number
    code: string
    name: string
    image: (Record<string, string> & { original: string }) | null
    status: ArtworkStatus
    artwork: Artwork | null
    can_upload: boolean
    label_content_route: routeType
}

interface ThreadComment {
    id: number
    body: string
    user_id: number | null
    user: string | null
    username: string | null
    role: string | null
    avatar: Record<string, string> | null
    created_at: string | null
    images?: Record<string, string>[]
    attachments?: any[]
    version: number | null
    review: "approve" | "request_changes" | null
    packaging_sample: "approved" | "changes_required" | null
    replies?: ThreadComment[]
}

const props = defineProps<{
    sectionName: string
    row: ArtworkRow | null
    pdfUrl: string
}>()

const visible = defineModel<boolean>("visible", { required: true })

const emit = defineEmits<{
    preview: []
    compare: []
    upload: []
    aiku: []
    cancelAiku: []
}>()

const formatDate = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM yyyy" }) : ""
const formatWhen = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM yyyy, HH:mm" }) : ""

const artwork = computed(() => props.row?.artwork ?? null)

/* Review */

const comment = ref("")
const reviewing = ref<string | null>(null)
const reviewError = ref<string | null>(null)

function review(operation: "approve" | "request_changes") {
    if (!artwork.value) {
        return
    }
    router.patch(route(artwork.value.review_route.name, artwork.value.review_route.parameters), { operation, comment: comment.value || null }, {
        preserveScroll: true,
        only: ["design"],
        onStart: () => { reviewing.value = operation; reviewError.value = null },
        onFinish: () => reviewing.value = null,
        onError: (errors) => reviewError.value = Object.values(errors)[0] ?? null,
        onSuccess: () => comment.value = "",
    })
}

/* Packaging sample: the designer checks the printed box or label */

const packagingComment = ref("")
const checkingPackaging = ref<string | null>(null)
const packagingError = ref<string | null>(null)

function checkPackagingSample(status: "approved" | "changes_required") {
    const sample = artwork.value?.packaging_sample
    if (!sample) {
        return
    }
    router.patch(route(sample.routes.review.name, sample.routes.review.parameters), { status, comment: packagingComment.value || null }, {
        preserveScroll: true,
        only: ["design"],
        onStart: () => { checkingPackaging.value = status; packagingError.value = null },
        onFinish: () => checkingPackaging.value = null,
        onError: (errors) => packagingError.value = Object.values(errors)[0] ?? null,
        onSuccess: () => packagingComment.value = "",
    })
}

/* Discussion */

const thread = ref<{ threads: ThreadComment[], mentionables: Mentionable[], route: routeType } | null>(null)
const loadingThread = ref(false)
const replyingTo = ref<number | null>(null)

const comments = computed(() => [...(thread.value?.threads ?? [])].sort((a, b) => a.id - b.id))

async function loadThread() {
    const target = artwork.value
    if (!target) {
        thread.value = null
        return
    }
    loadingThread.value = true
    try {
        const { data } = await axios.get(route(target.comments_route.name, target.comments_route.parameters))
        if (artwork.value?.id === target.id) {
            thread.value = data
        }
    } finally {
        loadingThread.value = false
    }
}

watch(() => [visible.value, artwork.value?.id], ([isVisible], previous) => {
    if (!isVisible) {
        return
    }
    if (previous?.[1] !== artwork.value?.id || !previous?.[0]) {
        comment.value = ""
        reviewError.value = null
        packagingComment.value = ""
        packagingError.value = null
        replyingTo.value = null
        thread.value = null
        loadThread()
    }
}, { immediate: true })

watch(() => artwork.value?.comments, (count, previous) => {
    if (visible.value && previous !== undefined && count !== previous) {
        loadThread()
    }
})
</script>

<template>
    <Drawer v-model:visible="visible" position="right" class="!w-full sm:!w-[34rem]" :header="row ? `${sectionName} · ${row.code}` : ''">
        <div v-if="row" class="space-y-5 text-sm">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 shrink-0 overflow-hidden rounded-md bg-gray-100">
                    <Image v-if="row.image" :src="row.image" :alt="row.code" class="h-full w-full object-cover" />
                    <div v-else class="flex h-full w-full items-center justify-center text-gray-300">
                        <FontAwesomeIcon icon="fal fa-image" fixed-width aria-hidden="true" />
                    </div>
                </div>
                <div class="min-w-0">
                    <div class="whitespace-nowrap font-medium text-gray-900">{{ row.code }}</div>
                    <div class="text-xs text-gray-500">{{ row.name }}</div>
                </div>
            </div>

            <section class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Design") }}</h4>
                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-medium" :class="productDevelopmentColor(row.status.color).active">
                        <FontAwesomeIcon :icon="row.status.icon" fixed-width aria-hidden="true" />
                        {{ row.status.label }}
                    </span>
                </div>

                <div v-if="artwork?.pdf_route" class="flex items-start gap-3 rounded-md border border-gray-200 px-3 py-2.5">
                    <FontAwesomeIcon icon="fal fa-file-pdf" class="mt-0.5 text-xl text-red-500" fixed-width aria-hidden="true" />
                    <div class="min-w-0 flex-1">
                        <button type="button" class="block max-w-full truncate text-left font-medium text-gray-900 hover:text-[--app-accent]" @click="emit('preview')">{{ artwork.file_name }}</button>
                        <div class="text-xs text-gray-500">
                            <template v-if="artwork.updated_at">{{ ctrans("Uploaded :date by :name", { date: formatDate(artwork.updated_at), name: artwork.updated_by ?? "" }) }}</template>
                            <template v-if="artwork.source === 'aiku'"> · {{ ctrans("Made in Aiku") }}</template>
                        </div>
                        <p v-if="artwork.update_note" class="mt-1 text-xs text-gray-700">{{ artwork.update_note }}</p>
                    </div>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800" v-tooltip="ctrans('Preview')" :aria-label="ctrans('Preview')" @click="emit('preview')">
                        <FontAwesomeIcon icon="fal fa-eye" fixed-width aria-hidden="true" />
                    </button>
                    <a :href="pdfUrl" class="flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800" v-tooltip="ctrans('Download')" :aria-label="ctrans('Download')">
                        <FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
                    </a>
                </div>
                <p v-else-if="artwork?.source === 'aiku'" class="rounded-md border border-dashed border-gray-300 px-3 py-2.5 text-xs text-gray-500">{{ ctrans("Being made in Aiku") }}</p>
                <p v-else class="rounded-md border border-dashed border-amber-300 px-3 py-2.5 text-xs text-amber-700">{{ ctrans("Missing artwork") }}</p>

                <div v-if="row.can_upload || artwork?.editor_route" class="flex flex-wrap gap-2">
                    <Button v-if="row.can_upload && (artwork?.source !== 'aiku' || row.status.value === 'in_review')" type="tertiary" size="xs" :label="artwork?.pdf_route ? ctrans('Replace with a PDF') : ctrans('Upload PDF')" @click="emit('upload')">
                        <template #icon><FontAwesomeIcon icon="fal fa-cloud-upload" class="text-[--app-accent]" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button v-if="(row.can_upload && (artwork?.source !== 'upload' || row.status.value === 'in_review')) || artwork?.editor_route" type="tertiary" size="xs" :label="artwork?.source === 'aiku' ? (!row.can_upload ? ctrans('Open in Aiku') : artwork.can_edit_layout ? ctrans('Continue in Aiku') : ctrans('Make changes in Aiku')) : ctrans('Create in Aiku')" @click="emit('aiku')">
                        <template #icon><FontAwesomeIcon icon="fal fa-pencil-ruler" class="text-[--app-accent]" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button v-if="artwork?.can_cancel_aiku" type="tertiary" size="xs" :label="ctrans('Cancel Aiku design')" @click="emit('cancelAiku')">
                        <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>

                <p v-if="artwork?.needs_recheck" class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <FontAwesomeIcon icon="fal fa-exclamation-circle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
                    {{ ctrans("The label information of this SKU changed after the artwork was approved. Check the artwork still matches it.") }}
                </p>
                <p v-if="artwork?.reviewed_at && (row.status.value === 'approved' || row.status.value === 'changes_required')" class="text-xs text-gray-500">
                    {{ row.status.value === "approved"
                        ? ctrans("Approved by :name on :date", { name: artwork.reviewed_by ?? "", date: formatWhen(artwork.reviewed_at) })
                        : ctrans("Changes asked by :name on :date", { name: artwork.reviewed_by ?? "", date: formatWhen(artwork.reviewed_at) }) }}
                </p>

                <div v-if="artwork?.can_review" class="space-y-2 rounded-md border border-gray-200 bg-gray-50 p-3">
                    <p v-if="artwork.approval_blocked" class="flex gap-2 text-xs text-amber-800">
                        <FontAwesomeIcon icon="fal fa-exclamation-circle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
                        {{ ctrans(":reason. You can still ask for changes.", { reason: artwork.approval_blocked }) }}
                    </p>
                    <Textarea v-model="comment" rows="2" autoResize class="w-full text-sm" :invalid="!!reviewError" :placeholder="ctrans('Comment (required to ask for changes)')" />
                    <p v-if="reviewError" class="text-xs text-red-600">{{ reviewError }}</p>
                    <Button v-if="artwork.pdf_route" type="tertiary" size="xs" :label="ctrans('Check against the label information')" @click="emit('compare')">
                        <template #icon><FontAwesomeIcon icon="fal fa-columns" class="text-[--app-accent]" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <Button type="tertiary" size="xs" :label="ctrans('Changes required')" :loading="reviewing === 'request_changes'" @click="review('request_changes')">
                            <template #icon><FontAwesomeIcon icon="fal fa-undo" class="text-orange-500" fixed-width aria-hidden="true" /></template>
                        </Button>
                        <Button type="tertiary" size="xs" :label="ctrans('Approve')" :loading="reviewing === 'approve'" @click="review('approve')">
                            <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </div>
                </div>
            </section>

            <section v-if="artwork && row.status.value === 'approved'" class="space-y-3 rounded-lg border border-gray-200 bg-gray-50/60 p-3">
                <div class="flex items-center justify-between gap-2">
                    <h4 class="flex items-center gap-1.5 text-sm font-semibold text-gray-800">
                        <FontAwesomeIcon icon="fal fa-box-open" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                        {{ ctrans("Check the printed sample") }}
                    </h4>
                    <span v-if="artwork.packaging_sample.status" class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium" :class="artwork.packaging_sample.status === 'approved' ? 'bg-green-50 text-green-700' : 'bg-orange-50 text-orange-700'">
                        <FontAwesomeIcon :icon="artwork.packaging_sample.status === 'approved' ? 'fal fa-check' : 'fal fa-undo'" fixed-width aria-hidden="true" />
                        {{ artwork.packaging_sample.status === "approved" ? ctrans("Approved") : ctrans("Changes needed") }}
                    </span>
                </div>

                <p class="text-xs text-gray-500">{{ ctrans("Photos of the printed box or label, checked by the designer.") }}</p>
                <ProductDevelopmentPhotoStrip
                    :photos="artwork.packaging_sample.photos"
                    :uploadRoute="artwork.packaging_sample.can_check ? artwork.packaging_sample.routes.photos : null"
                    :only="['design']"
                    :emptyText="ctrans('No photos yet')"
                />

                <p v-if="artwork.packaging_sample.reviewed_at" class="text-xs text-gray-500">
                    {{ artwork.packaging_sample.status === "approved"
                        ? ctrans("Approved by :name on :date", { name: artwork.packaging_sample.reviewed_by ?? "", date: formatWhen(artwork.packaging_sample.reviewed_at) })
                        : ctrans("Changes asked by :name on :date", { name: artwork.packaging_sample.reviewed_by ?? "", date: formatWhen(artwork.packaging_sample.reviewed_at) }) }}<template v-if="artwork.packaging_sample.note">: <span class="text-gray-800">{{ artwork.packaging_sample.note }}</span></template>
                </p>

                <div v-if="artwork.packaging_sample.can_check" class="space-y-2 rounded-md border border-gray-200 bg-white p-3">
                    <Textarea v-model="packagingComment" rows="2" autoResize class="w-full text-sm" :invalid="!!packagingError" :placeholder="ctrans('Comment (required to ask for changes)')" />
                    <p v-if="packagingError" class="text-xs text-red-600">{{ packagingError }}</p>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <Button type="tertiary" size="xs" :label="ctrans('Changes needed')" :loading="checkingPackaging === 'changes_required'" @click="checkPackagingSample('changes_required')">
                            <template #icon><FontAwesomeIcon icon="fal fa-undo" class="text-orange-500" fixed-width aria-hidden="true" /></template>
                        </Button>
                        <Button type="tertiary" size="xs" :label="ctrans('Approve')" :loading="checkingPackaging === 'approved'" @click="checkPackagingSample('approved')">
                            <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </div>
                </div>
            </section>

            <section class="space-y-3 border-t border-gray-100 pt-4">
                <h4 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <FontAwesomeIcon icon="fal fa-comments" fixed-width aria-hidden="true" />
                    {{ ctrans("Design discussion") }}
                </h4>

                <p v-if="!artwork" class="text-xs text-gray-400">{{ ctrans("The discussion opens once this SKU has artwork.") }}</p>
                <p v-else-if="!thread && loadingThread" class="flex items-center gap-2 text-xs text-gray-400">
                    <FontAwesomeIcon icon="fal fa-spinner" spin fixed-width aria-hidden="true" /> {{ ctrans("Loading…") }}
                </p>
                <template v-else-if="thread">
                    <ol class="space-y-3">
                        <li v-for="item in comments" :key="item.id" class="space-y-2">
                            <div v-for="entry in [item, ...(item.replies ?? [])]" :key="entry.id" class="flex items-start gap-2" :class="entry.id !== item.id ? 'ml-8' : ''">
                                <TicketUserHoverCard
                                    class="mt-0.5 shrink-0"
                                    :name="entry.user"
                                    :avatar="entry.avatar"
                                    :username="entry.username"
                                    :messageUserId="entry.user_id"
                                    :canMention="false"
                                    size="sm"
                                    avatarOnly
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-1.5 text-xs">
                                        <span class="font-medium text-gray-800">{{ entry.user }}</span>
                                        <span v-if="entry.role" class="text-gray-500">{{ entry.role }}</span>
                                        <span class="tabular-nums text-gray-400">{{ formatWhen(entry.created_at) }}</span>
                                        <span v-if="entry.packaging_sample" class="rounded-full px-1.5 py-0.5 text-[11px] font-medium" :class="entry.packaging_sample === 'approved' ? 'bg-green-50 text-green-700' : 'bg-orange-50 text-orange-700'">
                                            {{ entry.packaging_sample === "approved" ? ctrans("Packaging sample approved") : ctrans("Packaging sample: changes needed") }}
                                        </span>
                                        <span v-if="entry.review" class="rounded-full px-1.5 py-0.5 text-[11px] font-medium" :class="entry.review === 'approve' ? 'bg-green-50 text-green-700' : 'bg-orange-50 text-orange-700'">
                                            {{ entry.review === "approve" ? ctrans("Approved V:version", { version: String(entry.version ?? "") }) : ctrans("Changes required on V:version", { version: String(entry.version ?? "") }) }}
                                        </span>
                                    </div>
                                    <TicketBody :text="entry.body" :images="entry.images" :attachments="entry.attachments" class="text-sm" />
                                </div>
                            </div>
                            <div class="ml-8">
                                <button v-if="replyingTo !== item.id" type="button" class="text-xs text-gray-500 hover:text-[--app-accent]" @click="replyingTo = item.id">
                                    {{ ctrans("Reply") }}
                                </button>
                                <ProductDevelopmentCommentComposer
                                    v-else
                                    :route="thread.route"
                                    :mentionables="thread.mentionables"
                                    :parentId="item.id"
                                    :only="['design']"
                                    :placeholder="ctrans('Write a reply')"
                                    :rows="2"
                                    @posted="replyingTo = null"
                                />
                            </div>
                        </li>
                    </ol>
                    <p v-if="!comments.length" class="text-xs text-gray-400">{{ ctrans("No comments yet") }}</p>
                    <ProductDevelopmentCommentComposer
                        :route="thread.route"
                        :mentionables="thread.mentionables"
                        :extra="{ artwork_id: artwork.id }"
                        :only="['design']"
                        :rows="2"
                    />
                </template>
            </section>
        </div>
    </Drawer>
</template>
