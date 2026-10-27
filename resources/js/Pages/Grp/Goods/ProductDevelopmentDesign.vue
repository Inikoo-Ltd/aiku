<!--
  - Author Louis Perez
  - Created on 17-10-2026-14h-10m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import Textarea from "primevue/textarea"
import Checkbox from "primevue/checkbox"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import DesignArtworkViewer, { type ArtworkPin, type ArtworkStroke } from "@/Components/Goods/ProductDevelopment/Design/DesignArtworkViewer.vue"
import DesignComments, { type DesignComment } from "@/Components/Goods/ProductDevelopment/Design/DesignComments.vue"
import { productDevelopmentColor } from "@/Components/Goods/ProductDevelopment/productDevelopmentColors"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import type { Mentionable } from "@/Components/Goods/ProductDevelopment/ProductDevelopmentCommentComposer.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLightbulbOn, faLock, faPencilRuler, faUndo, faPlus, faPaperPlane, faCheck, faArrowLeft, faSpinner, faCloudUpload, faMapPin, faPencil, faFilePdf } from "@fal"
library.add(faLightbulbOn, faLock, faPencilRuler, faUndo, faPlus, faPaperPlane, faCheck, faArrowLeft, faSpinner, faCloudUpload, faMapPin, faPencil, faFilePdf)

interface Status {
    value: "draft" | "in_review" | "changes_requested" | "approved"
    label: string
    color: string
    icon: string
}

interface Check {
    key: string
    label: string
    ticked_by: string | null
    ticked_at: string | null
    can_tick: boolean
}

interface VersionUpload {
    kind: "pdf" | "image"
    name: string
    url: string
    preview_url: string
}

interface Version {
    id: number
    version: number
    status: Status
    notes: string | null
    upload: VersionUpload | null
    approved_by: string | null
    approved_at: string | null
    can_edit: boolean
    can_review: boolean
    can_comment: boolean
    checks: Check[]
    all_checked: boolean
    routes: Record<"update" | "submit" | "review" | "artwork", routeType>
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    round: { value: string, label: string }
    artworkLocked: boolean
    versions: { id: number, version: number, status: Status, submitted_by: string | null, submitted_at: string | null, created_at: string | null, comments: number, route: routeType }[]
    version: Version | null
    previous: { version: number, upload: VersionUpload | null } | null
    comments: { threads: DesignComment[], mentionables: Mentionable[], route: routeType } | null
    commentStatusRoute: string
    startVersion: { route: routeType, round: string, next: number } | null
    developmentRoute: routeType
}>()

const notes = ref(props.version?.notes ?? "")

watch(() => props.version?.id, () => {
    notes.value = props.version?.notes ?? ""
    lastSaved = payloadNow()
    pendingMarkup.value = null
})

const canEdit = computed(() => !!props.version?.can_edit)

const saveState = ref<"idle" | "saving" | "saved" | "error">("idle")
let saveTimer: ReturnType<typeof setTimeout> | undefined
const payloadNow = () => JSON.stringify({ notes: notes.value })
let lastSaved = payloadNow()

function scheduleSave() {
    if (!canEdit.value || !props.version) {
        return
    }
    const payload = payloadNow()
    if (payload === lastSaved) {
        return
    }
    clearTimeout(saveTimer)
    saveTimer = setTimeout(() => save(payload), 900)
}

async function save(payload: string) {
    if (!props.version) {
        return
    }
    saveState.value = "saving"
    try {
        await axios.patch(route(props.version.routes.update.name, props.version.routes.update.parameters), JSON.parse(payload))
        lastSaved = payload
        saveState.value = "saved"
    } catch (error: any) {
        saveState.value = "error"
        const message = Object.values(error?.response?.data?.errors ?? {}).flat()[0] as string | undefined
        notify({ title: ctrans("Design not saved"), text: message ?? error?.response?.data?.message ?? "", type: "error" })
    }
}

watch(notes, scheduleSave)

async function flushSave() {
    clearTimeout(saveTimer)
    const payload = payloadNow()
    if (canEdit.value && payload !== lastSaved) {
        await save(payload)
    }
}

const compare = ref(false)

type PendingMarkup = { type: "pin", x: number, y: number, page?: number } | { type: "stroke", page: number, points: [number, number][] }

const pendingMarkup = ref<PendingMarkup | null>(null)
const activeCommentId = ref<number | null>(null)
const tool = ref<"pin" | "draw">("pin")

const threadsOfVersion = computed(() => (props.comments?.threads ?? []).filter((thread) => thread.version === props.version?.version))
const markupCount = computed(() => threadsOfVersion.value.filter((thread) => thread.pin || thread.stroke).length)

const artworkPins = computed<ArtworkPin[]>(() => [
    ...threadsOfVersion.value.filter((thread) => thread.pin).map((thread) => ({ id: thread.id, number: thread.pin!.number, x: thread.pin!.x, y: thread.pin!.y, page: thread.pin!.page ?? 1, resolved: thread.status === "resolved", active: thread.id === activeCommentId.value })),
    ...(pendingMarkup.value?.type === "pin" ? [{ id: -1, number: markupCount.value + 1, x: pendingMarkup.value.x, y: pendingMarkup.value.y, page: pendingMarkup.value.page ?? 1, resolved: false, active: true }] : []),
])

const artworkStrokes = computed<ArtworkStroke[]>(() => [
    ...threadsOfVersion.value.filter((thread) => thread.stroke).map((thread) => ({ id: thread.id, number: thread.stroke!.number, page: thread.stroke!.page, points: thread.stroke!.points, resolved: thread.status === "resolved", active: thread.id === activeCommentId.value })),
    ...(pendingMarkup.value?.type === "stroke" ? [{ id: -2, number: markupCount.value + 1, page: pendingMarkup.value.page, points: pendingMarkup.value.points, resolved: false, active: true }] : []),
])

function onPin(point: { x: number, y: number, page?: number }) {
    pendingMarkup.value = { type: "pin", ...point }
    activeCommentId.value = null
}

function onStroke(stroke: { page: number, points: [number, number][] }) {
    pendingMarkup.value = { type: "stroke", ...stroke }
    activeCommentId.value = null
}

const toolOptions = computed(() => [
    { value: "pin" as const, label: ctrans("Pin"), icon: "fal fa-map-pin" },
    { value: "draw" as const, label: ctrans("Draw"), icon: "fal fa-pencil" },
])

const canSubmit = computed(() => !!props.version?.upload)

const busy = ref<string | null>(null)

function send(routeName: routeType, payload: Record<string, string>, key: string, onSuccess?: () => void) {
    router.patch(route(routeName.name, routeName.parameters), payload, {
        preserveScroll: true,
        onStart: () => busy.value = key,
        onFinish: () => busy.value = null,
        onSuccess: () => onSuccess?.(),
        onError: (errors) => notify({ title: ctrans("Not saved"), text: Object.values(errors).join(" "), type: "error" }),
    })
}

async function submitForReview() {
    if (!props.version) {
        return
    }
    await flushSave()
    send(props.version.routes.submit, {}, "submit")
}

const CHECK_DEBOUNCE_MS = 500

/** A tick shows at once; it is saved once that box has been left alone for half a second */
const pendingChecks = ref<Record<string, boolean>>({})
const checkTimers = new Map<string, ReturnType<typeof setTimeout>>()

const isTicked = (check: Check) => pendingChecks.value[check.key] ?? !!check.ticked_by

function toggleCheck(check: Check, ticked: boolean) {
    pendingChecks.value[check.key] = ticked
    scheduleCheckSave(check, CHECK_DEBOUNCE_MS)
}

function scheduleCheckSave(check: Check, delay: number) {
    clearTimeout(checkTimers.get(check.key))
    checkTimers.set(check.key, setTimeout(() => {
        if (busy.value !== null) {
            scheduleCheckSave(check, 150)
            return
        }
        checkTimers.delete(check.key)
        const saved = !!props.version?.checks.find((candidate) => candidate.key === check.key)?.ticked_by
        const wanted = pendingChecks.value[check.key]
        if (!props.version || wanted === undefined || wanted === saved) {
            delete pendingChecks.value[check.key]
            return
        }
        send(props.version.routes.review, { operation: wanted ? "check" : "uncheck", check: check.key }, `check-${check.key}`)
    }, delay))
}

watch(() => props.version?.checks, (checks) => {
    for (const check of checks ?? []) {
        if (!checkTimers.has(check.key) && pendingChecks.value[check.key] === !!check.ticked_by) {
            delete pendingChecks.value[check.key]
        }
    }
}, { deep: true })

onBeforeUnmount(() => checkTimers.forEach((timer) => clearTimeout(timer)))

function review(operation: "approve" | "request_changes") {
    if (props.version) {
        send(props.version.routes.review, { operation }, operation)
    }
}

function beginVersion() {
    if (!props.startVersion) {
        return
    }
    router.post(route(props.startVersion.route.name, props.startVersion.route.parameters), { round: props.startVersion.round }, {
        onStart: () => busy.value = "start",
        onFinish: () => busy.value = null,
        onError: (errors) => notify({ title: ctrans("Version not started"), text: Object.values(errors).join(" "), type: "error" }),
    })
}

const artworkInput = ref<HTMLInputElement | null>(null)
const uploadProgress = ref<number | null>(null)

function onArtworkPicked(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ""
    if (!file || !props.version) {
        return
    }
    router.post(route(props.version.routes.artwork.name, props.version.routes.artwork.parameters), { file }, {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => uploadProgress.value = 0,
        onProgress: (event) => uploadProgress.value = Math.round(event?.percentage ?? 0),
        onFinish: () => uploadProgress.value = null,
        onError: (errors) => notify({ title: ctrans("File not uploaded"), text: Object.values(errors).join(" "), type: "error" }),
    })
}

const round = computed(() => props.round)

const formatWhen = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM" }) : ""
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <Link :href="route(developmentRoute.name, developmentRoute.parameters)">
                <Button type="negative" icon="fal fa-arrow-left" :label="ctrans('Back to development')" />
            </Link>
        </template>
    </PageHeading>

    <div class="space-y-4 px-4 pb-6 pt-4 md:px-6">
        <div v-if="artworkLocked" class="rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800">
            <FontAwesomeIcon icon="fal fa-lock" fixed-width aria-hidden="true" /> {{ ctrans("The artwork is signed off and locked: no new versions can be started") }}
        </div>

        <div class="grid gap-4 lg:grid-cols-[13rem_minmax(0,1fr)] xl:grid-cols-[13rem_minmax(0,1fr)_22rem]">
            <aside class="space-y-2">
                <h2 class="text-sm font-medium text-gray-700">{{ ctrans("Versions") }}</h2>
                <Link
                    v-for="item in versions"
                    :key="item.id"
                    :href="route(item.route.name, item.route.parameters)"
                    class="block rounded-lg border bg-white px-3 py-2.5 text-sm transition-colors"
                    :class="item.id === version?.id ? 'border-[--app-accent] ring-1 ring-[--app-accent]' : 'border-gray-200 hover:border-gray-300'"
                    preserve-scroll
                >
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-gray-900">v{{ item.version }}</span>
                        <span class="rounded px-1.5 py-0.5 text-xs" :class="productDevelopmentColor(item.status.color).count">{{ item.status.label }}</span>
                    </div>
                    <div class="mt-0.5 text-xs text-gray-500">
                        {{ [item.submitted_by, formatWhen(item.submitted_at ?? item.created_at)].filter(Boolean).join(" · ") }}
                        <template v-if="item.comments"> · {{ ctrans(":count comments", { count: String(item.comments) }) }}</template>
                    </div>
                </Link>
                <p v-if="!versions.length" class="text-sm text-gray-400">{{ ctrans("No version yet") }}</p>
                <Button v-if="startVersion && versions.length" type="tertiary" full icon="fal fa-plus" :label="ctrans('Start v:version', { version: String(startVersion.next) })" :loading="busy === 'start'" @click="beginVersion" />
            </aside>

            <section class="min-w-0 space-y-3 rounded-lg border border-gray-200 bg-white p-4">
                <template v-if="version">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="flex min-w-0 items-center gap-2 text-sm text-gray-700">
                            <FontAwesomeIcon :icon="version.upload?.kind === 'pdf' ? 'fal fa-file-pdf' : 'fal fa-image'" class="text-gray-400" fixed-width aria-hidden="true" />
                            <a v-if="version.upload" :href="version.upload.url" class="primaryLink truncate">{{ version.upload.name }}</a>
                            <span v-else class="text-gray-400">{{ ctrans("No file yet") }}</span>
                        </span>
                        <Button v-if="canEdit" type="tertiary" size="xs" icon="fal fa-cloud-upload" :label="version.upload ? ctrans('Replace file') : ctrans('Upload PDF or picture')" :loading="uploadProgress !== null" @click="artworkInput?.click()" />
                        <SegmentedToggle v-if="!canEdit && version.can_comment && version.upload" v-model="tool" :options="toolOptions" :ariaLabel="ctrans('Markup tool')" class="ml-auto" />
                        <label v-if="previous?.upload" class="flex items-center gap-2 text-sm text-gray-700" :class="!canEdit && version.can_comment ? '' : 'ml-auto'">
                            <Checkbox v-model="compare" binary />
                            {{ ctrans("Compare with v:version", { version: String(previous.version) }) }}
                        </label>
                        <input ref="artworkInput" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" class="hidden" @change="onArtworkPicked" />
                    </div>
                    <div v-if="uploadProgress !== null" class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full bg-[--app-accent] transition-all" :style="{ width: uploadProgress + '%' }" />
                    </div>
                    <p v-if="!canEdit && version.can_comment && version.upload" class="text-xs text-gray-500">
                        {{ tool === "draw" ? ctrans("Draw on the artwork, then write your comment") : ctrans("Click the artwork to pin a comment") }}
                    </p>

                    <div v-if="version.upload" class="grid min-w-0 gap-4" :class="compare && previous?.upload ? 'md:grid-cols-2' : ''">
                        <div v-if="compare && previous?.upload" class="min-w-0 space-y-1">
                            <span class="text-xs font-medium text-gray-500">v{{ previous.version }}</span>
                            <DesignArtworkViewer :upload="previous.upload" />
                        </div>
                        <div class="min-w-0 space-y-1">
                            <span v-if="compare && previous?.upload" class="text-xs font-medium text-gray-500">v{{ version.version }}</span>
                            <DesignArtworkViewer
                                :upload="version.upload"
                                :pins="artworkPins"
                                :strokes="artworkStrokes"
                                :nextNumber="markupCount + 1"
                                :tool="!canEdit && version.can_comment ? tool : null"
                                @pin="onPin"
                                @stroke="onStroke"
                                @focus="(id) => activeCommentId = id"
                            />
                        </div>
                    </div>
                    <button
                        v-else-if="canEdit"
                        type="button"
                        class="flex w-full flex-col items-center justify-center gap-2 rounded-md border-2 border-dashed border-gray-200 px-4 py-14 text-sm text-gray-500 hover:border-[--app-accent] hover:text-[--app-accent]"
                        @click="artworkInput?.click()"
                    >
                        <FontAwesomeIcon icon="fal fa-cloud-upload" class="text-3xl" fixed-width aria-hidden="true" />
                        {{ ctrans("Upload the :round as a PDF or picture", { round: round.label.toLowerCase() }) }}
                        <span class="text-xs text-gray-400">PDF, JPG, PNG, WEBP · {{ ctrans("up to 50 MB") }}</span>
                    </button>
                    <p v-else class="py-10 text-center text-sm text-gray-400">{{ ctrans("The designer has not uploaded the file yet") }}</p>

                    <div v-if="canEdit" class="space-y-1">
                        <label class="text-xs text-gray-500">{{ ctrans("Note for reviewers") }}</label>
                        <Textarea v-model="notes" rows="2" autoResize class="w-full text-sm" :placeholder="ctrans('What changed in this version?')" />
                    </div>
                    <p v-else-if="version.notes" class="whitespace-pre-line rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ version.notes }}</p>

                    <div v-if="canEdit" class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-100 pt-3">
                        <span class="text-xs text-gray-500">
                            <template v-if="saveState === 'saving'"><FontAwesomeIcon icon="fal fa-spinner" spin fixed-width aria-hidden="true" /> {{ ctrans("Saving…") }}</template>
                            <template v-else-if="saveState === 'saved'"><FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" /> {{ ctrans("Saved") }}</template>
                            <template v-else-if="saveState === 'error'"><span class="text-red-600">{{ ctrans("Not saved") }}</span></template>
                        </span>
                        <Button
                            type="primary"
                            icon="fal fa-paper-plane"
                            :label="ctrans('Send v:version for review', { version: String(version.version) })"
                            :disabled="!canSubmit"
                            :loading="busy === 'submit'"
                            class="disabled:cursor-not-allowed disabled:opacity-50"
                            @click="submitForReview"
                        />
                    </div>
                </template>

                <div v-else class="flex flex-col items-center justify-center gap-3 py-16 text-center text-sm text-gray-500">
                    <FontAwesomeIcon icon="fal fa-pencil-ruler" class="text-3xl text-gray-300" fixed-width aria-hidden="true" />
                    <p>{{ ctrans("No :round design yet", { round: round.label.toLowerCase() }) }}</p>
                    <Button v-if="startVersion" type="primary" icon="fal fa-plus" :label="ctrans('Start v1')" :loading="busy === 'start'" @click="beginVersion" />
                </div>
            </section>

            <aside class="min-w-0 space-y-4 lg:col-span-2 lg:grid lg:grid-cols-2 lg:items-start lg:gap-4 lg:space-y-0 xl:col-span-1 xl:block xl:space-y-4">
                <section v-if="version && comments" class="rounded-lg border border-gray-200 bg-white">
                    <h2 class="border-b border-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-800">{{ ctrans("Comments on v:version", { version: String(version.version) }) }}</h2>
                    <DesignComments
                        :data="comments"
                        :version="version.version"
                        :designRoundId="version.id"
                        :statusRoute="commentStatusRoute"
                        :pendingMarkup="pendingMarkup"
                        :pendingNumber="markupCount + 1"
                        :activeId="activeCommentId"
                        @cancelPin="pendingMarkup = null"
                        @posted="pendingMarkup = null"
                        @focus="(id) => activeCommentId = id"
                    />
                </section>

                <section v-if="version && version.status.value === 'in_review'" class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-gray-800">{{ ctrans("Before approving") }}</h2>
                    <ul class="space-y-2">
                        <li v-for="check in version.checks" :key="check.key" class="flex items-start gap-2 text-sm">
                            <Checkbox :modelValue="isTicked(check)" binary :disabled="!check.can_tick" :inputId="`check-${check.key}`" class="mt-0.5" @update:modelValue="(value) => toggleCheck(check, !!value)" />
                            <label :for="`check-${check.key}`" class="min-w-0">
                                <span class="text-gray-800">{{ check.label }}</span>
                                <span v-if="check.ticked_by" class="block text-xs text-gray-500">{{ check.ticked_by }} · {{ formatWhen(check.ticked_at) }}</span>
                            </label>
                        </li>
                    </ul>
                    <div v-if="version.can_review" class="space-y-2 pt-1">
                        <div class="grid grid-cols-2 gap-2 [&_button]:whitespace-nowrap">
                            <Button type="negative" full icon="fal fa-undo" :label="ctrans('Request changes')" :loading="busy === 'request_changes'" @click="review('request_changes')" />
                            <Button type="primary" full icon="fal fa-check" :label="ctrans('Approve')" :disabled="!version.all_checked" :loading="busy === 'approve'" class="disabled:cursor-not-allowed disabled:opacity-50" @click="review('approve')" />
                        </div>
                        <p class="text-xs text-gray-500">{{ ctrans("Approving locks v:version and opens the artwork for the designer.", { version: String(version.version) }) }}</p>
                    </div>
                </section>

                <section v-if="version && version.status.value === 'approved'" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    <FontAwesomeIcon icon="fal fa-lock" fixed-width aria-hidden="true" />
                    {{ ctrans("Approved by :user on :date, locked", { user: version.approved_by ?? "", date: formatWhen(version.approved_at) }) }}
                </section>
            </aside>
        </div>

    </div>
</template>
