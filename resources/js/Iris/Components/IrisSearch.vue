<script setup lang="ts">
import { ref, computed, watch, onBeforeMount, defineAsyncComponent, nextTick, inject } from "vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { debounce } from "lodash-es"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import Popover from "primevue/popover"
import { searchRoute } from "@/Iris/Composables/useSearchRoute"
import { useSearchFeaturedItems } from "@/Iris/Composables/useSearchFeaturedItems"
library.add(faSearch)

const SearchResultCatalogue = defineAsyncComponent(() => import("@/Iris/Components/SearchResultCatalogue.vue"))
const SearchFeatured = defineAsyncComponent(() => import("@/Iris/Components/SearchFeatured.vue"))

const props = defineProps<{
    id: string
    fieldValueSearch?: {
        placeholder?: string
    }
}>()

const layout = inject('layout', retinaLayoutStructure)

const inputValue = ref('')

onBeforeMount(() => {
    const params = new URLSearchParams(window.location.search)
    const q = params.get('q')
    if (q) inputValue.value = q
})

const internalResults = ref<any>(null)
const searchLogUlid = ref<string | null>(null)
const isInternalLoading = ref(false)
const showDropdown = ref(false)
const inputRef = ref<HTMLInputElement | null>(null)
const popoverRef = ref<InstanceType<typeof Popover> | null>(null)
const isPopoverVisible = ref(false)
let internalAbort: AbortController | null = null

// Same client-side cache as the staff SearchBar: repeated queries within the TTL
// (backspacing, retyping) render instantly without hitting the server
const CACHE_TTL_MS = 30_000
const CACHE_MAX_ENTRIES = 50
const responseCache = new Map<string, { data: any, expiresAt: number }>()

const cacheResponse = (query: string, data: any) => {
    if (responseCache.size >= CACHE_MAX_ENTRIES) {
        responseCache.delete(responseCache.keys().next().value as string)
    }
    responseCache.set(query, { data, expiresAt: Date.now() + CACHE_TTL_MS })
}

const getCachedResponse = (query: string): any | null => {
    const entry = responseCache.get(query)
    if (!entry) return null
    if (entry.expiresAt < Date.now()) {
        responseCache.delete(query)
        return null
    }
    return entry.data
}

const openPopover = () => {
    if (isPopoverVisible.value || !inputRef.value) {
        return
    }
    popoverRef.value?.show({ currentTarget: inputRef.value } as any)
}

const closePopover = () => {
    popoverRef.value?.hide()
}

// Point the popover caret at the horizontal center of the input, even though the
// panel itself spans the full viewport width (PrimeVue can no longer shift it to align)
const updateCaretPosition = () => {
    if (!inputRef.value) {
        return
    }
    const rect = inputRef.value.getBoundingClientRect()
    const panel = document.querySelector('.iris-search-popover') as HTMLElement | null
    panel?.style.setProperty('--caret-left', `${rect.left + rect.width / 2}px`)
}

const onPopoverShow = () => {
    isPopoverVisible.value = true
    showDropdown.value = true
    nextTick(updateCaretPosition)
    window.addEventListener('resize', updateCaretPosition)
    window.addEventListener('scroll', updateCaretPosition, true)
}

const onPopoverHide = () => {
    isPopoverVisible.value = false
    showDropdown.value = false
    window.removeEventListener('resize', updateCaretPosition)
    window.removeEventListener('scroll', updateCaretPosition, true)
}

const fetchResults = debounce(async (query: string) => {
    internalAbort?.abort()
    const abort = new AbortController()
    internalAbort = abort
    isInternalLoading.value = true
    try {
        const { data } = await axios.get(
            route(searchRoute('catalogue'), { q: query, source: 'desktop_bar' }),
            { signal: abort.signal }
        )
        cacheResponse(query, data)
        internalResults.value = data.results ?? null
        searchLogUlid.value = data.search_log_ulid ?? null
    } catch (error) {
        if (axios.isCancel(error)) {
            return
        }
        console.error('Iris catalogue search failed', error)
        internalResults.value = null
    } finally {
        if (internalAbort === abort) {
            isInternalLoading.value = false
        }
    }
}, 250)

const cancelPendingSearch = () => {
    fetchResults.cancel()
    internalAbort?.abort()
    internalAbort = null
    isInternalLoading.value = false
}

// An empty field shows what the shop merchandises instead of nothing at all. The popover
// only opens once there is something to put in it, so a shop without featured items keeps
// the plain focus behaviour.
const { featuredResults, isFeaturedLoading, hasFeaturedResults, fetchFeaturedResults } = useSearchFeaturedItems()

const isShowingFeatured = computed(() => !inputValue.value.trim() && (isFeaturedLoading.value || hasFeaturedResults.value))

let isInputFocused = false

const showFeaturedOrClose = async () => {
    await fetchFeaturedResults()

    if (!isInputFocused || inputValue.value.trim()) {
        return
    }

    if (hasFeaturedResults.value) {
        openPopover()
    } else {
        closePopover()
    }
}

// On the results page the box is pre-filled from ?q=, so typing without selecting first
// prepends to the old term and searches the concatenation ("incensesalt lampssoap loaves").
// Selecting on focus makes the next keystroke replace it, while still allowing edits.
let isFocusSelection = false

const onFocus = (event: FocusEvent) => {
    isFocusSelection = true
    isInputFocused = true;
    (event.target as HTMLInputElement)?.select()

    if (inputValue.value.trim()) {
        openPopover()
        return
    }

    showFeaturedOrClose()
}

// The mouseup of the click that focused the field would otherwise collapse that selection
const onMouseUp = (event: MouseEvent) => {
    if (isFocusSelection) {
        event.preventDefault()
        isFocusSelection = false
    }
}

const onBlur = () => {
    isFocusSelection = false
    isInputFocused = false
}

const onSearchInput = (event: Event) => {
    inputValue.value = (event.target as HTMLInputElement)?.value ?? ''

    if (!inputValue.value.trim()) {
        cancelPendingSearch()
        internalResults.value = null
        showFeaturedOrClose()
        return
    }

    openPopover()

    const cached = getCachedResponse(inputValue.value)
    if (cached) {
        cancelPendingSearch()
        internalResults.value = cached.results ?? null
        searchLogUlid.value = cached.search_log_ulid ?? null
        return
    }

    isInternalLoading.value = true
    fetchResults(inputValue.value)
}

// SearchResultCatalogue sets open=false when a result is clicked
watch(showDropdown, (open) => {
    if (!open) {
        closePopover()
    }
})

const visitSearchPage = () => {
    if (inputValue.value) {
        if (route().current()?.startsWith('iris.')) {
            router.get(`/search?q=${encodeURIComponent(inputValue.value)}`)
        } else {
            window.location.href = `/search?q=${encodeURIComponent(inputValue.value)}`
        }
    } else {
        notify({
            title: trans("Something went wrong"),
            text: trans("The query must be filled"),
            type: "error",
        })
    }
}
</script>

<template>
    <div class="w-full relative group">
        <input
            ref="inputRef"
            :value="inputValue"
            @input="onSearchInput"
            @focus="onFocus"
            @mouseup="onMouseUp"
            @blur="onBlur"
            class="h-12 min-w-28 focus:border-transparent focus:ring-2 focus:ring-gray-700 w-full md:min-w-0 md:w-full rounded-full border border-[#d1d5db] disabled:bg-gray-200 disabled:cursor-not-allowed pl-10"
            :id="id || 'inputIrisSearch'"
            :placeholder="fieldValueSearch?.placeholder ?? trans('Search')"
            @keydown.enter="() => visitSearchPage()"
        />
        <FontAwesomeIcon icon="far fa-search" class="group-focus-within:text-gray-700 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" fixed-width aria-hidden="true" />

        <!-- Catalogue results in a centered popover with an arrow to the input -->
        <Popover
            ref="popoverRef"
            appendTo="body"
            :dismissable="true"
            class="iris-search-popover !w-[90vw] -translate-x-1/2"
            style="left: 50%"
            @show="onPopoverShow"
            @hide="onPopoverHide"
        >
            <div v-if="isShowingFeatured" class="max-h-[70vh] overflow-y-auto">
                <SearchFeatured
                    v-model:open="showDropdown"
                    :results="featuredResults"
                    :is-loading="isFeaturedLoading"
                />
            </div>
            <div v-else class="h-[70vh] max-h-[550px] overflow-hidden">
                <SearchResultCatalogue
                    v-model:open="showDropdown"
                    :results="internalResults"
                    :is-loading="isInternalLoading"
                    :query="inputValue"
                    :search-log-ulid="searchLogUlid"
                />
            </div>
        </Popover>
    </div>
</template>

<style lang="scss">
/* Search popover: full-width panel pinned to the viewport */
.iris-search-popover.p-popover {
    max-width: none !important;
}

/* Caret: an accent diamond aimed at the input center (--caret-left is set in JS on show) */
.iris-search-popover.p-popover::before {
    display: none !important;
}

.iris-search-popover.p-popover::after {
    content: "" !important;
    position: absolute !important;
    top: -5px !important;
    left: var(--caret-left, 50%) !important;
    width: 12px !important;
    height: 12px !important;
    margin-left: -6px !important;
    background: var(--theme-color-0) !important;
    border: none !important;
    border-radius: 2px 0 0 0 !important;
    box-shadow: none !important;
    transform: rotate(45deg) !important;
}

.iris-search-popover .p-popover-content {
    padding: 0 !important;
    overflow: hidden !important;
    border-radius: inherit;
}
</style>
