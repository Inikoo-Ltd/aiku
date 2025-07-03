<script setup lang="ts">
import { ref, watch } from 'vue'
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import { json } from "@codemirror/lang-json"
import { basicSetup } from "codemirror"
import codemirrorPkg from 'vue-codemirror';

library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)

const props = defineProps<{
  form: any
  fieldName: string
  options?: any
  fieldData?: {
    type: string
    placeholder: string
    readonly?: boolean
    copyButton: boolean
    maxLength?: number
  }
}>()
const { Codemirror } = codemirrorPkg;
const type = ref(null)
const optionsType = [
  { label: 'Organisation', value: 'organisation' }
]

// Default structured data
const defaultJsonLD = JSON.stringify({
  "@context": "https://schema.org",
  "@type": "Organization",
  "url": "https://www.aw-fulfilment.co.uk",
  "sameAs": [
    "https://www.ancientwisdom.biz",
    "https://www.aw-aromatics.com",
    "https://www.aw-dropship.com",
    "https://www.aw-fulfilment.eu",
    "https://www.awgifts.eu",
    "https://www.aw-dropship.eu",
    "https://www.awartisan.eu"
  ],
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
}, null, 2)

// Menonton perubahan tipe untuk mengganti nilai editor
watch(type, (newValue) => {
  if (newValue === 'organisation') {
    props.form[props.fieldName] = defaultJsonLD
  } else {
    props.form[props.fieldName] = ''
  }
})
</script>

<template>
  <div class="max-w-2xl mx-auto bg-white shadow-md rounded-xl p-6 border border-gray-200">
    <!-- Header -->
    <div class="mb-4">
      <h2 class="text-lg font-semibold text-gray-800">Pilih Tipe</h2>
      <p class="text-sm text-gray-500">Silakan pilih tipe sebelum mengedit kode.</p>
    </div>

    <!-- Multiselect -->
    <div class="mb-4">
      <PureMultiselect v-model="type" :options="optionsType" class="w-full" />
    </div>

    <!-- Code Editor -->
    <transition name="fade">
      <div v-if="type" class="mt-4">
        <h3 class="text-md font-medium text-gray-700 mb-2">Editor JSON-LD</h3>
        <div class="border border-gray-300 rounded-lg overflow-hidden shadow-sm transition-all duration-300 hover:shadow-md">
          <Codemirror 
            v-model="form[fieldName]" 
            class="h-[300px] bg-gray-50 text-gray-900 border border-gray-200"
            :style="{ textOverflow: 'ellipsis' }" 
            :autofocus="true"
            :indent-with-tab="true" 
            :tab-size="2" 
            :extensions="[basicSetup, json()]" 
          />
        </div>
      </div>
    </transition>
  </div>
</template>

<style>
/* Animasi transisi */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease-in-out;
}
.fade-enter, .fade-leave-to {
  opacity: 0;
}
</style>
