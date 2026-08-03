<script setup lang="ts">
import { ref, watch, onBeforeMount, onMounted, onBeforeUnmount, defineAsyncComponent, nextTick } from "vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { debounce } from "lodash-es"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch, faChevronUp, faChevronDown } from "@far"
import { faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { searchRoute } from "@/Iris/Composables/useSearchRoute"
import { useIrisSearchMobile } from "@/Iris/Composables/useIrisSearchMobile"
library.add(faSearch, faTimes, faChevronUp, faChevronDown)

const SearchResultCatalogueMobile = defineAsyncComponent(() => import("@/Iris/Components/SearchResultCatalogueMobile.vue"))

defineProps<{
    id: string
}>()

const inputValue = ref('')

onBeforeMount(() => {
    const params = new URLSearchParams(window.location.search)
    const q = params.get('q')
    if (q) inputValue.value = q
})

const internalResults = ref<any>(null)
const searchLogUlid = ref<string | null>(null)
const isInternalLoading = ref(false)
// Shared so other components (the sidebar search field) open the very same overlay
const { isIrisSearchMobileOpen: isOverlayOpen } = useIrisSearchMobile()
const showDropdown = ref(true)
const inputRef = ref<HTMLInputElement | null>(null)
let internalAbort: AbortController | null = null
let internalRequestId = 0

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

onBeforeUnmount(() => {
    isOverlayOpen.value = false
    document.body.style.overflow = ''
    clearDragHintTimer()
})

// The floating button can be dragged vertically to uncover content beneath it;
// the position sticks per device
const FAB_POSITION_KEY = 'iris-search-fab-bottom'
const fabBottom = ref<number | null>(null)
let dragStartY = 0
let dragStartBottom = 0
let dragMoved = false

const clampFabBottom = (value: number): number =>
    Math.min(Math.max(value, 16), window.innerHeight - 140)

onBeforeMount(() => {
    const saved = Number(localStorage.getItem(FAB_POSITION_KEY))
    if (saved) {
        fabBottom.value = saved
    }
})

const onFabTouchStart = (event: TouchEvent) => {
    dragMoved = false
    dragStartY = event.touches[0].clientY
    dragStartBottom = fabBottom.value ?? clampFabBottom(88)
}

const onFabTouchMove = (event: TouchEvent) => {
    const delta = dragStartY - event.touches[0].clientY
    if (!dragMoved && Math.abs(delta) < 8) {
        return
    }
    dragMoved = true
    fabBottom.value = clampFabBottom(dragStartBottom + delta)
}

const onFabTouchEnd = () => {
    if (dragMoved && fabBottom.value !== null) {
        localStorage.setItem(FAB_POSITION_KEY, String(Math.round(fabBottom.value)))
    }
}

const onFabClick = () => {
    if (dragMoved) {
        dragMoved = false
        return
    }
    openOverlay()
}

// Touching the floating button reveals that it can be moved: most people never
// discover the drag gesture on their own. It shows on press rather than on a
// long press, so the arrows are already there by the time the finger moves
const DRAG_HINT_LINGER_MS = 900
const isDragHintVisible = ref(false)
let dragHintHideTimer: ReturnType<typeof setTimeout> | null = null

const clearDragHintTimer = () => {
    if (dragHintHideTimer !== null) {
        clearTimeout(dragHintHideTimer)
        dragHintHideTimer = null
    }
}

const showDragHint = () => {
    clearDragHintTimer()
    isDragHintVisible.value = true
}

const hideDragHintSoon = () => {
    clearDragHintTimer()
    dragHintHideTimer = setTimeout(() => {
        dragHintHideTimer = null
        isDragHintVisible.value = false
    }, DRAG_HINT_LINGER_MS)
}

const openOverlay = () => {
    isOverlayOpen.value = true
}

const closeOverlay = () => {
    isOverlayOpen.value = false
}

watch(isOverlayOpen, (open) => {
    if (open) {
        showDropdown.value = true
        document.body.style.overflow = 'hidden'
        nextTick(() => inputRef.value?.focus())
        if (inputValue.value.trim() && !internalResults.value) {
            isInternalLoading.value = true
            fetchResults(inputValue.value)
        }
        return
    }

    document.body.style.overflow = ''
    fetchResults.cancel()
    internalAbort?.abort()
    isInternalLoading.value = false
})

const fetchResults = debounce(async (query: string) => {
    const requestId = ++internalRequestId
    internalAbort?.abort()
    internalAbort = new AbortController()
    isInternalLoading.value = true
    try {
        const { data } = await axios.get(
            route(searchRoute('catalogue'), { q: query }),
            { signal: internalAbort.signal }
        )
        if (requestId !== internalRequestId) {
            return
        }
        cacheResponse(query, data)
        internalResults.value = data.results ?? null
        searchLogUlid.value = data.search_log_ulid ?? null
    } catch (error) {
        if (axios.isCancel(error) || requestId !== internalRequestId) {
            return
        }
        internalResults.value = null
    } finally {
        if (requestId === internalRequestId) {
            isInternalLoading.value = false
        }
    }
}, 250)

// The overlay reopens holding the previous query; selecting it on focus means the next
// keystroke replaces it instead of being prepended to it (which searched the concatenation)
let isFocusSelection = false

const onInputFocus = (event: FocusEvent) => {
    isFocusSelection = true;
    (event.target as HTMLInputElement)?.select()
}

// The mouseup of the click that focused the field would otherwise collapse that selection
const onInputMouseUp = (event: MouseEvent) => {
    if (isFocusSelection) {
        event.preventDefault()
        isFocusSelection = false
    }
}

const onInputBlur = () => {
    isFocusSelection = false
}

const onSearchInput = (event: Event) => {
    inputValue.value = (event.target as HTMLInputElement)?.value ?? ''

    if (!inputValue.value.trim()) {
        fetchResults.cancel()
        internalResults.value = null
        return
    }

    const cached = getCachedResponse(inputValue.value)
    if (cached) {
        internalRequestId++
        fetchResults.cancel()
        internalAbort?.abort()
        isInternalLoading.value = false
        internalResults.value = cached.results ?? null
        searchLogUlid.value = cached.search_log_ulid ?? null
        return
    }

    isInternalLoading.value = true
    fetchResults(inputValue.value)
}

// SearchResultCatalogueMobile sets open=false when a result is clicked
watch(showDropdown, (open) => {
    if (!open) {
        closeOverlay()
    }
})

const visitSearchPage = () => {
    if (inputValue.value) {
        closeOverlay()
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
    <div>
        <button
            type="button"
            :id="id || 'inputIrisSearchMobile'"
            :aria-label="ctrans('Search')"
            class="ml-1 xw-14 xh-14 rounded-full flex items-center justify-center touch-none"
            xstyle="fabBottom !== null ? { bottom: `${fabBottom}px` } : undefined"
            @click="onFabClick"
            @touchstart.passive="onFabTouchStart"
            @touchmove.prevent="onFabTouchMove"
            @touchend="onFabTouchEnd"
        >
            <FontAwesomeIcon icon="far fa-search" class="text-3xl" fixed-width aria-hidden="true" />
        </button>

    <Teleport to="body">
        <!-- Always-present floating search button in the thumb zone; drag it up or down -->
        <div
            v-if="!isOverlayOpen"
            class="fixed right-4 bottom-[calc(env(safe-area-inset-bottom)+5rem)] z-40 w-14 h-14"
            :style="fabBottom !== null ? { bottom: `${fabBottom}px` } : undefined"
        >
            <Transition
                enter-active-class="transition-opacity duration-75"
                enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-300"
                leave-to-class="opacity-0"
            >
                <div v-if="isDragHintVisible" class="pointer-events-none absolute inset-0" aria-hidden="true">
                    <FontAwesomeIcon
                        icon="far fa-chevron-up"
                        class="dragHintArrowUp absolute left-1/2 -top-6 text-[var(--theme-color-0)]"
                    />
                    <FontAwesomeIcon
                        icon="far fa-chevron-down"
                        class="dragHintArrowDown absolute left-1/2 -bottom-6 text-[var(--theme-color-0)]"
                    />
                    <span class="absolute right-[calc(100%+0.75rem)] top-1/2 -translate-y-1/2 whitespace-nowrap rounded-md bg-gray-900/85 px-2 py-1 text-xs text-white shadow-lg">
                        {{ ctrans('Drag up or down to move') }}
                    </span>
                </div>
            </Transition>

            <button
                type="button"
                xid="id || 'inputIrisSearchMobile'"
                :aria-label="trans('Search')"
                class="w-14 h-14 rounded-full bg-[var(--theme-color-0)] text-[var(--theme-color-1)] shadow-lg flex items-center justify-center opacity-60 focus-visible:opacity-100 active:opacity-100 active:scale-95 transition-[opacity,transform] touch-none"
                @pointerdown="showDragHint"
                @pointerup="hideDragHintSoon"
                @pointercancel="hideDragHintSoon"
                @pointerleave="hideDragHintSoon"
                @click="onFabClick"
                @touchstart.passive="onFabTouchStart"
                @touchmove.prevent="onFabTouchMove"
                @touchend="onFabTouchEnd"
                @touchcancel="onFabTouchEnd"
            >
                <FontAwesomeIcon icon="far fa-search" class="text-xl" fixed-width aria-hidden="true" />
            </button>
        </div>

        <!-- Full-screen overlay: the input sits at the top, immune to the keyboard
             appearing/disappearing (a bottom-pinned input wobbles on iOS) -->
        <div v-if="isOverlayOpen" class="fixed inset-0 z-50 bg-white flex flex-col">
            <div class="relative shrink-0 flex items-center gap-1 p-2 border-b border-gray-200">
                <div class="relative flex-1">
                    <input
                        ref="inputRef"
                        :value="inputValue"
                        @input="onSearchInput"
                        @focus="onInputFocus"
                        @mouseup="onInputMouseUp"
                        @blur="onInputBlur"
                        class="h-11 w-full rounded-full border border-[#d1d5db] focus:border-transparent focus:ring-2 focus:ring-gray-700 pl-10"
                        :placeholder="trans('Search')"
                        autocapitalize="none"
                        autocorrect="off"
                        autocomplete="off"
                        spellcheck="false"
                        enterkeyhint="search"
                        @keydown.enter="() => visitSearchPage()"
                        @keydown.esc="closeOverlay"
                    />
                    <FontAwesomeIcon icon="far fa-search" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" fixed-width aria-hidden="true" />
                </div>
                <button
                    type="button"
                    class="shrink-0 w-11 h-11 flex items-center justify-center text-gray-500"
                    :aria-label="trans('Close')"
                    @click="closeOverlay"
                >
                    <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                </button>
            </div>

            <div class="flex-1 min-h-0">
                <SearchResultCatalogueMobile
                    v-if="inputValue.trim()"
                    v-model:open="showDropdown"
                    :results="internalResults"
                    :is-loading="isInternalLoading"
                    :query="inputValue"
                    :search-log-ulid="searchLogUlid"
                />
                <div v-else class="h-full flex items-center justify-center text-gray-400 text-sm px-8 text-center">
                    {{ trans('Start typing to search the catalogue') }}
                </div>
            </div>
        </div>
    </Teleport>
    </div>
</template>

<style scoped>
.dragHintArrowUp {
    animation: dragHintArrowUp 700ms ease-in-out infinite;
}

.dragHintArrowDown {
    animation: dragHintArrowDown 700ms ease-in-out infinite;
}

@keyframes dragHintArrowUp {
    0%, 100% {
        transform: translate(-50%, 0);
        opacity: 1;
    }
    50% {
        transform: translate(-50%, -5px);
        opacity: 0.5;
    }
}

@keyframes dragHintArrowDown {
    0%, 100% {
        transform: translate(-50%, 0);
        opacity: 1;
    }
    50% {
        transform: translate(-50%, 5px);
        opacity: 0.5;
    }
}

@media (prefers-reduced-motion: reduce) {
    .dragHintArrowUp,
    .dragHintArrowDown {
        animation: none;
        transform: translate(-50%, 0);
    }
}
</style>
