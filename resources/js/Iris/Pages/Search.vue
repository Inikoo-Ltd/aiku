<script setup lang="ts">
import { inject, onBeforeMount } from "vue"

import { irisLocaleStructure } from "@iris/Composables/useIrisLocaleStructure"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { trans } from "laravel-vue-i18n"
import { getStyles } from "@/Composables/styles"
import { onMounted } from "vue"


const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', irisLocaleStructure)

const props = defineProps<{
    web_block_family?: {}
}>()

console.log("PROP web_block_family", props.web_block_family);

// Init: Search result
const LBInitSearchResult = async () => {

    if (!layout.iris?.luigisbox_tracker_id) {
        console.error("Luigi tracker id didn't provided")
        return
    }

    const usedLocale = locale.language?.code ?? "en";
    
    const xxx = await Luigis.Search(
        {
            TrackerId: layout.iris?.luigisbox_tracker_id,
            Locale: usedLocale,
            PriceFilter: {
                decimals: 2,
                prefixed: true,
                symbol: locale.currencySymbol(layout.iris?.currency?.code),
            },
            TopItems: ['category:4', 'department:4', 'sub_department:4', 'collection:4', 'brand:4', 'tag:4'],
            Theme: "boo",
            Size: 15,
            Facets: [
                'category',
                'color',
                'news',
                'department',
                'sub_department',
                'brand',
                'collection',
                'tag',
                'price_amount',
            ],
            QuicksearchTypes: [
                'category:12',
                'color',
                'news',
                'department',
                'sub_department',
                'brand',
                'collection',
                'tag',
            ],
            DefaultFilters: {
                availability: 1,
                // stock_qty: '1|',  // Filter out of stock products
                
            },
            Translations: {
                [usedLocale]: {
                    "activeFilter": {
                        "remove": trans("Cancel"),
                    },
                    "activeFilters": {
                        "title": trans("Used filters"),
                        "cancelAllFilters": trans("Cancel all filters"),
                    },
                    "additionalResults": {
                        "title": trans("You may also like"),
                    },
                    "facet": {
                        "name": {
                            "category": trans("Categories"),
                            "department": trans("Departments"),
                            "sub_department": trans("Sub Departments"),
                            "brand": trans("Brands"),
                            "collection": trans("Collections"),
                            "tag": trans("Tags"),
                            "price_amount": trans("Price"),
                            "color": trans("Colors"),
                            "news": trans("News"),
                        },
                        "multichoice": {
                            "showMore": trans("More (:count)"),
                            "showLess": trans("Hide others"),
                        },
                    },
                    "facetDate": {
                        "smallerThan": trans("Before"),
                        "exactDay": trans("Exact day"),
                        "biggerThan": trans("After"),
                        "range": trans("From-To"),
                        "get": trans("get"),
                    },
                    "facetNumericRange": {
                        "from": trans("From"),
                        "to": trans("to"),
                        "histogramBucketTitle": trans(":count Products"),
                    },
                    "facets": {
                        "closeFilter": trans("Close"),
                    },
                    "loading": {
                        "isLoading": trans("Loading ..."),
                    },
                    "noResults": {
                        "noResults": trans("We couldn't find any suitable results"),
                    },
                    "pagination": {
                        "nextPage": trans("Load more"),
                    },
                    "quickSearch": {
                        "title": {
                            "category": trans("Categories"),
                            "department": trans("Departments"),
                            "sub_department": trans("Sub Departments"),
                            "brand": trans("Brands"),
                            "collection": trans("Collections"),
                            "tag": trans("Tags"),
                            "price_amount": trans("Price"),
                            "color": trans("Colors"),
                            "news": trans("News"),
                        },
                        "topItemTitle": {
                            "category": trans("Top categories"),
                            "brand": trans("Top brands"),
                        }
                    },
                    "resultDefault": {
                        "actionButton": trans("Detail"),
                        "availability": {
                            "0": trans("Unavailable"),
                        },
                        "result": trans("Result"),
                        "loginForPrices": trans("Login for prices"),
                    },
                    "search": {
                        "title": trans("Results for :query (:hitsCount)"),
                        "titleShort": trans("Search"),
                        "filter": trans("Filters"),
                        "queryUnderstanding": {
                            "title": trans("We detected the following filters"),
                            "cancel": trans("Repeat without automatic filter detection"),
                        }
                    },
                    "sort": {
                        "default": trans("Default"),
                        "price_amount:asc": trans("Price: Low to High"),
                        "price_amount:desc": trans("Price: High to Low"),
                        "headlineTitle": trans("Sort by") + ": ",
                    },
                    "site": {
                        "titleResults": trans("Results for :query (:hitsCount)"),
                        "queryCorrection": trans("We detected the following filters"),
                    },
                    "topItems": {
                        "category": trans("Categories"),
                        "department": trans("Departments"),
                        "sub_department": trans("Sub Departments"),
                        "brand": trans("Brands"),
                        "collection": trans("Collections"),
                        "tag": trans("Tags"),
                        "price_amount": trans("Price"),
                        "title": trans("You might be interested"),
                        "results": {
                            "title": trans("Top products"),
                        }
                    },
                }
            },
            UrlParamName: {
                QUERY: "q",
            },
            RemoveFields: layout.iris.is_logged_in ? null : ['price', 'formatted_price', 'price_amount'],
        },
        "#inputXxxLuigi",
        "#luigi_result_search"
    )

    // console.log("Init Search", xxx)
}

onBeforeMount(() => {
    const script = document.createElement('script');
    script.src = "https://cdn.luigisbox.tech/search.js";
    script.async = true;
    script.onload = () => {
        console.log('Search script loaded');
        LBInitSearchResult();
    };
    script.onerror = () => {
        console.error('Failed to load Luigi Search script');
    }
    document.head.appendChild(script);

})

const createCssClassFromStyleObject = (className: string, styleObj: Record<string, string>) => {
    const styleId = `style-${className}`

    // Avoid multiple inject with same name
    if (document.getElementById(styleId)) return

    const styleEl = document.createElement('style')
    styleEl.id = styleId

    const cssText = Object.entries(styleObj)
        .map(([key, value]) => {
        const kebab = key.replace(/[A-Z]/g, m => '-' + m.toLowerCase())
        return `${kebab}: ${value};`
        })
        .join(' ')

    styleEl.textContent = `.${className} { ${cssText} }`

    document.head.appendChild(styleEl)
}

onMounted(() => {
    
    createCssClassFromStyleObject('lb-product-card', getStyles(props.web_block_family.fieldValue?.card_product?.properties, 'desktop'))
    // createCssClassFromStyleObject('render-product-button-login', getStyles(props.web_block_family.fieldValue?.buttonLogin?.properties, 'desktop'))
})

// const inputValue = ref('')
console.log("layout", layout)
</script>
<template>
    <div class="xmd:py-16 w-full mx-auto px-8">

        <div class="md:mt-4 min-h-44" :style="{
            fontFamily: layout?.app?.webpage_layout?.container?.properties?.text?.fontFamily
        }">
            <div id="luigi_result_search" class="h-40 mb-4">
                <div class="flex gap-x-4 h-full">
                    <div class="w-96 skeleton rounded-md">
                    </div>
                    <div class="w-full skeleton rounded-md">
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style lang="scss">


// ===== Search Page: Result (start) ===== //
.lb-container {
    @apply box-border !important;
}
.lb-search__main {
    @apply md:pl-7 box-border lg:w-[77%] !important;
}

.lb-search__aside {
    @apply max-w-64 !important;
}

.lb-results {
    @apply p-0 !important;
    display: block !important;
}

.lb-product-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.5rem 1.5rem;
    padding: 0.75rem;

    @media (min-width: 768px) {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 2rem 2rem;
    }

    @media (min-width: 1024px) {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        column-gap: 2rem;
        row-gap: 2.5rem;
    }

    @media (min-width: 1280px) {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }
}

.lb-result {
    @apply box-border !important;
    width: auto !important;
    padding: 0 !important;
    display: flex !important;
    flex-direction: column !important;
}

.lb-product-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    background-color: #ffffff;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    transition: box-shadow 0.2s ease;
    position: relative;
    padding-bottom: 0.75rem;

    &:hover {
        text-decoration: none;
        color: inherit;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
}

.lb-product-image-wrap {
    position: relative;
    width: 100%;
    xheight: 180px;
    border-radius: 0.75rem;
    overflow: hidden;
    background-color: #f9fafb;
    flex-shrink: 0;
    margin-bottom: 0.25rem;

    @media (min-width: 640px) {
        vheight: 305px;
    }
}

.lb-product-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center;
}

.lb-product-image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0.2;
    color: #9ca3af;

    svg {
        width: 3rem;
        height: 3rem;
    }
}

.lb-oos-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    z-index: 10;
}

.lb-oos-label {
    width: 100%;
    background: rgba(255, 255, 255, 0.95);
    color: #111827;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 4px 0;
    text-align: center;
    backdrop-filter: blur(2px);
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}

.lb-product-info {
    padding: 0 0.75rem;
    display: flex;
    flex-direction: column;
    flex: 1;
    gap: 4px;
}

.lb-product-title {
    font-weight: 600;
    font-size: 0.875rem;
    line-height: 1.4;
    color: #111827;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.lb-product-code {
    font-size: 0.75rem;
    color: #6b7280;
}

.lb-product-stock {
    font-size: 0.75rem;
    font-weight: 600;

    &.lb-product-stock--in {
        color: #15803d;
    }

    &.lb-product-stock--out {
        color: #dc2626;
    }
}

.lb-product-footer {
    margin-top: auto;
    padding-top: 4px;
}

.lb-product-price {
    font-size: 0.9rem;
    font-weight: 800;
    color: #111827;
    display: block;
}

.lb-product-login-btn {
    font-size: 0.7rem;
    color: var(--iris-color-0, #4f46e5);
    text-decoration: underline;
    display: block;
}

.lb-search .lb-search__container {
    @apply justify-center !important;
}
// ===== Search Page: Result (end) ===== //

// Quick Search Type //
.lb-quick-searches {
    @apply mt-0 !important;
}
.lb-quick-searches__headings {
    @apply gap-x-3 gap-y-2 mb-2.5 pb-3 !important;
}

.lb-quick-searches__heading {
    @apply m-0 flex-grow text-center max-w-64 px-2 py-1 text-base rounded !important;
    border: 1px solid color-mix(in srgb, var(--iris-color-0) 40%, transparent) !important;
}

.lb-quick-searches__heading--active {
    background-color: color-mix(in srgb, var(--iris-color-0) 10%, transparent) !important;
}

.lb-quick-search-default__item-image-wrapper {
    @apply w-[40px] md:w-[70px] !important;
}
.lb-quick-search-default__item-image {
    @apply overflow-hidden !important;
}
.lb-quick-search-default__list {
    @apply grid grid-cols-2 lg:grid-cols-4 gap-x-2 !important;
}
.lb-quick-search-default__item {
    @apply w-full  !important;
}
// End: Quick Search Type //

.lb-checkbox label.lb-facet__label {
    position: static !important;
}

.lb-search {
    font-family: v-bind('layout?.app?.webpage_layout?.container?.properties?.text?.fontFamily') !important;
}

.lb-search-text-color-primary {
    color: var(--luigiColor3) !important;
}

.lb-result__title {
    margin-bottom: 1px !important;
}

.lb-search .lb-search__aside.is-active {
    padding: 70px 20px 100px 20px !important;
}

.lb-search .lb-search__close-filter {
    top: -47px !important;
}

.lb-search .lb-checkbox {
    padding: 1px 0 1px 10px !important;
}

.lb-search .lb-checkbox__text {
    padding-top: 1.5px !important;
    margin-left: 15px !important;
}

.lb-search .lb-result__description {
    text-align: justify !important;
    display: -webkit-box !important;
    -webkit-box-orient: vertical !important;
    -webkit-line-clamp: 3 !important;
    line-clamp: 3 !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    margin-bottom: 10px !important;
}

.lb-result__actions {
    display: flex !important;
    place-items: center !important;
    justify-content: space-between !important;
    row-gap: 5px !important;
}

.lb-result__prices {
    flex-grow: 1 !important;
    margin-bottom: 15px !important;
}

.lb-result__price {
    display: flex !important;
    place-content: center !important;
    text-align: center !important;
    color: var(--luigiColor3) !important;
}

.lb-result__action-buttons {
    flex-grow: 1 !important;
}


.lb-search .lb-result__action-item {
    width: 100% !important;
    margin: 0px !important
}

.lb-search-text-color-primary-clickable {
    color: var(--luigiColor1) !important;
}

.lb-search-bg-color-primary-clickable {
    background: transparent !important;
    color: var(--luigiColor1) !important;
    border: 1px solid var(--luigiColor1) !important;
    border-radius: 4px !important;
}

.lb-search-bg-color-primary-clickable:hover {
    background: color-mix(in srgb, var(--luigiColor1) 20%, transparent) !important;
    
}

@media only screen and (max-width: 640px) {
    .lb-search .lb-checkbox__text {
        margin-left: 25px !important;
    }
}
</style>