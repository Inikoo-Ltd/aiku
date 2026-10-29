<!--
  - Author Louis Perez
  - Created on 29-10-2026-11h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import DesignLabelContent from "@/Components/Goods/ProductDevelopment/Design/DesignLabelContent.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import DesignArtworkViewer, { type ArtworkPin, type ArtworkStroke } from "@/Components/Goods/ProductDevelopment/Design/DesignArtworkViewer.vue"
import DesignComments, { type DesignComment } from "@/Components/Goods/ProductDevelopment/Design/DesignComments.vue"
import { type Mentionable } from "@/Components/Goods/ProductDevelopment/ProductDevelopmentCommentComposer.vue"
import { type Artwork } from "@/Components/Goods/ProductDevelopment/Design/DesignArtworkPanel.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faMapPin, faPencil, faDownload, faSpinner, faComments, faTag, faUndo, faCheck, faExclamationCircle } from "@fal"
library.add(faMapPin, faPencil, faDownload, faSpinner, faComments, faTag, faUndo, faCheck, faExclamationCircle)

const props = defineProps<{
    title: string
    artwork: Artwork | null
    pdfUrl: string
    downloadUrl: string
    labelContentRoute?: routeType | null
    initialTab?: "comments" | "label"
}>()

const visible = defineModel<boolean>("visible", { required: true })

type PendingMarkup = { type: "pin", x: number, y: number, page?: number } | { type: "stroke", page: number, points: [number, number][] }

const thread = ref<{ threads: DesignComment[], mentionables: Mentionable[], route: routeType } | null>(null)
const loading = ref(false)
const pendingMarkup = ref<PendingMarkup | null>(null)
const activeCommentId = ref<number | null>(null)
const tool = ref<"pin" | "draw">("pin")

const tab = ref<"comments" | "label">("comments")
const tabOptions = computed(() => [
    { value: "comments" as const, label: ctrans("Comments"), icon: "fal fa-comments" },
    { value: "label" as const, label: ctrans("Label information"), icon: "fal fa-tag" },
])

type LabelContent = InstanceType<typeof DesignLabelContent>["$props"]["data"]
const labelContent = ref<LabelContent | null>(null)

async function loadLabelContent() {
    if (labelContent.value || !props.labelContentRoute) {
        return
    }
    try {
        const { data } = await axios.get(route(props.labelContentRoute.name, props.labelContentRoute.parameters))
        labelContent.value = data
    } catch {
        labelContent.value = null
    }
}

watch(tab, (value) => value === "label" && loadLabelContent())

/* Review: approve the version, or send it back with what to fix (the pins say where) */

const reviewComment = ref("")
const reviewing = ref<string | null>(null)
const reviewError = ref<string | null>(null)

function review(operation: "approve" | "request_changes") {
    const artwork = props.artwork
    if (!artwork) {
        return
    }
    router.patch(route(artwork.review_route.name, artwork.review_route.parameters), { operation, comment: reviewComment.value || null }, {
        preserveScroll: true,
        only: ["design"],
        onStart: () => { reviewing.value = operation; reviewError.value = null },
        onFinish: () => reviewing.value = null,
        onError: (errors) => reviewError.value = Object.values(errors)[0] ?? null,
        onSuccess: () => { reviewComment.value = ""; visible.value = false },
    })
}

const toolOptions = computed(() => [
    { value: "pin" as const, label: ctrans("Pin"), icon: "fal fa-map-pin" },
    { value: "draw" as const, label: ctrans("Draw"), icon: "fal fa-pencil" },
])

const threadsOfVersion = computed(() => (thread.value?.threads ?? []).filter((item) => item.version === props.artwork?.version))
const markupCount = computed(() => threadsOfVersion.value.filter((item) => item.pin || item.stroke).length)

const pins = computed<ArtworkPin[]>(() => [
    ...threadsOfVersion.value.filter((item) => item.pin).map((item) => ({ id: item.id, number: item.pin!.number, x: item.pin!.x, y: item.pin!.y, page: item.pin!.page ?? 1, resolved: item.status === "resolved", active: item.id === activeCommentId.value })),
    ...(pendingMarkup.value?.type === "pin" ? [{ id: -1, number: markupCount.value + 1, x: pendingMarkup.value.x, y: pendingMarkup.value.y, page: pendingMarkup.value.page ?? 1, resolved: false, active: true }] : []),
])

const strokes = computed<ArtworkStroke[]>(() => [
    ...threadsOfVersion.value.filter((item) => item.stroke).map((item) => ({ id: item.id, number: item.stroke!.number, page: item.stroke!.page, points: item.stroke!.points, resolved: item.status === "resolved", active: item.id === activeCommentId.value })),
    ...(pendingMarkup.value?.type === "stroke" ? [{ id: -2, number: markupCount.value + 1, page: pendingMarkup.value.page, points: pendingMarkup.value.points, resolved: false, active: true }] : []),
])

async function loadThread() {
    const target = props.artwork
    if (!target) {
        thread.value = null
        return
    }
    loading.value = true
    try {
        const { data } = await axios.get(route(target.comments_route.name, target.comments_route.parameters))
        if (props.artwork?.id === target.id) {
            thread.value = data
        }
    } finally {
        loading.value = false
    }
}

function onPosted() {
    pendingMarkup.value = null
    loadThread()
}

watch(() => [visible.value, props.artwork?.id, props.artwork?.version], ([isVisible]) => {
    if (isVisible) {
        pendingMarkup.value = null
        activeCommentId.value = null
        reviewComment.value = ""
        reviewError.value = null
        labelContent.value = null
        tab.value = props.initialTab ?? "comments"
        if (tab.value === "label") {
            loadLabelContent()
        }
        loadThread()
    }
}, { immediate: true })
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="title" :style="{ width: '78rem' }" :breakpoints="{ '1280px': '96vw' }" :contentClass="'!pb-4'">
        <div v-if="artwork" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="min-w-0 space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-sm font-medium text-gray-700">V{{ artwork.version }}</span>
                    <span class="text-xs text-gray-500">
                        {{ tool === "draw" ? ctrans("Draw on the artwork, then write your comment") : ctrans("Click the artwork to pin a comment") }}
                    </span>
                    <SegmentedToggle v-model="tool" :options="toolOptions" :ariaLabel="ctrans('Markup tool')" class="ml-auto" />
                    <a :href="downloadUrl" class="flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800" v-tooltip="ctrans('Download')" :aria-label="ctrans('Download')">
                        <FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
                    </a>
                </div>
                <p v-if="artwork.update_note" class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700"><span class="font-medium">{{ ctrans("What changed:") }}</span> {{ artwork.update_note }}</p>
                <DesignArtworkViewer
                    :upload="{ kind: 'pdf', preview_url: pdfUrl, name: artwork.file_name ?? undefined }"
                    :pins="pins"
                    :strokes="strokes"
                    :nextNumber="markupCount + 1"
                    :tool="tool"
                    @pin="(point) => { pendingMarkup = { type: 'pin', ...point }; activeCommentId = null }"
                    @stroke="(stroke) => { pendingMarkup = { type: 'stroke', ...stroke }; activeCommentId = null }"
                    @focus="(id) => activeCommentId = id"
                />
            </div>

            <aside class="min-w-0 space-y-3 lg:max-h-[75vh] lg:overflow-y-auto">
                <div v-if="artwork.can_review" class="space-y-2 rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <h3 class="text-sm font-semibold text-gray-800">{{ ctrans("Review V:version", { version: String(artwork.version) }) }}</h3>
                    <p v-if="artwork.approval_blocked" class="flex gap-2 text-xs text-amber-800">
                        <FontAwesomeIcon icon="fal fa-exclamation-circle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
                        {{ ctrans(":reason. You can still ask for changes.", { reason: artwork.approval_blocked }) }}
                    </p>
                    <Textarea v-model="reviewComment" rows="2" autoResize class="w-full text-sm" :invalid="!!reviewError" :placeholder="ctrans('Comment (required to ask for changes)')" />
                    <p v-if="reviewError" class="text-xs text-red-600">{{ reviewError }}</p>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <Button type="tertiary" size="xs" :label="ctrans('Changes required')" :loading="reviewing === 'request_changes'" @click="review('request_changes')">
                            <template #icon><FontAwesomeIcon icon="fal fa-undo" class="text-orange-500" fixed-width aria-hidden="true" /></template>
                        </Button>
                        <Button type="tertiary" size="xs" :label="ctrans('Approve')" :loading="reviewing === 'approve'" @click="review('approve')">
                            <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </div>
                </div>

                <SegmentedToggle v-if="labelContentRoute" v-model="tab" :options="tabOptions" :ariaLabel="ctrans('Show')" class="w-full" />

                <div v-if="tab === 'label'" class="rounded-lg border border-gray-200 bg-white p-3">
                    <DesignLabelContent v-if="labelContent" :data="labelContent" />
                    <p v-else class="flex items-center gap-2 text-sm text-gray-400"><FontAwesomeIcon icon="fal fa-spinner" spin fixed-width aria-hidden="true" /> {{ ctrans("Loading the label and pack information…") }}</p>
                </div>

                <div v-else class="rounded-lg border border-gray-200 bg-white">
                <h3 class="border-b border-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-800">{{ ctrans("Comments on V:version", { version: String(artwork.version) }) }}</h3>
                <p v-if="!thread && loading" class="flex items-center gap-2 px-4 py-3 text-xs text-gray-400">
                    <FontAwesomeIcon icon="fal fa-spinner" spin fixed-width aria-hidden="true" /> {{ ctrans("Loading…") }}
                </p>
                <DesignComments
                    v-else-if="thread"
                    :data="thread"
                    :version="artwork.version"
                    :target="{ artwork_id: artwork.id }"
                    statusRoute="grp.models.product_development.comment.status"
                    :only="['design']"
                    :pendingMarkup="pendingMarkup"
                    :pendingNumber="markupCount + 1"
                    :activeId="activeCommentId"
                    @cancelPin="pendingMarkup = null"
                    @posted="onPosted"
                    @changed="loadThread"
                    @focus="(id) => activeCommentId = id"
                />
                </div>
            </aside>
        </div>
    </Dialog>
</template>
