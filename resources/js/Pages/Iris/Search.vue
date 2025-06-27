<script setup lang="ts">
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { computed, inject, onBeforeMount, ref } from "vue"

const layout = inject('layout', layoutStructure)
const isLogin = computed(() => {
    return layout.is_logged_in
})

// Init: Search result
const LBInitSearchResult = async () => {
    // console.log('layout.iris.luigisbox_tracker_id:', layout.iris?.luigisbox_tracker_id)

    if (!layout.iris?.luigisbox_tracker_id) {
        console.error("Luigi tracker id didn't provided")
        return
    }

    await Luigis.Search(
        {
            TrackerId: layout.iris?.luigisbox_tracker_id,
            Locale: 'en',
            PriceFilter: {
                decimals: 2,
                prefixed: true,
                symbol: '£',
            },
            Theme: "boo",
            Size: 12,
            Facets: ['brand', 'category', 'color'],
            DefaultFilters: {
                type: 'item'
            },
            UrlParamName: {
                QUERY: "q",
            },
            RemoveFields: isLogin.value ? ['price', 'price_amount'] : ['price', 'formatted_price', 'price_amount'],
        },
        "#inputXxxLuigi",
        "#luigi_result_search"
    )

    console.log("Init Search")
}

onBeforeMount(() => {
    const script = document.createElement('script');
    script.src = "https://cdn.luigisbox.com/search.js";
    script.async = true;
    script.onload = () => {
        console.log('Luigi autocomplete script loaded');
        LBInitSearchResult();
    };
    script.onerror = () => {
        console.error('Failed to load Luigi autocomplete script');
    }
    document.head.appendChild(script);
})

const inputValue = ref('')
</script>

<template>
    <div class="py-16 w-full max-w-6xl mx-auto">
        <template v-if="layout?.app?.environment === 'local'">
            <input v-model="inputValue" class="block w-full max-w-lg mx-auto" id="inputXxxLuigi" style="border: 1px solid #d1d5db; border-radius: 7px;height: 45px;padding-left: 10px;" placeholder="Search"/>
            
            <div id="luigi_result_search" class="">
            </div>
        </template>
    </div>
</template>

<style>
.lb-checkbox label.lb-facet__label {
    position: static !important;
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
    color: var(--luigiColor2) !important;
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