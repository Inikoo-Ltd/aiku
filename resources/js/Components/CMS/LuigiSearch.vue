<script setup lang="ts">
import { inject, onMounted, ref, computed, watch, onBeforeMount, defineAsyncComponent } from "vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { debounce } from "lodash-es"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { LuigiTranslation } from "@/Composables/Unique/LuigiTranslation"
import { loadLuigiAutocomplete, onFirstInteractionOrIdle } from "@/Composables/useLuigiAutocomplete"
import Popover from "primevue/popover"
// import { AutoComplete } from "primevue"   /// No need to import AutoComplete
library.add(faSearch)

const SearchResultCatalogue = defineAsyncComponent(() => import("@/Iris/Components/SearchResultCatalogue.vue"))



const props = defineProps<{
    id: string
    fieldValueSearch?: {
        placeholder?: string
    }
}>()

const inputValue = ref('')

onBeforeMount(() => {
    // Read query param 'q' from URL if present
    const params = new URLSearchParams(window.location.search)
    const q = params.get('q')
    if (q) inputValue.value = q
})


const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)

// Section: Internal catalogue search (used when website search model is 'internal')
const isInternalSearch = computed(() => layout.iris?.iris_search_model === 'internal')
const internalResults = ref<any>(null)
const isInternalLoading = ref(false)
const showDropdown = ref(false)
const inputRef = ref<HTMLInputElement | null>(null)
const popoverRef = ref<InstanceType<typeof Popover> | null>(null)
const isPopoverVisible = ref(false)
let internalAbort: AbortController | null = null
let internalRequestId = 0

const openInternalPopover = () => {
    if (isPopoverVisible.value || !inputRef.value) {
        return
    }
    popoverRef.value?.show({ currentTarget: inputRef.value } as any)
}

const closeInternalPopover = () => {
    popoverRef.value?.hide()
}

const fetchInternalResults = debounce(async (query: string) => {
    const requestId = ++internalRequestId
    internalAbort?.abort()
    internalAbort = new AbortController()
    isInternalLoading.value = true
    try {
        const { data } = await axios.get(
            route('iris.json.search.catalogue', { q: query }),
            { signal: internalAbort.signal }
        )
        if (requestId !== internalRequestId) {
            return
        }
        internalResults.value = data.results ?? null
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

const onInternalInput = () => {
    if (!inputValue.value.trim()) {
        fetchInternalResults.cancel()
        internalResults.value = null
        closeInternalPopover()
        return
    }
    openInternalPopover()
    isInternalLoading.value = true
    fetchInternalResults(inputValue.value)
}

// SearchResultCatalogue sets open=false when a result is clicked
watch(showDropdown, (open) => {
    if (!open) {
        closeInternalPopover()
    }
})

const onSearchInput = (event: Event) => {
    inputValue.value = (event.target as HTMLInputElement)?.value ?? ''
    if (isInternalSearch.value) {
        onInternalInput()
    }
}


const LBInitAutocompleteNew = async () => {
    // console.log('layout.iris.luigisbox_tracker_id:', layout.iris?.luigisbox_tracker_id)

    if (!layout.iris?.luigisbox_tracker_id) {
        console.error("Luigi tracker id didn't provided")
        return
    }

    const xxx = await AutoComplete(
        {
            Layout: "heromobile",
            // TrackerId: '483878-588294',
            TrackerId: layout.iris?.luigisbox_tracker_id,
            // Locale: layout.iris?.website_i18n?.current_language?.code || 'en',
            PriceFilter: {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
                locale: locale.language.code,
                prefixed: true,
                symbol: locale.currencySymbol(layout.iris?.currency?.code)
            },
            Width: '100vw',
            CloseWhenQueryIsEmpty: false,
            Translations: LuigiTranslation,
            RemoveFields: layout.iris.is_logged_in ? [] : ['formatted_price', 'price_amount', 'price'],
            Types: [
                {
                    name: trans("Products"),
                    heroName: trans("Top product"),
                    type: "item",
                    size: 10,
                    defaultFilters: {
                        availability: 1,
                        // stock_qty: '1|',  // Filter out of stock products
                    },
                    recommend: {
                        heroName: trans('Top product'),
                        name: trans('Top products'),
                    }
                },
                {
                    type: "query",
                    name: trans("Queries"),
                    recommend: {
                        name: trans('Top searches'),
                        size: 2,
                    }
                },
                {
                    type: "category",
                    name: trans("Categories"),
                    recommend: {
                        name: trans('Top categories'),
                    }
                },
                {
                    name: trans("Articles"),
                    type: "news",
                },
                {
                    name: trans("Departments"),
                    type: "department",
                },
                {
                    name: trans("Sub Departments"),
                    type: "sub_department",
                },
                {
                    name: trans("Brands"),
                    type: "brand",
                },
                {
                    name: trans("Collections"),
                    type: "collection",
                },
                {
                    name: trans("Tags"),
                    type: "tag",
                },
            ],
            ShowAllTitle: trans('View all results'), // Show All Product: Button label
            ShowAllCallback: (q) => {  // Called when 'Show All Product' clicked
                visitSearchPage()
            },
            ShowBuyTitle: trans('Detail'), // Top Product: Button label
            Actions: [  // Action for Top Product 'Add To Basket'
                {
                    forRow: function(row) {
                        return (
                            row['data-autocomplete-id'] == 1 &&
                            row.type === 'item'
                        )
                    },
                    // iconUrl: 'https://cdn-icons-png.freepik.com/256/275/275790.png',
                    iconText: '➔',
                    // title: "Visit product's page",
                    action: function(e, result) {
                        // console.log('zzzzzzzzz', e, result)
                        window.location.href = result?.attributes?.web_url?.[0]
                        // router.visit(result.attributes.web_url[0])

                    }
                }
            ]
        },
        `#${props.id || 'inputLuigi'}`
    )

    console.log(`Init autocomplete: ${props.id}`)
}





onMounted(() => {
    if (isInternalSearch.value) {
        return
    }

    onFirstInteractionOrIdle(() => {
        loadLuigiAutocomplete()
            .then(() => LBInitAutocompleteNew())
            .catch(() => console.error('Failed to load Luigi autocomplete script'))
    })
})

const visitSearchPage = () => {
    console.log('vzzzisit', inputValue.value)
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
            @focus="() => { if (isInternalSearch && inputValue.trim()) openInternalPopover() }"
            afocus="(q) => getTopItemsSuggestions()"
            xdisabled
            class="h-12 min-w-28 focus:border-transparent focus:ring-2 focus:ring-gray-700 w-full md:min-w-0 md:w-full rounded-full border border-[#d1d5db] disabled:bg-gray-200 disabled:cursor-not-allowed pl-10"
            :id="id || 'inputLuigi'"
            xstyle="height: 35px"
            :placeholder="fieldValueSearch?.placeholder ?? trans('Search')"
            @keydown.enter="() => visitSearchPage()"
        />
        <FontAwesomeIcon icon="far fa-search" class="group-focus-within:text-gray-700 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" fixed-width aria-hidden="true" />

        <!-- Internal search: catalogue results in a centered popover with an arrow to the input -->
        <Popover
            v-if="isInternalSearch"
            ref="popoverRef"
            appendTo="body"
            :dismissable="true"
            class="luigi-internal-search-popover"
            @show="() => { isPopoverVisible = true; showDropdown = true }"
            @hide="() => { isPopoverVisible = false; showDropdown = false }"
        >
            <div class="h-[70vh] max-h-[700px] w-full overflow-hidden">
                <SearchResultCatalogue
                    v-model:open="showDropdown"
                    :results="internalResults"
                    :is-loading="isInternalLoading"
                    :query="inputValue"
                />
            </div>
        </Popover>
    </div>
</template>

<style lang="scss">

/* Internal search popover: wide (90vw) panel; PrimeVue keeps its arrow pointing at the input */
.luigi-internal-search-popover.p-popover {
    width: 90vw !important;
    max-width: 1100px !important;
}

.luigi-internal-search-popover .p-popover-content {
    padding: 0 !important;
    overflow: hidden !important;
    border-radius: inherit;
}

.luigi-ac-heromobile-input { // Input on mobile
    @apply border border-[var(--theme-color-0)] focus:border-[var(--theme-color-0)] focus:ring-[var(--theme-color-0)] rounded-sm !important;
}

.luigi-ac-ribbon {
    /* Border top of the Autocomplete */
    background: var(--theme-color-0) !important;
}


/* Styling for Layout: Hero */
.luigi-ac-hero-color {
    background: var(--theme-color-0) !important;
    color: var(--theme-color-1) !important;
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
  /*  padding-left: 0px !important; */
    padding-bottom: 2px !important;
}

@media (max-width: 1020px) {
    .luigi-ac-button-block--show-all .luigi-ac-button {
        padding-bottom: 18px !important;
    }
}

.luigi-ac-others {
    background: #F3F7FA !important;
    overflow-y: auto !important;
}
.luigi-ac-header {
    color: var(--theme-color-0) !important;
    font-size: 1.2rem !important;
    font-weight: bold !important;
}
.luigi-ac-highlight {
    background: color-mix(in srgb, var(--theme-color-0) 90%, transparent) !important;
    border-radius: 2px !important;
    color: var(--theme-color-1) !important;
    font-weight: normal !important;
    padding-left: 2px !important;
    padding-right: 2px !important;
}

.luigi-ac-item {
    padding-top: 5px !important;
    padding-bottom: 5px !important;
}

.luigi-ac-item.active, .luigi-ac-active {
    background: #F3F7FA !important;
}

// Main slots
.luigi-ac-item:hover {
    background: color-mix(in srgb, var(--theme-color-0) 20%, var(--theme-color-1)) !important;
}

// Side slot (queries, etc)
.luigi-ac-other:hover {
    background: color-mix(in srgb, var(--theme-color-0) 80%, var(--theme-color-1)) !important;
}
/* End of styling for Layout: Hero */


.luigi-ac-button-buy {
    background: var(--theme-color-0) !important;
    border-radius: 5px;
}

.luigi-ac-button-buy:hover {
    background: color-mix(in srgb, var(--theme-color-0) 75%, var(--theme-color-1)) !important;
}


.luigi-ac-button {
    background: transparent !important;
    transition: background 0.05s !important;
    border-radius: 5px !important;
    border: 1px solid var(--theme-color-0) !important;
    color: var(--theme-color-0) !important;
}

.luigi-ac-button:hover {
    background: color-mix(in srgb, var(--theme-color-0) 10%, transparent) !important;
}

.luigi-ac-heromobile .luigi-ac-first-main .luigi-ac-text {
    padding-top: 0px !important;
}

.luigi-ac-heromobile .luigi-ac-name {
    height: fit-content !important;
}

/* Copyright */
.luigi-ac-footer {
    visibility: hidden !important;
}


.luigi-ac-heromobile .luigi-ac-first-main .luigi-ac-item .luigi-ac-attrs {
    overflow: visible !important;
}

.luigi-ac-no-result {
    color: var(--theme-color-0) !important;
}

// .luigi-ac-queries {
//     line-height: 0px !important;
// }

/* .luigi-ac-query .luigi-ac-other-content {
    color: #fff !important;
}
*/


/* Top Product styling (luigi-ac-first-main) */
.luigi-ac-first-main .luigi-ac-attr--formatted_price {
    margin-top: 5px;
    font-size: 1.05rem !important;
    display: block !important;
    color: var(--theme-color-0) !important;
}

.luigi-ac-first-main .luigi-ac-attr--description {
    text-align: justify !important;
    display: -webkit-box !important;
    margin-top: 5px !important;
    font-size: 0.7rem !important;
    -webkit-line-clamp: 4 !important;
    line-clamp: 4 !important;  /* This will not work in most browsers but included for future compatibility */
    -webkit-box-orient: vertical !important;
    overflow: hidden !important;
}

.luigi-ac-first-main .luigi-ac-name {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2 !important;
    line-clamp: 2 !important;
}

.luigi-ac-first-main .luigi-ac-button-buy {
    padding: 6px 20px !important;
}

.luigi-ac-heromobile .luigi-ac-first-main .luigi-ac-item .luigi-ac-attrs {
    max-height: 900px !important;
    display: block !important;
}

.luigi-ac-heromobile .luigi-ac-first-main .luigi-ac-item {
    padding: .4em 0.8em !important;
}


/* Products styling */
.luigi-ac-rest-main .luigi-ac-attrs {
    -webkit-line-clamp: 3 !important;
    line-clamp: 3 !important;
    max-height: 60px !important;
}

.luigi-ac-rest-main .luigi-ac-attr--formatted_price {
    display: block !important;
    color: var(--theme-color-0) !important;
}

.luigi-ac-heromobile-action-for-mobile  {
    @apply md:!hidden;
}

@media (min-width: 1021px) {
    .luigi-ac-heromobile .luigi-ac-others {
        width: 27%;
        max-width: 400px;
    }
}

.luigi-ac-heromobile .luigi-ac-action-primary {
    @apply left-1/2 -translate-x-1/2 !important;
}

.luigi-ac-heromobile .luigi-ac-main .luigi-ac-first-main {
    @apply max-w-md !important;
}

@media (min-width: 1021px) {
    .luigi-ac-heromobile .luigi-ac-main, .luigi-ac-heromobile .luigi-ac-products {
        @apply flex-grow !important;
    }
}

.luigi-ac-heromobile .luigi-ac-main .luigi-ac-rest-main {
    @apply flex-grow !important;
}

@media (min-width: 1021px) {
    .luigi-ac-heromobile .luigi-ac-rest-main .luigi-ac-other, .luigi-ac-heromobile .luigi-ac-rest-main .luigi-ac-product {
        @apply w-[33%]  !important;
    }
}


/* ====================================== Search result */


</style>