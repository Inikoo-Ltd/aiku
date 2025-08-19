<script setup lang="ts">
import { ref, watch } from 'vue'
import BrowserView from '@/Components/Pure/BrowserView.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import InputSwitch from 'primevue/inputswitch'
import SelectButton from 'primevue/selectbutton'
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { capitalize } from "@/Composables/capitalize";
import {
  faUser,
  faUserSlash,
  faDesktop,
  faTabletAlt,
  faMobileAlt
} from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'

library.add(faUser, faUserSlash, faDesktop, faTabletAlt, faMobileAlt)

const props = defineProps<{
  pageHead: PageHeadingTypes
  title: string
  data: {
    slug: string
    state: string
    status: string
    created_at: string
    updated_at: string
    domain: string
    code: string
    typeIcon: string
    url: string
    layout: {
      web_blocks?: any[]
    }
  }
}>()

const filterBlock = ref<Boolean>(true)
const screenMode = ref<'desktop' | 'tablet' | 'mobile'>('desktop')
const isIframeLoading = ref(true)
const _iframe = ref<HTMLIFrameElement | null>(null)

console.log( route().params)
const iframeSrc = route('grp.org.shops.show.web.webpages.snapshot.preview', 
  {
    organisation: route().params['organisation'],
    shop: route().params['shop'],
    fulfilment: route().params['fulfilment'],
    website : route().params['website'],
    webpage: route().params['webpage'],
    snapshot: route().params['snapshot'],
  }
)

const sendToIframe = (data: any) => {
  _iframe.value?.contentWindow?.postMessage(data, '*')
}

watch(filterBlock, (newValue) => {
  sendToIframe({ key: 'isPreviewLoggedIn', value: newValue })
})

const screenModeOptions = [
  { label: 'Desktop', value: 'desktop', icon: ['fal', 'desktop'] },
  { label: 'Tablet', value: 'tablet', icon: ['fal', 'tablet-alt'] },
  { label: 'Mobile', value: 'mobile', icon: ['fal', 'mobile-alt'] }
]

console.log(props)
</script>

<template>

  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead" />
  <div class="px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="grid grid-cols-1  gap-6">
      <!-- Left: Controls + Preview -->
      <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <!-- Logged In / Logged Out Switch -->
          <div class="flex items-center gap-3">
            <FontAwesomeIcon :icon="['fal', filterBlock ? 'user' : 'user-slash']" />
            <InputSwitch v-model="filterBlock" :true-value="true" :false-value="false" />
            <span class="text-sm font-medium text-gray-800">
              {{ filterBlock ? 'Logged In' : 'Logged Out' }}
            </span>
          </div>

          <!-- Screen Mode SelectButton -->
          <div class="flex items-center">
            <SelectButton v-model="screenMode" :options="screenModeOptions" optionLabel="label" optionValue="value"
              class="p-button-outlined">
              <template #option="slotProps">
                <div class="flex items-center gap-2">
                  <FontAwesomeIcon :icon="slotProps.option.icon" />
                  <span>{{ slotProps.option.label }}</span>
                </div>
              </template>
            </SelectButton>
          </div>
        </div>

        <!-- Browser View -->
        <BrowserView :screenMode="screenMode" :tab="{ icon: data.typeIcon, label: data.code }"
          :url="{ domain: data.domain, page: data.url }">
          <template #page v-if="data.layout.web_blocks?.length">
            <div class="relative w-full h-full">
              <div v-if="isIframeLoading" class="absolute inset-0 flex items-center justify-center bg-white">
                <LoadingIcon class="w-24 h-24 text-6xl" />
              </div>
              <iframe ref="_iframe" :src="iframeSrc" :title="props.title" class="w-full h-full"
                @load="isIframeLoading = false" />
            </div>
          </template>
        </BrowserView>
      </div>

      <!-- Right Panel (Optional) -->
      <div class="hidden xl:block">
        <!-- Optional sidebar -->
      </div>
    </div>
  </div>
</template>

<style scoped>
iframe {
  background-color: white;
}
</style>
