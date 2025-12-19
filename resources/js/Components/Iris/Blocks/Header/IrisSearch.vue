<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'

// import 'https://cdn.luigisbox.tech/autocomplete.js'  // For autocomplete

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSearch } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faSearch)

const luigiTrackerId = import.meta.env.VITE_LUIGI_TRACKER_ID
const defaultTrackerId = '179075-204259'

const searchValue = ref('')
const _inputRef = ref(null)

// const listFieldsRemoved = ['price', 'formatted_price', 'price_amount']
const listFieldsRemoved = ['price']

// const LBInitAutocomplete = () => {
//     AutoComplete({
//         Layout: 'heromobile',
//         TrackerId: luigiTrackerId,
//         Locale: 'en',
//         Translations: {
//             en: {
//                 showBuyTitle: 'Shop Today', // Top Product: Button label
//                 priceFilter: {
//                     minimumFractionDigits: 0,
//                     maximumFractionDigits: 2,
//                     locale: 'en',
//                     prefixed: true,
//                     symbol: '£'
//                 }
//             }
//         },
//         RemoveFields: listFieldsRemoved,
//         Types: [
//             {
//                 name: "Item",
//                 type: "item",
//                 size: 7,  // Item list will appear 7 items
//                 // attributes: ['product_code', 'description', 'formatted_price.last'],
//                 attributes: ['product_code', 'formatted_price'],
//             },
//             {
//                 name: "Query",
//                 type: "query",
//             },
//             {
//                 name: "Category",
//                 type: "category",
//             },
//         ],
//         Actions: [
//             {
//                 forRow: function(row) {
//                     // console.log('row', row)
//                     // if(row['data-autocomplete-id'] == 1 && row.type === 'item') {
//                     //     console.log('aaaa', row.attributes['title.untouched'])
//                     // }
//                     return row['data-autocomplete-id'] == 1 && row.type === 'item'
//                 },
//                 iconUrl: 'https://cdn-icons-png.freepik.com/256/275/275790.png',
//                 title: "Visit product's page",
//                 // action: function(e, result) {
//                 //     console.log(e, result)
//                 //     e.preventDefault();
//                 //     alert("Product added to cart");
//                 // }
//             }
//         ]
//     }, '#inputLuigi')
// }

onMounted(() => {
    // LBInitAutocomplete()
})

const isUserMac = navigator.platform.includes('Mac')  // To check the user's Operating System

onMounted(() => {
    if (typeof window !== 'undefined') {
        document.addEventListener('keydown', (event) => {
            // Listen ctrl+k
            if( ( isUserMac ? event.metaKey : event.ctrlKey ) && event.key === 'k') {
                event.preventDefault()
                _inputRef.value?.focus()
            }
        })
    }
})

onUnmounted(() => {
    document.removeEventListener('keydown', () => false)
})

</script>

<template>
    <!-- Button: Search -->
    <div id="search"
        class="h-7 w-fit flex items-center justify-center gap-x-3 ring-1 ring-gray-300 rounded-md px-3 text-gray-500 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500">
        <span class="sr-only">{{ trans("Search") }}</span>
        <FontAwesomeIcon aria-hidden="true" size="sm" icon="fa-regular fa-search" />

        <!-- <input
            ref="_inputRef"
            v-model="searchValue"
            @keydown.enter="() => router.visit(route('iris.search', { q: searchValue }))"
            id="inputLuigi"
            type="text"
            class="h-12 w-full border-0 bg-transparent px-0 text-gray-900 placeholder:text-gray-400 focus:ring-0 sm:text-sm"
            placeholder="Search..."> -->

        <div class="whitespace-nowrap flex items-center justify-end text-gray-500/80 tracking-tight space-x-1">
            <span v-if="isUserMac" class="ring-1 ring-gray-400 bg-gray-100 px-2 leading-none text-xl rounded">⌘</span>
            <span v-else class="ring-1 ring-gray-400 bg-gray-100 px-2 py-0.5 text-xs rounded">Ctrl</span>
            <span class="ring-1 ring-gray-400 bg-gray-100 px-1.5 py-0.5 text-xs rounded">K</span>
        </div>
    </div>
</template>

<style lang="scss">
/* @import 'https://cdn.luigisbox.tech/autocomplete.css';  */

$luigiColor1: #4b5058;
$luigiColor2: #957a65;
$luigiColor3: #e87928;

.luigi-ac-ribbon {
    /* Border top of the Autocomplete */
    background: $luigiColor1 !important;
}


/* Styling for Layout: Hero */
.luigi-ac-hero-color {
    background: $luigiColor1 !important;
}
.luigi-ac-others {
    background: #F3F7FA !important;
}
.luigi-ac-header {
    color: $luigiColor1 !important;
    font-size: 1.2rem !important;
    font-weight: bold !important;
}
.luigi-ac-highlight {
    background: color-mix(in srgb, $luigiColor3 90%, transparent) !important;
    color: #fff !important;
    padding-left: 1px !important;
    padding-right: 1px !important;
}

.luigi-ac-item {
    padding-top: 5px !important;
    padding-bottom: 5px !important;
}

.luigi-ac-item.active, .luigi-ac-active {
    background: #F3F7FA !important;
}

.luigi-ac-item:hover, .luigi-ac-other:hover {
    background: color-mix(in srgb, $luigiColor1 10%, transparent) !important;
}
/* End of styling for Layout: Hero */

.luigi-ac-button-buy {
    background: $luigiColor1 !important;
    border-radius: 5px;
}

.luigi-ac-button-buy:hover {
    background: color-mix(in srgb, $luigiColor1 75%, black) !important;
}



.luigi-ac-button {
    background: transparent !important;
    transition: background 0.05s !important;
    border-radius: 5px !important;
    border: 1px solid $luigiColor1 !important;
    color: $luigiColor1 !important;
}

.luigi-ac-button:hover {
    background: color-mix(in srgb, $luigiColor1 10%, transparent) !important;
}

.luigi-ac-heromobile .luigi-ac-first-main .luigi-ac-text {
    padding-top: 0 !important;
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
    color: $luigiColor3 !important;
}

// Top Product styling (luigi-ac-first-main)
.luigi-ac-first-main .luigi-ac-attr--formatted_price {
    margin-top: 5px;
    font-size: 1.05rem !important;
    display: block !important;
    color: $luigiColor1 !important;
}

.luigi-ac-first-main .luigi-ac-attr--description {
    text-align: justify !important;
    display: -webkit-box !important;
    margin-top: 5px !important;
    font-size: 0.7rem !important;
    -webkit-line-clamp: 4 !important;
    -webkit-box-orient: vertical !important;
    overflow: hidden !important;
    // color: $luigiColor1 !important;
}

.luigi-ac-first-main .luigi-ac-name {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2 !important;
}

.luigi-ac-first-main .luigi-ac-button-buy {
    padding: 6px 20px !important;
}

.luigi-ac-heromobile .luigi-ac-first-main .luigi-ac-item .luigi-ac-attrs {
    max-height: 900px !important;
    display: block !important;
}

.luigi-ac-heromobile .luigi-ac-first-main .luigi-ac-item {
    padding: .4em 0.8em;
}



// Products styling
.luigi-ac-rest-main .luigi-ac-attrs {
    -webkit-line-clamp: 3 !important;
    max-height: 60px !important;
}

.luigi-ac-rest-main .luigi-ac-attr--formatted_price {
    display: block !important;
    color: $luigiColor1 !important;
}

.luigi-ac-rest-main .luigi-ac-attr--description {
    display: none !important;
}

// .luigi-ac-heromobile .luigi-ac-first-main .luigi-ac-action-primary {
    // margin-top: 20px;
    // position: inherit !important;
    // width: 100% !important;
// }

/* ====================================== Search result */

.lb-search-text-color-primary {
    color: $luigiColor3 !important;
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

.lb-search .lb-checkbox__text {
    margin-left: 25px;
}

.lb-search .lb-result__description {
    text-align: justify;
    display: -webkit-box !important;
    -webkit-box-orient: vertical !important;
    -webkit-line-clamp: 3 !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    margin-bottom: 10px;
}

.lb-search .lb-checkbox__text {
    margin-left: 20px !important; 
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
    color: $luigiColor3 !important;
}

.lb-result__action-buttons {
    flex-grow: 1 !important;
}


.lb-search .lb-result__action-item {
    width: 100% !important;
    margin: 0 !important
}

.lb-search-text-color-primary-clickable {
    color: $luigiColor2 !important;
}

.lb-search-bg-color-primary-clickable {
    background: transparent !important;
    color: $luigiColor1 !important;
    border: 1px solid $luigiColor1 !important;
    border-radius: 4px !important;
}

.lb-search-bg-color-primary-clickable:hover {
    background: color-mix(in srgb, $luigiColor1 20%, transparent) !important;
    
}


</style>