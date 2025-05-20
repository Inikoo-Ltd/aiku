<script setup lang='ts'>
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import { Codemirror } from "vue-codemirror"
import { ref, watch } from "vue"
import { json } from "@codemirror/lang-json"

const props = defineProps<{
    form?: any
    fieldName: string
    options: string[] | {}
}>()

const extensions = [json()]

// Contoh default JSON-LD untuk SEO
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

// Simpan JSON dalam bentuk string untuk editor
const jsonValue = ref(JSON.stringify(props.form?.[props.fieldName].structured_data || defaultJsonLD, null, 2))

// Perubahan JSON yang diinput oleh user akan diperbarui ke form
watch(jsonValue, (newValue) => {
    try {
        props.form[props.fieldName].structured_data = JSON.parse(newValue)
    } catch (error) {
        console.error("Invalid JSON:", error)
    }
})
</script>

<template>
    <div class="max-w-2xl rounded-md">
        <div>
            <label class="text-gray-600 font-semibold cursor-pointer">Structured Data Type</label>
            <PureMultiselect v-model="form[fieldName].structured_data_type" :options="options" />
        </div>
        <div class="mt-3">
            <label class="text-gray-600 font-semibold cursor-pointer">SEO Structured Data (JSON-LD)</label>
            <Codemirror v-model="form[fieldName].structured_data"
                :style="{ height: '500px', textOverflow: 'ellipsis', border: '1px solid #ddd' }" :autofocus="true"
                :indent-with-tab="true" :tab-size="2" :extensions="extensions"  />
        </div>
    </div>
</template>
