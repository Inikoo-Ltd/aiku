<script setup lang="ts">
import { computed, inject, onMounted, ref } from "vue"
import "https://cdn.luigisbox.com/autocomplete.js"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"


// vika_luigi.js
// &language=en
// &currency_symbol=£

const inputValue = ref('')


console.log('xxx')

const isLogin = computed(() => {
    return layout.is_logged_in
})


const LBInitAutocompleteNew = async () => {
    await AutoComplete(
        {
            Layout: "heromobile",
            TrackerId: layout.iris?.luigisbox_tracker_id,
            Locale: 'en',
            Translations: {
                en: {
                    showBuyTitle: 'Buy now', // Top Product: Button label
                    // priceFilter: {
                    //     minimumFractionDigits: 0,
                    //     maximumFractionDigits: 2,
                    //     locale: 'en',
                    //     prefixed: true,
                    //     symbol: '£'
                    // }
                }
            },
            // RemoveFields: fieldsRemoved,
            Types: [
                {
                    name: "Item",
                    type: "item",
                    size: 7,
                    attributes: isLogin ? ['product_code', 'formatted_price'] : ['product_code'],
                },
                {
                    name: "Query",
                    type: "query",
                },
                {
                    name: "Category",
                    type: "category",
                },
                // {
                //     name: "Articles",
                //     type: "articles",
                // },
            ],
            ShowAllCallback: () => {  // Called when 'Show All Product' clicked
                if (inputValue.value) {
                    // console.log('query:', stringQuery)
                    window.location.href = `/search?q=${encodeURIComponent(inputValue.value)}`;
                } else {
                    notify({
                        title: trans("Something went wrong"),
                        text: trans("The query must be filled"),
                        type: "error",
                    })
                }
            },
            Actions: [  // Action for Top Product 'Add To Basket'
                {
                    forRow: function(row) {
                        // console.log('row:', row)
                        // if (deviceType === 'desktop') {
                            return row['data-autocomplete-id'] == 1 && row.type === 'item'  // Top product
                        // } else {
                            // return false
                        // }                        
                    },
                    // iconUrl: 'https://cdn-icons-png.freepik.com/256/275/275790.png',
                    title: "Visit product's page",
                    action: function(e, result) {
                        console.log('zzzzzzzzz', e, result)
                        // e.preventDefault();
                        // alert("Product added to cart");
                    }
                }
            ]
        },
        "#inputLuigi"
    )

    console.log("Init autocomplete")
}







// Import Luigi CSS style
const importStyleCSS = () => {
    const link = document.createElement("link")
    link.rel = "stylesheet"
    link.href = "https://cdn.luigisbox.com/autocomplete.css"
    document.head.appendChild(link)
    document.documentElement.style.setProperty('--luigiColor1', layout.iris.theme.color[0]);
    document.documentElement.style.setProperty('--luigiColor2', layout.iris.theme.color[1]);
    document.documentElement.style.setProperty('--luigiColor3', layout.iris.theme.color[2]);
    document.documentElement.style.setProperty('--luigiColor4', layout.iris.theme.color[3]);
}


const layout = inject('layout', {})
const locale = inject('locale', {})
console.log('layout:', layout)
console.log('locale:', locale)

onMounted(() => {
    importStyleCSS()
    LBInitAutocompleteNew()
})
</script>

<template>
    <input v-model="inputValue" class="w-full" id="inputLuigi" style="border: 1px solid #d1d5db; border-radius: 7px;height: 35px;padding-left: 10px;" placeholder="Search"/>
</template>

<style>
.luigi-ac-ribbon {
    /* Border top of the Autocomplete */
    background: var(--luigiColor1) !important;
}


/* Styling for Layout: Hero */
.luigi-ac-hero-color {
    background: var(--luigiColor1) !important;
}
.luigi-ac-others {
    background: #F3F7FA !important;
}
.luigi-ac-header {
    color: var(--luigiColor1) !important;
    font-size: 1.2rem !important;
    font-weight: bold !important;
}
.luigi-ac-highlight {
    background: color-mix(in srgb, var(--luigiColor1) 90%, transparent) !important;
    border-radius: 2px !important;
    color: var(--luigiColor2) !important;
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

.luigi-ac-item:hover, .luigi-ac-other:hover {
    background: color-mix(in srgb, var(--luigiColor1) 10%, transparent) !important;
}
/* End of styling for Layout: Hero */


.luigi-ac-button-buy {
    background: var(--luigiColor1) !important;
    border-radius: 5px;
}

.luigi-ac-button-buy:hover {
    background: color-mix(in srgb, var(--luigiColor1) 75%, black) !important;
}


.luigi-ac-button {
    background: transparent !important;
    transition: background 0.05s !important;
    border-radius: 5px !important;
    border: 1px solid var(--luigiColor1) !important;
    color: var(--luigiColor1) !important;
}

.luigi-ac-button:hover {
    background: color-mix(in srgb, var(--luigiColor1) 10%, transparent) !important;
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
    color: var(--luigiColor3) !important;
}

.luigi-ac-queries {
    line-height: 0px !important;
}

.luigi-ac-query .luigi-ac-other-content {
    color: #fff !important;
}


/* Top Product styling (luigi-ac-first-main) */
.luigi-ac-first-main .luigi-ac-attr--formatted_price {
    margin-top: 5px;
    font-size: 1.05rem !important;
    display: block !important;
    color: var(--luigiColor1) !important;
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
    color: var(--luigiColor1) !important;
}

/* Button: Shop Today */
/* .luigi-ac-heromobile .luigi-ac-first-main .luigi-ac-action-primary {
    margin-top: 20px;
    position: inherit !important;
    width: 100% !important;
} */


/* ====================================== Search result */


</style>