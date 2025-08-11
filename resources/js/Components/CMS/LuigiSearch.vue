<script setup lang="ts">
import { computed, inject, onMounted, ref, onBeforeMount } from "vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faSearch)


// vika_luigi.js
// &language=en
// &currency_symbol=£

const props = defineProps<{
    id: string
}>()

const inputValue = ref('')

onBeforeMount(() => {
    // Read query param 'q' from URL if present
    const params = new URLSearchParams(window.location.search)
    const q = params.get('q')
    if (q) inputValue.value = q
})


const layout = inject('layout', {})
const locale = inject('locale', aikuLocaleStructure)


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
            //Locale: 'en',
            PriceFilter: {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
                locale: locale.language.code,
                prefixed: true,
                symbol: locale.currencySymbol(layout.iris?.currency?.code)
            },
            // Translations: {
            //     en: {
            //         // showBuyTitle: 'Burrrry now', // Top Product: Button label
            //         // priceFilter: {
            //         //     minimumFractionDigits: 0,
            //         //     maximumFractionDigits: 2,
            //         //     locale: locale.language.code,
            //         //     prefixed: true,
            //         //     symbol: locale.currencySymbol(layout.iris?.currency?.code)
            //         // }
            //     }
            // },
            RemoveFields: layout.iris.is_logged_in ? [] : ['formatted_price', 'price_amount', 'price'],
            Types: [
                {
                    name: "Products",
                    heroName: "Top product",
                    type: "item",
                    size: 7,
                    xattributes: layout.iris.is_logged_in ? ['product_code', 'formatted_price'] : ['product_code'],
                },
                {
                    name: "Queries",
                    type: "query",
                },
                {
                    name: "Categories",
                    type: "category",
                },
                {
                    name: "Articles",
                    type: "news",
                },
                {
                    name: "Departments",
                    type: "department",
                },
                {
                    name: "Sub Departments",
                    type: "sub_department",
                },
                {
                    name: "Brands",
                    type: "brand",
                },
                {
                    name: "Collections",
                    type: "collection",
                },
                {
                    name: "Tags",
                    type: "tag",
                },
            ],
            ShowAllTitle: 'View all results', // Show All Product: Button label
            ShowAllCallback: (q) => {  // Called when 'Show All Product' clicked
                visitSearchPage()
            },
            ShowBuyTitle: 'Detail', // Top Product: Button label
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

    console.log("Init autocomplete", props.id)
}


// Import Luigi CSS style
const importStyleCSS = () => {
    const link = document.createElement("link")
    link.rel = "stylesheet"
    link.href = "https://cdn.luigisbox.com/autocomplete.css"
    document.head.appendChild(link)
    document.documentElement.style.setProperty('--luigiColor1', layout.iris?.theme?.color?.[0]);
    document.documentElement.style.setProperty('--luigiColor2', layout.iris?.theme?.color?.[1]);
    document.documentElement.style.setProperty('--luigiColor3', layout.iris?.theme?.color?.[2]);
    document.documentElement.style.setProperty('--luigiColor4', layout.iris?.theme?.color?.[3]);
}



onBeforeMount(() => {
    const script = document.createElement('script');
    script.src = "https://cdn.luigisbox.com/autocomplete.js";
    script.async = true;
    document.head.appendChild(script);
    script.onload = () => {
        LBInitAutocompleteNew();
    };
    script.onerror = () => {
        console.error('Failed to load Luigi autocomplete script');
    }
})
onMounted(() => {
    importStyleCSS()
})

const visitSearchPage = () => {
    console.log('visit', inputValue.value)
    if (inputValue.value) {
        router.get(`/search?q=${encodeURIComponent(inputValue.value)}`)
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
            :value="inputValue"
            @input="(q) => (inputValue = q?.target?.value, console.log('inputValue', inputValue))"
            xdisabled
            class="h-12 min-w-28 focus:border-transparent focus:ring-2 focus:ring-gray-700 w-full md:min-w-0 md:w-full rounded-full border border-[#d1d5db] disabled:bg-gray-200 disabled:cursor-not-allowed pl-10"
            :id="id || 'inputLuigi'"
            xstyle="height: 35px"
            :placeholder="trans('Search')"
            @keydown.enter="() => visitSearchPage()"
        />
        <FontAwesomeIcon icon="far fa-search" class="group-focus-within:text-gray-700 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" fixed-width aria-hidden="true" />
    </div>
</template>

<style lang="scss">

.luigi-ac-ribbon {
    /* Border top of the Autocomplete */
    background: var(--luigiColor1) !important;
}


/* Styling for Layout: Hero */
.luigi-ac-hero-color {
    background: var(--luigiColor1) !important;
    color: var(--luigiColor2) !important;
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
  /*  padding-left: 0px !important; */
    padding-bottom: 2px !important;
}
.luigi-ac-others {
    background: #F3F7FA !important;
    overflow-y: auto !important;
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

.luigi-ac-heromobile-action-for-mobile  {
    @apply md:!hidden;
}

/* Button: Shop Today */
/* .luigi-ac-heromobile .luigi-ac-first-main .luigi-ac-action-primary {
    margin-top: 20px;
    position: inherit !important;
    width: 100% !important;
} */


/* ====================================== Search result */


</style>