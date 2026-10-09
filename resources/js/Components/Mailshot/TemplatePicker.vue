<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faDesktop, faMobile, faSearch, faArrowLeft, faEye } from '@fal'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { ctrans } from '@/Composables/useTrans'

library.add(faDesktop, faMobile, faSearch, faArrowLeft, faEye)

const props = defineProps<{
    data: any
    tab: string
}>()

const emits = defineEmits<{
    (e: 'select-snapshot', snapshot: any): void
}>()

const MOBILE_PREVIEW_WIDTH = 375

const search = ref('')
const selectedId = ref<number | null>(null)
const device = ref<'desktop' | 'mobile'>('desktop')
const previews = ref<Record<number, string>>({})
const loadingPreview = ref<Record<number, boolean>>({})
const isChoosing = ref(false)

const isMailshotTab = computed(() => props.tab === 'previous_mailshots' || props.tab === 'other_store_mailshots')

const items = computed<any[]>(() => props.data?.data ?? [])

const itemTitle = (item: any): string => item.name ?? item.subject ?? `#${item.id}`

const filteredItems = computed(() => {
    const query = search.value.trim().toLowerCase()
    if (!query) {
        return items.value
    }

    return items.value.filter((item) =>
        [item.name, item.subject, item.shop_name].some((value) => String(value ?? '').toLowerCase().includes(query))
    )
})

const selectedItem = computed(() => items.value.find((item) => item.id === selectedId.value) ?? null)

const templateRoute = (id: number, preview: boolean) =>
    isMailshotTab.value
        ? route('grp.json.mailshot.template', { mailshot: id, ...(preview ? { preview: 1 } : {}) })
        : route('grp.json.email_templates.layout', { emailTemplate: id, ...(preview ? { preview: 1 } : {}) })

const loadPreview = async (id: number) => {
    if (previews.value[id] !== undefined || loadingPreview.value[id]) {
        return
    }
    loadingPreview.value[id] = true
    try {
        const { data } = await axios.get(templateRoute(id, true))
        previews.value[id] = data?.html ?? ''
    } catch {
        previews.value[id] = ''
    } finally {
        loadingPreview.value[id] = false
    }
}

const openPreview = (item: any) => {
    selectedId.value = item.id
    loadPreview(item.id)
}

const backToList = () => {
    selectedId.value = null
}

const observer = new IntersectionObserver(
    (entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                loadPreview(Number((entry.target as HTMLElement).dataset.templateId))
                observer.unobserve(entry.target)
            }
        })
    },
    { rootMargin: '200px' },
)

const observeCard = (element: Element | null, id: number) => {
    if (!element) {
        return
    }
    (element as HTMLElement).dataset.templateId = String(id)
    observer.observe(element)
}

onBeforeUnmount(() => observer.disconnect())

watch(items, () => {
    previews.value = {}
    selectedId.value = null
})

const chooseSelected = async () => {
    if (!selectedId.value) {
        return
    }
    isChoosing.value = true
    try {
        const { data } = await axios.get(templateRoute(selectedId.value, false))
        if (data) {
            emits('select-snapshot', data)
        }
    } catch {
        notify({ title: ctrans('Something went wrong'), text: ctrans('Failed to load the template'), type: 'error' })
    } finally {
        isChoosing.value = false
    }
}
</script>

<template>
    <div class="mt-4 flex h-[68vh] min-h-[420px] flex-col overflow-hidden rounded-lg border border-gray-200">
        <template v-if="!selectedItem">
            <div class="flex items-center justify-between gap-x-3 border-b border-gray-200 bg-white px-4 py-2.5">
                <div class="relative w-72">
                    <FontAwesomeIcon icon="fal fa-search" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400" fixed-width aria-hidden="true" />
                    <input v-model="search" type="search" :placeholder="ctrans('Search templates')" :aria-label="ctrans('Search templates')"
                        class="w-full rounded border-gray-300 py-1.5 pl-8 pr-2 text-sm focus:border-[var(--theme-color-4)] focus:ring-[var(--theme-color-4)]" />
                </div>
                <span class="text-xs text-gray-500">{{ filteredItems.length }} {{ ctrans('templates') }}</span>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto bg-gray-50 p-4">
                <div v-if="!filteredItems.length" class="py-16 text-center text-sm text-gray-500">
                    {{ items.length ? ctrans('No templates match your search') : ctrans('No templates here yet') }}
                </div>

                <div v-else class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                    <button v-for="item in filteredItems" :key="item.id" :ref="(element) => observeCard(element as Element, item.id)"
                        type="button"
                        class="group flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white text-left transition hover:border-[var(--theme-color-4)] hover:shadow-md"
                        @click="openPreview(item)">
                        <div class="relative h-56 overflow-hidden border-b border-gray-100 bg-gray-50">
                            <iframe v-if="previews[item.id]" :srcdoc="previews[item.id]" sandbox="" loading="lazy" tabindex="-1"
                                :title="itemTitle(item)"
                                class="pointer-events-none h-[560px] w-[250%] origin-top-left scale-[0.4] border-0 bg-white" />
                            <div v-else-if="loadingPreview[item.id] || previews[item.id] === undefined" class="h-full w-full animate-pulse bg-gray-100" />
                            <div v-else class="flex h-full items-center justify-center text-xs text-gray-400">{{ ctrans('No preview') }}</div>

                            <div class="absolute inset-0 flex items-center justify-center bg-black/0 opacity-0 transition group-hover:bg-black/30 group-hover:opacity-100">
                                <span class="flex items-center gap-x-1.5 rounded bg-white px-3 py-1.5 text-xs font-medium text-gray-800 shadow">
                                    <FontAwesomeIcon icon="fal fa-eye" fixed-width aria-hidden="true" />
                                    {{ ctrans('Preview') }}
                                </span>
                            </div>
                        </div>
                        <div class="px-3 py-2.5">
                            <div class="truncate text-sm font-medium text-gray-900" :title="itemTitle(item)">{{ itemTitle(item) }}</div>
                            <div class="truncate text-[11px] text-gray-500">
                                <span v-if="item.shop_name">{{ item.shop_name }} &bull; </span>
                                <span>{{ useFormatTime(item.created_at) }}</span>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="flex items-center justify-between gap-x-3 border-b border-gray-200 bg-white px-4 py-2">
                <div class="flex min-w-0 items-center gap-x-3">
                    <button type="button" class="flex h-8 shrink-0 items-center gap-x-1.5 rounded px-2 text-sm text-gray-600 hover:bg-gray-100" @click="backToList">
                        <FontAwesomeIcon icon="fal fa-arrow-left" fixed-width aria-hidden="true" />
                        {{ ctrans('All templates') }}
                    </button>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-gray-800">{{ itemTitle(selectedItem) }}</div>
                        <div class="truncate text-[11px] text-gray-500">
                            <span v-if="selectedItem.shop_name">{{ selectedItem.shop_name }} &bull; </span>
                            <span>{{ useFormatTime(selectedItem.created_at) }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center rounded bg-gray-100 p-0.5">
                    <button type="button" class="h-7 w-9 rounded text-sm"
                        :class="device === 'desktop' ? 'bg-white text-[var(--theme-color-4)] shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        v-tooltip="ctrans('Desktop')" @click="device = 'desktop'">
                        <FontAwesomeIcon icon="fal fa-desktop" fixed-width aria-hidden="true" />
                    </button>
                    <button type="button" class="h-7 w-9 rounded text-sm"
                        :class="device === 'mobile' ? 'bg-white text-[var(--theme-color-4)] shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        v-tooltip="ctrans('Mobile')" @click="device = 'mobile'">
                        <FontAwesomeIcon icon="fal fa-mobile" fixed-width aria-hidden="true" />
                    </button>
                </div>
            </div>

            <div class="flex min-h-0 flex-1 justify-center overflow-hidden bg-gray-100 p-4">
                <div v-if="loadingPreview[selectedItem.id]" class="flex items-center">
                    <LoadingIcon class="text-4xl text-gray-400" />
                </div>
                <div v-else-if="!previews[selectedItem.id]" class="flex items-center text-sm text-gray-500">
                    {{ ctrans('No preview available for this template') }}
                </div>
                <iframe v-else :key="selectedItem.id" :srcdoc="previews[selectedItem.id]" sandbox="" :title="ctrans('Template preview')"
                    class="h-full border-0 bg-white shadow-sm transition-all"
                    :class="device === 'mobile' ? 'rounded-[24px] border-[10px] border-gray-800' : ''"
                    :style="{ width: device === 'mobile' ? `${MOBILE_PREVIEW_WIDTH + 20}px` : '100%' }" />
            </div>

            <div class="flex items-center justify-end gap-x-2 border-t border-gray-200 bg-white px-4 py-3">
                <button type="button" class="h-9 rounded border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-50" @click="backToList">
                    {{ ctrans('Back') }}
                </button>
                <button type="button"
                    class="flex h-9 items-center gap-x-2 rounded bg-[var(--theme-color-4)] px-5 text-sm font-medium text-[var(--theme-color-5)] hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)] disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="isChoosing" @click="chooseSelected">
                    <LoadingIcon v-if="isChoosing" />
                    {{ ctrans('Choose this template') }}
                </button>
            </div>
        </template>
    </div>
</template>
