<script setup lang='ts'>
import codemirrorPkg from 'vue-codemirror';
import { json } from "@codemirror/lang-json"

const props = defineProps<{
    form?: any
    fieldName: string
    options: string[] | {}
}>()
const { Codemirror } = codemirrorPkg;
const extensions = [json()]

const defaultJsonLD = {
    "@context": "https://schema.org",
    "@type": "Organization",
    "url": "https://www.aw-fulfilment.co.uk",
    "sameAs": ["https://www.ancientwisdom.biz", "https://www.aw-aromatics.com", "https://www.aw-dropship.com", "https://www.aw-fulfilment.eu", "https://www.awgifts.eu", "https://www.aw-dropship.eu", "https://www.awartisan.eu"],
    "logo": "https://media.aiku.io/QdYcHDe3W11-zs5hwB_GsdGFeLbCAQZQKlLkFpAJjwE/bG9jYWw6Ly9tZWRpYS9TRy9FQy82MFIzMEMxSDc0UktFQ1NHLzhiNGY2NWMwLnBuZw.avif",
    "name": "AW Fulfilment",
    "description": "Put your description with keywords fulfilment in UK",
    "email": "info@aw-fulfilment.co.uk",
    "telephone": "+447377188459",
    "address": {
        "@type": "PostalAddress",
        "streetAddress": "Affinity Park, Europa Drive",
        "addressLocality": "Sheffield",
        "addressCountry": "United Kingdom",
        "addressRegion": "South Yorkshire",
        "postalCode": "S9 1XT"
    },
    "vatID": "GB764298589"
}



</script>

<template>
    <div class="max-w-2xl rounded-md">
        <div class="mt-3">
            <label class="text-gray-600 font-semibold cursor-pointer">SEO Structured Data (JSON-LD)</label>
            <Codemirror 
                v-model="form[fieldName]"
                :style="{ 
                    height: '500px', 
                    textOverflow: 'ellipsis', 
                    border: '1px solid #ddd' 
                }" 
                :autofocus="true"
                :indent-with-tab="true" 
                :tab-size="2" 
                :extensions="extensions"  
            />
        </div>
    </div>
</template>
