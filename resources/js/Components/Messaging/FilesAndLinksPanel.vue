<!--
  - Author Louis Perez
  - Created on 26-11-2026-14h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script lang="ts">
import type { SlackMessageFile } from "@/Components/Messaging/Slack/SlackMessageFiles.vue"
import type { Image as ImageSources } from "@/types/Image"

export type SharedKind = "all" | "files" | "media" | "links"

export interface SharedItem {
    key: string
    kind: "file" | "media" | "link"
    message_id: number
    ts?: string
    thread_ts?: string | null
    is_broadcast?: boolean
    sent_at: string | null
    shared_by: string | null
    file?: SlackMessageFile
    media?: { name: string; thumbnail: ImageSources | null; image: ImageSources | null; gif_url: string | null }
    link?: { url: string; text: string }
}
</script>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue"
import axios from "axios"
import { InputText, Select } from "primevue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArrowDown, faCommentDots, faExternalLink, faImage, faLink, faSearch } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { formatDistanceToNowStrict, isToday, isYesterday } from "date-fns"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import Image from "@/Common/Components/Image.vue"
import TicketAttachmentPreview, { attachmentIconFor } from "@/Components/Tickets/TicketAttachmentPreview.vue"

library.add(faArrowDown, faCommentDots, faExternalLink, faImage, faLink, faSearch)

type Sort = "newest" | "oldest"

const props = withDefaults(defineProps<{ url: string; kinds?: SharedKind[] }>(), { kinds: () => ["all", "files", "media", "links"] })
const emit = defineEmits<{ (e: "jump", item: SharedItem): void }>()

const kind = ref<SharedKind>("all")
const sort = ref<Sort>("newest")
const search = ref("")
const items = ref<SharedItem[]>([])
const nextCursor = ref<string | null>(null)
const isLoading = ref(false)
const loadError = ref<string | null>(null)
const brokenThumbnails = ref<string[]>([])
const previewIndex = ref<number | null>(null)

const kindLabels = computed<Record<SharedKind, string>>(() => ({ all: ctrans("All"), files: ctrans("Files"), media: ctrans("Media"), links: ctrans("Links") }))
const kindOptions = computed(() => props.kinds.map((value) => ({ label: kindLabels.value[value], value })))
const sortOptions = computed(() => [
    { label: ctrans("Newest"), value: "newest" as Sort },
    { label: ctrans("Oldest"), value: "oldest" as Sort },
])

let requestId = 0
const load = async (append = false) => {
    const current = ++requestId
    isLoading.value = true
    loadError.value = null
    try {
        const { data } = await axios.get(props.url, {
            params: { kind: kind.value, sort: sort.value, q: search.value.trim() || undefined, cursor: append ? nextCursor.value ?? undefined : undefined },
        })
        if (current !== requestId) return
        items.value = append ? [...items.value, ...data.items] : data.items
        nextCursor.value = data.next_cursor
    } catch {
        if (current === requestId) loadError.value = ctrans("The files and links could not be loaded, try again")
    } finally {
        if (current === requestId) isLoading.value = false
    }
}

let searchTimeout: ReturnType<typeof setTimeout> | null = null
watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => load(), 300)
})
watch([kind, sort, () => props.url], () => load())
onMounted(() => load())

const previewUrl = (item: SharedItem) => item.file?.url ?? item.media?.image?.original ?? item.media?.gif_url ?? null
const isThumbnailShown = (item: SharedItem) => item.media
    ? !!previewUrl(item)
    : !!item.file?.thumbnail_url && !!item.file.url && !brokenThumbnails.value.includes(item.file.url)
const imageItems = computed(() => items.value.filter((item) => item.kind === "media" && isThumbnailShown(item)))
const previewFiles = computed(() => imageItems.value.map((item) => ({ name: titleOf(item), url: previewUrl(item) as string, mime: "image/*" })))

const sharedWhen = (sentAt: string | null) => {
    if (!sentAt) return ""
    const date = new Date(sentAt)
    if (isToday(date)) return ctrans("today")
    if (isYesterday(date)) return ctrans("yesterday")
    return formatDistanceToNowStrict(date, { addSuffix: true })
}
const sharedLine = (item: SharedItem) => item.shared_by
    ? ctrans("Shared by :name :when", { name: item.shared_by, when: sharedWhen(item.sent_at) })
    : ctrans("Shared :when", { when: sharedWhen(item.sent_at) })
const fullDate = (item: SharedItem) => item.sent_at ? useFormatTime(item.sent_at, { formatTime: "PPp" }) : undefined

const titleOf = (item: SharedItem) => item.file?.name ?? item.media?.name ?? item.link?.text ?? ""
const hostOf = (url: string) => {
    try {
        return new URL(url).host
    } catch {
        return url
    }
}
const fileIcon = (item: SharedItem) => attachmentIconFor({ name: item.file?.name ?? "", url: "" })
const fileHref = (item: SharedItem) => item.file?.state === "ready" ? item.file.url : (item.file?.state === "external" ? item.file.external_url : null)
const opensInTab = (item: SharedItem) => item.file?.state === "external" || !!item.file?.opens_in_tab

const open = (item: SharedItem) => {
    if (item.kind === "media" && isThumbnailShown(item)) {
        previewIndex.value = imageItems.value.indexOf(item)
    }
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div class="shrink-0 space-y-2 px-5 pb-2 pt-3">
            <span class="relative block">
                <FontAwesomeIcon icon="fal fa-search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fixed-width aria-hidden="true" />
                <InputText v-model="search" class="w-full !pl-9" :placeholder="ctrans('Search')" :aria-label="ctrans('Search files and links')" />
            </span>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <SegmentedToggle v-model="kind" :options="kindOptions" :ariaLabel="ctrans('Show')" />
                <Select v-model="sort" :options="sortOptions" optionLabel="label" optionValue="value" size="small" :aria-label="ctrans('Sort')" />
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 pb-4">
            <div v-if="isLoading && !items.length" class="space-y-2 py-2" aria-busy="true" :aria-label="ctrans('Loading…')">
                <div v-for="row in 5" :key="'files-skeleton-' + row" class="flex items-center gap-3 rounded border border-gray-100 p-3">
                    <span class="skeleton h-9 w-9 shrink-0 rounded" />
                    <div class="flex-1 space-y-1.5">
                        <span class="skeleton block h-3 rounded" :class="row % 2 ? 'w-2/3' : 'w-1/2'" />
                        <span class="skeleton block h-3 w-32 rounded" />
                    </div>
                </div>
            </div>

            <p v-else-if="loadError" class="py-3 text-xs text-red-600">{{ loadError }}</p>

            <div v-else-if="!items.length" class="flex flex-col items-center justify-center gap-1 py-16 text-center text-sm text-gray-400">
                <FontAwesomeIcon icon="fal fa-link" class="text-2xl text-gray-300" fixed-width aria-hidden="true" />
                {{ search.trim() ? ctrans("Nothing matches “:search”", { search: search.trim() }) : ctrans("No files or links shared here yet") }}
            </div>

            <ul v-else class="divide-y divide-gray-100 overflow-hidden rounded border border-gray-200">
                <li v-for="item in items" :key="item.key" class="group/item flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50">
                    <button
                        v-if="item.kind === 'media' && isThumbnailShown(item)"
                        type="button"
                        class="h-10 w-10 shrink-0 cursor-zoom-in overflow-hidden rounded border border-gray-200 bg-gray-100"
                        :aria-label="ctrans('Open :name', { name: titleOf(item) })"
                        @click="open(item)">
                        <Image v-if="item.media?.thumbnail" :src="item.media.thumbnail" :alt="titleOf(item)" image-cover class="h-full w-full" />
                        <img v-else-if="item.media?.gif_url" :src="item.media.gif_url" :alt="titleOf(item)" loading="lazy" decoding="async" class="h-full w-full object-cover" />
                        <img v-else :src="item.file!.thumbnail_url ?? ''" :alt="titleOf(item)" loading="lazy" decoding="async" class="h-full w-full object-cover" @error="brokenThumbnails.push(item.file!.url as string)" />
                    </button>
                    <span v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-gray-100 text-lg">
                        <FontAwesomeIcon v-if="item.kind === 'link'" icon="fal fa-link" class="text-gray-500" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon v-else-if="item.kind === 'media'" icon="fal fa-image" class="text-gray-500" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon v-else :icon="fileIcon(item).icon" :class="fileIcon(item).class" fixed-width aria-hidden="true" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <a
                            v-if="item.kind === 'link'"
                            :href="item.link!.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block truncate text-sm font-semibold text-[#1d1c1d] hover:text-[var(--app-accent-strong)] hover:underline"
                            v-tooltip="item.link!.url">{{ titleOf(item) }}</a>
                        <button
                            v-else-if="item.media"
                            type="button"
                            class="block max-w-full truncate text-left text-sm font-semibold text-[#1d1c1d] hover:text-[var(--app-accent-strong)] hover:underline"
                            @click="open(item)">{{ titleOf(item) }}</button>
                        <a
                            v-else-if="fileHref(item)"
                            :href="fileHref(item)!"
                            :download="opensInTab(item) ? undefined : titleOf(item)"
                            :target="opensInTab(item) ? '_blank' : undefined"
                            :rel="opensInTab(item) ? 'noopener noreferrer' : undefined"
                            class="block truncate text-sm font-semibold text-[#1d1c1d] hover:text-[var(--app-accent-strong)] hover:underline"
                            @click="item.kind === 'media' && isThumbnailShown(item) && ($event.preventDefault(), open(item))">{{ titleOf(item) }}</a>
                        <span v-else class="block truncate text-sm font-semibold text-gray-500" v-tooltip="item.file?.note ?? ctrans('Not available')">{{ titleOf(item) }}</span>
                        <span class="block truncate text-xs text-[#616061]" v-tooltip="fullDate(item)">
                            <template v-if="item.kind === 'link' && hostOf(item.link!.url) !== titleOf(item)">{{ hostOf(item.link!.url) }} · </template>{{ sharedLine(item) }}
                        </span>
                    </div>

                    <Button
                        type="tertiary"
                        size="xs"
                        class="shrink-0 opacity-0 focus:opacity-100 group-hover/item:opacity-100"
                        icon="fal fa-comment-dots"
                        :tooltip="ctrans('Show in conversation')"
                        @click="emit('jump', item)" />
                </li>
            </ul>

            <div v-if="nextCursor && items.length" class="flex justify-center pt-3">
                <Button type="tertiary" size="xs" icon="fal fa-arrow-down" :label="ctrans('Load more')" :loading="isLoading" @click="load(true)" />
            </div>
            <div v-else-if="isLoading && items.length" class="flex justify-center pt-3"><LoadingIcon /></div>
        </div>

        <TicketAttachmentPreview v-model:index="previewIndex" :files="previewFiles" />
    </div>
</template>
