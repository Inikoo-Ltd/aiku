<script setup lang="ts">
import { ref, computed, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
    faSpinner,
    faPlus,
    faMagnifyingGlass,
} from "@fortawesome/free-solid-svg-icons"
import { Dialog } from "primevue"

export interface TemplateItem {
    id: number
    name: string
    language: string
    category: string | null
    body: string
    parameter_count: number
    merge_tags?: string[]
    auto_fill?: boolean
    missing_tags?: string[]
    resolved_values?: (string | null)[]
    preview?: string | null
}

const props = defineProps<{
    visible: boolean
    templates: TemplateItem[]
    isLoading: boolean
    selectedTemplateId?: number | null
    organisationSlug?: string | null
    shopSlug?: string | null
}>()

const emit = defineEmits<{
    (e: "update:visible", value: boolean): void
    (e: "select", template: TemplateItem): void
}>()

const dialogVisible = computed({
    get: () => props.visible,
    set: (v: boolean) => emit("update:visible", v),
})

const search = ref("")
const hoveredTemplate = ref<TemplateItem | null>(null)
const popupStyle = ref<Record<string, string>>({})

watch(() => props.visible, (v) => { if (v) search.value = "" })

const filteredTemplates = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return props.templates
    return props.templates.filter((t) =>
        t.name.toLowerCase().includes(q) || (t.category ?? "").toLowerCase().includes(q)
    )
})

const escapeHtml = (str: string) =>
    str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;")

const tagBadge = (label: string) =>
    `<span class="inline-flex items-center px-1.5 py-0.5 mx-0.5 rounded bg-blue-50 text-blue-600 text-[10px] font-medium align-baseline whitespace-nowrap">${escapeHtml(label)}</span>`

const formatBodyWithTags = (body: string, tags: string[] = []) => {
    return escapeHtml(body).replace(/\{\{(\d+)\}\}/g, (_match, num) => {
        const index = parseInt(num) - 1
        const label = tags[index] ? String(tags[index]).replace(/_/g, " ") : `{{${num}}}`
        return tagBadge(label)
    })
}

const POPUP_WIDTH = 288
let hidePopupTimer: ReturnType<typeof setTimeout> | null = null

const showTemplatePopup = (template: TemplateItem, event: MouseEvent) => {
    if (hidePopupTimer) { clearTimeout(hidePopupTimer); hidePopupTimer = null }
    const row = (event.currentTarget as HTMLElement).getBoundingClientRect()
    const fitsRight = row.right + 12 + POPUP_WIDTH <= window.innerWidth
    popupStyle.value = {
        top: `${Math.min(row.top, window.innerHeight - 240)}px`,
        left: fitsRight ? `${row.right + 12}px` : `${Math.max(8, row.left - 12 - POPUP_WIDTH)}px`,
    }
    hoveredTemplate.value = template
}

const scheduleHidePopup = () => {
    hidePopupTimer = setTimeout(() => { hoveredTemplate.value = null }, 150)
}

const cancelHidePopup = () => {
    if (hidePopupTimer) { clearTimeout(hidePopupTimer); hidePopupTimer = null }
}

const createTemplateUrl = computed(() => {
    if (!props.organisationSlug || !props.shopSlug) return null
    try {
        return route("grp.org.shops.show.chat.whatsapp_templates.create", [props.organisationSlug, props.shopSlug])
    } catch {
        return null
    }
})
</script>

<template>
    <Dialog v-model:visible="dialogVisible" modal :header="trans('WhatsApp templates')" :style="{ width: '28rem' }">
        <div v-if="isLoading" class="flex items-center justify-center py-8 text-gray-400">
            <FontAwesomeIcon :icon="faSpinner" class="animate-spin" />
        </div>

        <template v-else>
            <div class="relative mb-2">
                <FontAwesomeIcon :icon="faMagnifyingGlass" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400" />
                <input v-model="search" type="text" :placeholder="trans('Search')"
                    class="w-full text-sm border rounded-lg pl-8 pr-3 py-1.5 focus:outline-none focus:border-gray-400" />
            </div>

            <a v-if="createTemplateUrl"
                :href="createTemplateUrl"
                target="_blank" rel="noopener noreferrer"
                class="flex items-center gap-2 px-3 py-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg transition-colors mb-2">
                <FontAwesomeIcon :icon="faPlus" class="text-xs" />
                {{ trans('Create new template') }}
            </a>

            <div v-if="!filteredTemplates.length" class="py-6 text-center text-sm text-gray-400">
                {{ templates.length ? trans('No templates match your search') : trans('No approved templates found for this shop') }}
            </div>
            <div v-else class="max-h-48 overflow-y-auto border rounded-lg divide-y" @scroll="hoveredTemplate = null">
                <button v-for="template in filteredTemplates" :key="template.id"
                    class="w-full text-left px-3 py-2 hover:bg-gray-50 transition-colors"
                    :class="{ 'bg-green-50': selectedTemplateId === template.id }"
                    @mouseenter="showTemplatePopup(template, $event)" @mouseleave="scheduleHidePopup"
                    @click="emit('select', template)">
                    <div class="text-sm font-medium">{{ template.name }}</div>
                    <div class="text-[11px] text-gray-400">
                        {{ template.language }}<span v-if="template.category"> &middot; {{ template.category }}</span>
                    </div>
                </button>
            </div>
        </template>

        <Teleport to="body">
            <div v-if="hoveredTemplate" :style="popupStyle"
                @mouseenter="cancelHidePopup"
                @mouseleave="scheduleHidePopup"
                class="fixed z-[2000] w-72 rounded-lg border bg-white p-3 shadow-xl">
                <div class="text-sm font-medium mb-1">{{ hoveredTemplate.name }}</div>
                <div class="text-xs text-gray-600 whitespace-pre-line max-h-60 overflow-y-auto leading-relaxed"
                    v-html="formatBodyWithTags(hoveredTemplate.body, hoveredTemplate.merge_tags ?? [])"></div>
                <div class="mt-2 pt-2 border-t text-[10px] text-gray-400">
                    {{ hoveredTemplate.language }}<span v-if="hoveredTemplate.category"> &middot; {{ hoveredTemplate.category }}</span>
                    &middot; {{ trans(':count parameters', { count: hoveredTemplate.parameter_count }) }}
                </div>
            </div>
        </Teleport>
    </Dialog>
</template>
