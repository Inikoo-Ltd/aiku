<script setup lang="ts">
import { computed, ref } from 'vue'
import axios from 'axios'
import Dialog from 'primevue/dialog'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faSearch, faCheck, faPuzzlePiece, faExternalLink, faChevronLeft, faChevronRight } from '@fal'
import { ctrans } from '@/Composables/useTrans'

library.add(faSearch, faCheck, faPuzzlePiece, faExternalLink, faChevronLeft, faChevronRight)

interface DynamicBlock {
    id: number
    name: string
    compiled_layout: string
    layout: Record<string, any> | null
    workshop_url: string
    shop_name: string
}

const props = defineProps<{
    shopSlug?: string
}>()

const PER_PAGE = 12
const SEARCH_DEBOUNCE_MS = 300

const isOpen = ref(false)
const searchQuery = ref('')
const isFromOtherShops = ref(false)
const dynamicBlocks = ref<DynamicBlock[]>([])
const selectedBlockId = ref<number | null>(null)
const isLoading = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)

let resolveSelection: ((value: Record<string, any>) => void) | null = null
let wantsLayout = false
let rejectSelection: (() => void) | null = null
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null
let latestRequestId = 0

const selectedBlock = computed(() => dynamicBlocks.value.find((block) => block.id === selectedBlockId.value) ?? null)

const emptyMessage = computed(() => {
    if (searchQuery.value.trim()) {
        return ctrans('No dynamic blocks found matching :search', { search: searchQuery.value.trim() })
    }

    return isFromOtherShops.value
        ? ctrans('No dynamic blocks in other shops yet.')
        : ctrans('No dynamic blocks yet. Create a template in Comms → Templates with Dynamic block turned on.')
})

const fetchDynamicBlocks = async (page = 1) => {
    if (!props.shopSlug) {
        dynamicBlocks.value = []
        return
    }

    const requestId = ++latestRequestId
    isLoading.value = true
    try {
        const { data } = await axios.get(
            route('grp.json.shop.dynamic_block_email_templates', { shop: props.shopSlug }),
            { params: { search: searchQuery.value.trim(), other_shops: isFromOtherShops.value ? 1 : 0, per_page: PER_PAGE, page } }
        )
        if (requestId !== latestRequestId) {
            return
        }
        dynamicBlocks.value = data?.data ?? []
        currentPage.value = data?.meta?.current_page ?? 1
        lastPage.value = data?.meta?.last_page ?? 1
    } catch {
        if (requestId === latestRequestId) {
            dynamicBlocks.value = []
        }
    } finally {
        if (requestId === latestRequestId) {
            isLoading.value = false
        }
    }
}

const onSearchInput = () => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer)
    }
    searchDebounceTimer = setTimeout(() => fetchDynamicBlocks(1), SEARCH_DEBOUNCE_MS)
}

const setSource = (fromOtherShops: boolean) => {
    if (isFromOtherShops.value === fromOtherShops) {
        return
    }
    isFromOtherShops.value = fromOtherShops
    selectedBlockId.value = null
    fetchDynamicBlocks(1)
}

const extractBodyHtml = (compiledLayout: string): string =>
    new DOMParser().parseFromString(compiledLayout, 'text/html').body.innerHTML.trim()

const insertSelected = () => {
    if (!selectedBlock.value) {
        return
    }
    const selection = {
        name: selectedBlock.value.name,
        value: extractBodyHtml(selectedBlock.value.compiled_layout),
    }
    resolveSelection?.(wantsLayout ? { ...selection, layout: selectedBlock.value.layout } : selection)
    resolveSelection = null
    rejectSelection = null
    isOpen.value = false
}

const onHide = () => {
    rejectSelection?.()
    resolveSelection = null
    rejectSelection = null
}

const openModal = (options: { withLayout?: boolean } = {}) => {
    wantsLayout = !!options.withLayout
    return new Promise((resolve, reject) => {
        resolveSelection = resolve
        rejectSelection = reject
        searchQuery.value = ''
        isFromOtherShops.value = false
        selectedBlockId.value = null
        dynamicBlocks.value = []
        isOpen.value = true
        fetchDynamicBlocks(1)
    })
}

defineExpose({
    openModal,
})
</script>

<template>
    <Dialog v-model:visible="isOpen" modal :draggable="false" :header="ctrans('Insert dynamic block')"
        :style="{ width: '64rem' }" :breakpoints="{ '1100px': '95vw' }" @hide="onHide"
        :pt="{ header: { class: '!px-5 !py-3 border-b border-gray-200' }, content: { class: '!p-0' }, footer: { class: '!px-5 !py-3 border-t border-gray-200' } }">
        <div class="flex h-[65vh] min-h-[420px] flex-col">
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-5 py-3">
                <div class="flex rounded-lg bg-gray-100 p-1">
                    <button type="button" class="rounded-md px-3 py-1.5 text-sm transition"
                        :class="!isFromOtherShops ? 'bg-white font-medium text-[var(--theme-color-4)] shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                        @click="setSource(false)">
                        {{ ctrans('This shop') }}
                    </button>
                    <button type="button" class="rounded-md px-3 py-1.5 text-sm transition"
                        :class="isFromOtherShops ? 'bg-white font-medium text-[var(--theme-color-4)] shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                        @click="setSource(true)">
                        {{ ctrans('Other shops') }}
                    </button>
                </div>
                <div class="relative min-w-[220px] flex-1">
                    <FontAwesomeIcon icon="fal fa-search" class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400" fixed-width aria-hidden="true" />
                    <input v-model="searchQuery" type="search" :placeholder="ctrans('Search dynamic blocks')" :aria-label="ctrans('Search dynamic blocks')"
                        class="w-full rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-[var(--theme-color-4)] focus:ring-[var(--theme-color-4)]"
                        @input="onSearchInput" />
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto bg-gray-50 p-4">
                <div v-if="isLoading" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="placeholder in 6" :key="placeholder" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <div class="h-44 animate-pulse bg-gray-100" />
                        <div class="p-3"><div class="h-3 w-2/3 animate-pulse rounded bg-gray-100" /></div>
                    </div>
                </div>

                <div v-else-if="!dynamicBlocks.length" class="flex h-full flex-col items-center justify-center px-6 py-12 text-center">
                    <FontAwesomeIcon icon="fal fa-puzzle-piece" class="mb-3 text-4xl text-gray-300" fixed-width aria-hidden="true" />
                    <div class="max-w-md text-sm text-gray-600">{{ emptyMessage }}</div>
                </div>

                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <button v-for="dynamicBlock in dynamicBlocks" :key="dynamicBlock.id" type="button"
                        class="group relative flex flex-col overflow-hidden rounded-lg border bg-white text-left transition"
                        :class="selectedBlockId === dynamicBlock.id
                            ? 'border-[var(--theme-color-4)] ring-2 ring-[var(--theme-color-4)]'
                            : 'border-gray-200 hover:border-[var(--theme-color-4)] hover:shadow-md'"
                        :aria-pressed="selectedBlockId === dynamicBlock.id"
                        @click="selectedBlockId = dynamicBlock.id"
                        @dblclick="selectedBlockId = dynamicBlock.id; insertSelected()">
                        <span class="absolute right-2 top-2 z-10 flex h-6 w-6 items-center justify-center rounded-full border text-xs"
                            :class="selectedBlockId === dynamicBlock.id ? 'border-[var(--theme-color-4)] bg-[var(--theme-color-4)] text-[var(--theme-color-5)]' : 'border-gray-300 bg-white/90 text-transparent group-hover:text-gray-300'">
                            <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
                        </span>
                        <div class="pointer-events-none h-44 overflow-hidden border-b border-gray-100 bg-white">
                            <iframe :srcdoc="dynamicBlock.compiled_layout" sandbox="" loading="lazy" tabindex="-1" :title="dynamicBlock.name"
                                class="h-[440px] w-[250%] origin-top-left scale-[0.4] border-0" />
                        </div>
                        <div class="px-3 py-2.5">
                            <div class="truncate text-sm font-medium text-gray-900" :title="dynamicBlock.name">{{ dynamicBlock.name }}</div>
                            <div v-if="isFromOtherShops && dynamicBlock.shop_name" class="truncate text-[11px] text-gray-500">{{ dynamicBlock.shop_name }}</div>
                        </div>
                    </button>
                </div>
            </div>

            <div v-if="lastPage > 1" class="flex items-center justify-end gap-x-1 border-t border-gray-100 px-5 py-2 text-xs text-gray-500">
                <button type="button" class="h-7 w-7 rounded border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-40"
                    :disabled="currentPage <= 1 || isLoading" :aria-label="ctrans('Previous page')" @click="fetchDynamicBlocks(currentPage - 1)">
                    <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width aria-hidden="true" />
                </button>
                <span class="px-2">{{ currentPage }} / {{ lastPage }}</span>
                <button type="button" class="h-7 w-7 rounded border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-40"
                    :disabled="currentPage >= lastPage || isLoading" :aria-label="ctrans('Next page')" @click="fetchDynamicBlocks(currentPage + 1)">
                    <FontAwesomeIcon icon="fal fa-chevron-right" fixed-width aria-hidden="true" />
                </button>
            </div>
        </div>

        <template #footer>
            <div class="flex w-full items-center justify-between gap-x-3">
                <div class="min-w-0 truncate text-sm text-gray-600">
                    <template v-if="selectedBlock">
                        <span class="font-medium text-gray-900">{{ selectedBlock.name }}</span>
                        <a :href="selectedBlock.workshop_url" target="_blank" rel="noopener"
                            class="ml-2 inline-flex items-center gap-x-1 text-xs text-[var(--theme-color-4)] hover:underline">
                            <FontAwesomeIcon icon="fal fa-external-link" fixed-width aria-hidden="true" />
                            {{ ctrans('Edit block') }}
                        </a>
                    </template>
                    <span v-else class="text-gray-400">{{ ctrans('Select a block to insert') }}</span>
                </div>
                <div class="flex shrink-0 items-center gap-x-2">
                    <button type="button" class="h-9 rounded border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-50" @click="isOpen = false">
                        {{ ctrans('Cancel') }}
                    </button>
                    <button type="button"
                        class="flex h-9 items-center gap-x-2 rounded bg-[var(--theme-color-4)] px-5 text-sm font-medium text-[var(--theme-color-5)] hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)] disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!selectedBlock" @click="insertSelected">
                        {{ ctrans('Insert block') }}
                    </button>
                </div>
            </div>
        </template>
    </Dialog>
</template>
