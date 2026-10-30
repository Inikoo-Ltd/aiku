<!--
  - Author Louis Perez
  - Created on 29-10-2026-12h-10m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import DesignArtworkViewer from "@/Components/Goods/ProductDevelopment/Design/DesignArtworkViewer.vue"
import ProductDevelopmentPhotoStrip, { type ProductDevelopmentPhoto } from "@/Components/Goods/ProductDevelopment/ProductDevelopmentPhotoStrip.vue"
import { type Artwork } from "@/Components/Goods/ProductDevelopment/Design/DesignArtworkPanel.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBoxOpen, faCheck, faUndo, faCameraAlt, faSearchPlus, faChevronLeft, faChevronRight, faFilePdf, faClock, faCheckCircle, faDownload } from "@fal"
library.add(faBoxOpen, faCheck, faUndo, faCameraAlt, faSearchPlus, faChevronLeft, faChevronRight, faFilePdf, faClock, faCheckCircle, faDownload)

const props = defineProps<{
    title: string
    artwork: Artwork | null
    pdfUrl: string
    downloadUrl?: string
}>()

const visible = defineModel<boolean>("visible", { required: true })

const sample = computed(() => props.artwork?.packaging_sample ?? null)
const photos = computed<ProductDevelopmentPhoto[]>(() => sample.value?.photos ?? [])
const selectedId = ref<number | null>(null)
const selected = computed(() => photos.value.find((photo) => photo.id === selectedId.value) ?? photos.value[photos.value.length - 1] ?? null)
const selectedIndex = computed(() => selected.value ? photos.value.findIndex((photo) => photo.id === selected.value!.id) : -1)

function step(by: number) {
    const count = photos.value.length
    if (count) {
        selectedId.value = photos.value[(selectedIndex.value + by + count) % count].id
    }
}

const earlier = computed(() => (props.artwork?.versions ?? [])
    .filter((version) => version.sample && version.version !== props.artwork?.version)
    .map((version) => ({ version: version.version, ...version.sample! })))

const comment = ref("")
const checking = ref<string | null>(null)
const error = ref<string | null>(null)

const formatWhen = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM yyyy, HH:mm" }) : ""

function check(status: "approved" | "changes_required") {
    if (!sample.value) {
        return
    }
    router.patch(route(sample.value.routes.review.name, sample.value.routes.review.parameters), { status, comment: comment.value || null }, {
        preserveScroll: true,
        only: ["design"],
        onStart: () => { checking.value = status; error.value = null },
        onFinish: () => checking.value = null,
        onError: (errors) => error.value = Object.values(errors)[0] ?? null,
        onSuccess: () => { comment.value = ""; visible.value = false },
    })
}

watch(() => [visible.value, props.artwork?.id], ([isVisible]) => {
    if (isVisible) {
        selectedId.value = null
        comment.value = ""
        error.value = null
    }
}, { immediate: true })

watch(() => photos.value.length, (count, previous) => {
    if (count > (previous ?? 0)) {
        selectedId.value = photos.value[count - 1]?.id ?? null
    }
})
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :style="{ width: '96rem' }"
        :breakpoints="{ '1536px': '96vw', '640px': '100vw' }"
        :pt="{ root: { class: 'max-sm:!h-full max-sm:!max-h-full max-sm:!rounded-none' }, content: { class: 'max-sm:!px-3' } }"
    >
        <template #header>
            <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[--app-accent-soft] text-[--app-accent]">
                    <FontAwesomeIcon icon="fal fa-box-open" fixed-width aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <h2 class="truncate text-base font-semibold text-gray-900">{{ title }}</h2>
                    <p class="text-xs text-gray-500">{{ ctrans("Compare the sample with the approved artwork, then give your verdict") }}</p>
                </div>
                <span
                    v-if="sample"
                    class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-medium"
                    :class="sample.status === 'approved' ? 'border-green-200 bg-green-50 text-green-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                >
                    <FontAwesomeIcon :icon="sample.status === 'approved' ? 'fal fa-check-circle' : 'fal fa-clock'" fixed-width aria-hidden="true" />
                    {{ sample.status === "approved" ? ctrans("Sample approved") : ctrans("Waiting for the verdict") }}
                </span>
            </div>
        </template>

        <div v-if="artwork && sample" class="grid gap-4 lg:h-[74vh] lg:grid-cols-[minmax(0,5fr)_minmax(0,5fr)_minmax(18rem,2fr)]">
            <section class="flex min-w-0 flex-col overflow-hidden rounded-lg border border-gray-200 bg-white">
                <header class="flex h-11 shrink-0 items-center gap-2 border-b border-gray-100 px-3">
                    <FontAwesomeIcon icon="fal fa-file-pdf" class="text-red-500" fixed-width aria-hidden="true" />
                    <span class="text-sm font-medium text-gray-800">{{ ctrans("Approved artwork") }}</span>
                    <span class="rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-xs font-medium tabular-nums text-green-700">V{{ artwork.version }}</span>
                    <a v-if="downloadUrl" :href="downloadUrl" class="ml-auto flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800" v-tooltip="ctrans('Download')" :aria-label="ctrans('Download')">
                        <FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
                    </a>
                </header>
                <div class="h-[40vh] overflow-auto bg-gray-50 p-3 lg:h-auto lg:min-h-0 lg:flex-1">
                    <DesignArtworkViewer :upload="{ kind: 'pdf', preview_url: pdfUrl, name: artwork.file_name ?? undefined }" />
                </div>
                <footer class="flex h-[5.5rem] shrink-0 flex-col justify-center gap-0.5 border-t border-gray-100 px-3 text-xs">
                    <span class="truncate font-medium text-gray-700">{{ artwork.file_name }}</span>
                    <span v-if="artwork.reviewed_at" class="text-gray-500">{{ ctrans("Approved by :name on :date", { name: artwork.reviewed_by ?? "", date: formatWhen(artwork.reviewed_at) }) }}</span>
                </footer>
            </section>

            <section class="flex min-w-0 flex-col overflow-hidden rounded-lg border border-gray-200 bg-white">
                <header class="flex h-11 shrink-0 items-center gap-2 border-b border-gray-100 px-3">
                    <FontAwesomeIcon icon="fal fa-camera-alt" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                    <span class="text-sm font-medium text-gray-800">{{ ctrans("Sample") }}</span>
                    <span v-if="photos.length" class="text-xs tabular-nums text-gray-500">{{ ctrans(":current of :total", { current: String(selectedIndex + 1), total: String(photos.length) }) }}</span>
                    <a v-if="selected?.url" :href="selected.url" target="_blank" class="ml-auto flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-gray-800" v-tooltip="ctrans('Full size')" :aria-label="ctrans('Full size')">
                        <FontAwesomeIcon icon="fal fa-search-plus" fixed-width aria-hidden="true" />
                    </a>
                </header>
                <div class="relative flex h-[40vh] items-center justify-center bg-gray-50 p-3 lg:h-auto lg:min-h-0 lg:flex-1">
                    <img v-if="selected?.preview_url" :src="selected.preview_url" :alt="selected.name ?? ''" class="max-h-full max-w-full rounded object-contain shadow-sm" />
                    <div v-else class="flex flex-col items-center gap-2 px-4 text-center text-sm text-gray-400">
                        <FontAwesomeIcon icon="fal fa-camera-alt" class="text-3xl" fixed-width aria-hidden="true" />
                        {{ sample.can_check ? ctrans("Add photos of the sample below") : ctrans("No photos of the sample yet") }}
                    </div>
                    <template v-if="photos.length > 1">
                        <button type="button" class="absolute left-2 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow hover:text-[--app-accent]" :aria-label="ctrans('Previous photo')" @click="step(-1)">
                            <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width aria-hidden="true" />
                        </button>
                        <button type="button" class="absolute right-2 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow hover:text-[--app-accent]" :aria-label="ctrans('Next photo')" @click="step(1)">
                            <FontAwesomeIcon icon="fal fa-chevron-right" fixed-width aria-hidden="true" />
                        </button>
                    </template>
                </div>
                <footer class="flex h-[5.5rem] shrink-0 items-center overflow-x-auto border-t border-gray-100 px-3">
                    <ProductDevelopmentPhotoStrip
                        class="[&>div:first-child]:flex-nowrap"
                        :photos="photos"
                        :uploadRoute="sample.can_check ? sample.routes.photos : null"
                        :only="['design']"
                        :selectedId="selected?.id ?? null"
                        @select="(photo) => selectedId = photo.id"
                    />
                </footer>
            </section>

            <aside class="flex min-w-0 flex-col overflow-hidden rounded-lg border border-gray-200 bg-white lg:col-span-2 xl:col-span-1">
                <header class="flex h-11 shrink-0 items-center border-b border-gray-100 px-3 text-sm font-medium text-gray-800">{{ ctrans("Verdict") }}</header>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
                    <p v-if="sample.status === 'approved'" class="rounded-md bg-green-50 px-3 py-2 text-xs text-green-800">
                        {{ ctrans("Approved by :name on :date", { name: sample.reviewed_by ?? "", date: formatWhen(sample.reviewed_at) }) }}<template v-if="sample.note">: {{ sample.note }}</template>
                    </p>
                    <div v-if="earlier.length" class="space-y-2">
                        <h4 class="text-xs font-medium text-gray-500">{{ ctrans("Earlier samples") }}</h4>
                        <ol class="space-y-2">
                            <li v-for="item in earlier" :key="item.version" class="flex gap-2.5 rounded-md border border-gray-100 p-2.5">
                                <span
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold tabular-nums"
                                    :class="item.status === 'approved' ? 'bg-green-50 text-green-700' : 'bg-orange-50 text-orange-700'"
                                >V{{ item.version }}</span>
                                <div class="min-w-0 flex-1 space-y-1.5">
                                    <p class="text-xs font-medium" :class="item.status === 'approved' ? 'text-green-700' : 'text-orange-700'">
                                        {{ item.status === "approved" ? ctrans("Approved") : ctrans("Sent back") }}
                                        <span class="block font-normal text-gray-500">{{ item.reviewed_by }} · {{ formatWhen(item.reviewed_at) }}</span>
                                    </p>
                                    <p v-if="item.note" class="line-clamp-4 break-words text-xs text-gray-800" :title="item.note">{{ item.note }}</p>
                                    <ProductDevelopmentPhotoStrip v-if="item.photos.length" :photos="item.photos" />
                                </div>
                            </li>
                        </ol>
                    </div>
                    <p v-else-if="sample.status !== 'approved'" class="text-xs leading-relaxed text-gray-500">
                        {{ ctrans("Approving lets its purchase orders go ahead. Changes needed sends the artwork back for V:next.", { next: String(artwork.version + 1) }) }}
                    </p>
                </div>

                <div v-if="sample.can_check" class="shrink-0 space-y-2 border-t border-gray-100 bg-gray-50 p-3">
                    <Textarea v-model="comment" rows="3" class="max-h-32 w-full resize-none !overflow-y-auto break-words text-sm" :invalid="!!error" :placeholder="ctrans('Comment (required to ask for changes)')" />
                    <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                        <Button key="approve" type="green" :label="ctrans('Approve sample')" :loading="checking === 'approved'" full @click="check('approved')">
                            <template #icon><FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" /></template>
                        </Button>
                        <Button key="changes" type="white-w-outline" :label="ctrans('Changes needed')" :loading="checking === 'changes_required'" full @click="check('changes_required')">
                            <template #icon><FontAwesomeIcon icon="fal fa-undo" class="text-orange-500" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </div>
                </div>
            </aside>
        </div>
    </Dialog>
</template>
