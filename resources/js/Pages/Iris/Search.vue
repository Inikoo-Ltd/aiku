<script setup lang="ts">
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { computed, inject, onBeforeMount, ref } from "vue"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTools } from '@fas'
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"


const layout = inject('layout')
const locale = inject('locale', aikuLocaleStructure)

// Init: Search result
const LBInitSearchResult = async () => {

    if (!layout.iris?.luigisbox_tracker_id) {
        console.error("Luigi tracker id didn't provided")
        return
    }
    const xxx = await Luigis.Search(
        {
            TrackerId: layout.iris?.luigisbox_tracker_id,
            Locale: 'en',
            PriceFilter: {
                decimals: 2,
                prefixed: true,
                symbol: locale.currencySymbol(layout.iris?.currency?.code),
            },
            Theme: "boo",
            Size: 12,
            Facets: [
                'category',
                'color',
                'news',
                'department',
                'sub_department',
                'brand',
                'collection',
                'tag',
            ],
            QuicksearchTypes: [
                'category',
                'color',
                'news',
                'department',
                'sub_department',
                'brand',
                'collection',
                'tag',
            ],
            Translations: {
                "en": {
                    "facet": {
                        "name": {
                            "category": "Categories",
                            "brand": "Brands",
                            "sub_department": "Sub Departments",
                            "tag": "Tags",
                            "department": "Departments",
                            "collection": "Collections"
                        }
                    },
                    "quickSearch": {
                        "title": {
                            "category": "Categories",
                            "department": "Departments",
                            "sub_department": "Sub Departments",
                            "tag": "Tags",
                            "color": "Color",
                            "news": "News",
                            "brand": "Brands",
                            "collection": "Collections"

                        }
                    },
                }
            },
            // DefaultFilters: {
            //     type: 'item'
            // },
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
    script.src = "https://cdn.luigisbox.com/search.js";
    script.async = true;
    script.onload = () => {
        console.log('Luigi Search script loaded');
        LBInitSearchResult();
    };
    script.onerror = () => {
        console.error('Failed to load Luigi Search script');
    }
    document.head.appendChild(script);
})

// const inputValue = ref('')
console.log("layout", layout)
</script>

<template>
    <div class="xmd:py-16 w-full mx-auto px-8">
        <!-- <input v-model="inputValue" class="block w-full max-w-lg mx-auto" id="inputXxxLuigi" style="border: 1px solid #d1d5db; border-radius: 7px;height: 45px;padding-left: 10px;" placeholder="Search"/> -->
        
        <div class="md:mt-4" :style="{
            fontFamily: layout?.app?.webpage_layout?.container?.properties?.text?.fontFamily
        }">
            <div id="luigi_result_search" class="h-40">
                <div class="flex gap-x-4 h-full">
                    <div class="w-96 skeleton">
                    </div>
                    <div class="w-full skeleton">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
     <!-- <div class="flex items-center justify-center min-h-[60vh] text-center px-4">
    <div class="max-w-xl">
      <div class="text-6xl mb-6" :style="{ color: layout?.app?.theme[4] }">
        <FontAwesomeIcon :icon="faTools" />
        </div>
     <h1 class="text-3xl font-bold text-gray-800 mb-4">
        Work in Progress
      </h1>
      <p class="text-lg text-gray-600">
        This feature will be ready by tomorrow. Thank you for your patience 🙏
      </p>
    </div>
  </div> -->
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
    @apply grid grid-cols-2 md:flex gap-x-0 gap-y-4 !important;
}

// .lb-search .lb-result {
//     @apply  !important;
// }

.lb-result {
    @apply box-border px-4 w-full md:w-1/2 lg:w-1/4 xl:w-[20%] !important;
}

.lb-search .lb-result__aside {
    @apply h-[100px] md:h-[125px] aspect-square !important;
}

.lb-result__image {
    @apply object-contain h-full w-full !important;
}

.lb-search .lb-result__image-wrapper {
    @apply w-full aspect-square !important; 
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