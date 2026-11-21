<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script lang="ts">
export interface SlackMessageFile {
    id: string | null
    name: string
    type: string | null
    size: number | null
    state: "ready" | "pending" | "skipped" | "not_available" | "external"
    note: string | null
    is_image: boolean
    opens_in_tab: boolean
    url: string | null
    thumbnail_url: string | null
    external_url: string | null
}
</script>

<script setup lang="ts">
import { computed, ref } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faDownload, faExternalLink, faFile } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import { formatBytes } from "@/Composables/useUploadLimits"
import TicketAttachmentPreview, { attachmentIconFor } from "@/Components/Tickets/TicketAttachmentPreview.vue"

library.add(faDownload, faExternalLink, faFile)

const props = defineProps<{ files: SlackMessageFile[] }>()

const brokenThumbnails = ref<string[]>([])
const previewIndex = ref<number | null>(null)

const isShownAsImage = (file: SlackMessageFile) => file.is_image && !!file.url && !!file.thumbnail_url && !brokenThumbnails.value.includes(file.url)

const images = computed(() => props.files.filter(isShownAsImage))
const others = computed(() => props.files.filter((file) => !isShownAsImage(file)))
const previewFiles = computed(() => images.value.map((file) => ({ name: file.name, url: file.url as string, mime: "image/*" })))

const sizeOf = (bytes: number) => (bytes >= 1024 ** 3 ? `${(bytes / 1024 ** 3).toFixed(1)}GB` : formatBytes(bytes))
const details = (file: SlackMessageFile) => [file.type, file.size ? sizeOf(file.size) : null].filter(Boolean).join(" · ")
const iconOf = (file: SlackMessageFile) => attachmentIconFor({ name: file.name, url: "" })
</script>

<template>
    <div class="mt-1 space-y-1.5">
        <div v-if="images.length" class="flex flex-wrap gap-1.5">
            <button
                v-for="(file, index) in images"
                :key="file.url ?? index"
                type="button"
                v-tooltip="file.name"
                :aria-label="ctrans('Open :name', { name: file.name })"
                class="block h-28 w-28 shrink-0 cursor-zoom-in overflow-hidden rounded border border-gray-200 bg-gray-100 hover:border-[var(--app-accent-muted)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--app-accent)]"
                @click="previewIndex = index">
                <img :src="file.thumbnail_url ?? ''" :alt="file.name" loading="lazy" decoding="async" class="h-full w-full object-cover" @error="brokenThumbnails.push(file.url as string)" />
            </button>
        </div>

        <div v-if="others.length" class="flex flex-wrap gap-1.5">
            <template v-for="(file, index) in others" :key="(file.id ?? file.name) + index">
                <a
                    v-if="file.state === 'ready' && file.url"
                    :href="file.url"
                    :download="file.opens_in_tab ? undefined : file.name"
                    :target="file.opens_in_tab ? '_blank' : undefined"
                    :rel="file.opens_in_tab ? 'noopener' : undefined"
                    v-tooltip="file.opens_in_tab ? ctrans('Open in new tab') : ctrans('Download')"
                    class="group/file inline-flex max-w-full items-center gap-2 rounded border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700 hover:border-[var(--app-accent-muted)] hover:bg-[var(--app-accent-soft)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--app-accent)]">
                    <FontAwesomeIcon :icon="iconOf(file).icon" class="shrink-0 text-base" :class="iconOf(file).class" fixed-width aria-hidden="true" />
                    <span class="min-w-0">
                        <span class="block truncate font-medium text-gray-800">{{ file.name }}</span>
                        <span v-if="details(file)" class="block whitespace-nowrap text-xxs text-gray-500">{{ details(file) }}</span>
                    </span>
                    <FontAwesomeIcon :icon="file.opens_in_tab ? 'fal fa-external-link' : 'fal fa-download'" class="shrink-0 text-gray-400 group-hover/file:text-[var(--app-accent-strong)]" fixed-width aria-hidden="true" />
                </a>

                <a
                    v-else-if="file.state === 'external' && file.external_url"
                    :href="file.external_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex max-w-full items-center gap-2 rounded border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700 hover:border-[var(--app-accent-muted)] hover:bg-[var(--app-accent-soft)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--app-accent)]">
                    <FontAwesomeIcon icon="fal fa-external-link" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
                    <span class="min-w-0">
                        <span class="block truncate font-medium text-gray-800">{{ file.name }}</span>
                        <span class="block whitespace-nowrap text-xxs text-gray-500">{{ ctrans("Link, opens in a new tab") }}</span>
                    </span>
                </a>

                <span v-else class="inline-flex max-w-full items-center gap-2 rounded border border-dashed border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-500">
                    <FontAwesomeIcon icon="fal fa-file" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
                    <span class="min-w-0">
                        <span class="block truncate font-medium text-gray-600">{{ file.name }}</span>
                        <span class="block whitespace-nowrap text-xxs text-gray-500">{{ [file.note ?? ctrans("Not available"), details(file)].filter(Boolean).join(" · ") }}</span>
                    </span>
                </span>
            </template>
        </div>

        <TicketAttachmentPreview v-model:index="previewIndex" :files="previewFiles" />
    </div>
</template>
