<script setup lang="ts">
import { ref, watch } from 'vue'
import BrowserView from '@/Components/Pure/BrowserView.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import ToggleSwitch from 'primevue/toggleswitch'
import SelectButton from 'primevue/selectbutton'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faUser,
  faUserSlash,
  faDesktop,
  faTabletAlt,
  faMobileAlt,
  faGlobe, faLink, faSearch, faFragile,
  faExternalLink
} from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import Button from "@/Components/Elements/Buttons/Button.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { trans } from "laravel-vue-i18n"
import { Message } from 'primevue'
import { router } from "@inertiajs/vue3"
import SearchInWebsiteAvailabilityChecklist from '@/Components/Utils/SearchInWebsiteAvailabilityChecklist.vue'
import PageSpeedInsights from '@/Components/DataDisplay/PageSpeedInsights.vue'
import WebpageSeo from '@/Components/DataDisplay/WebpageSeo.vue'

library.add(faUser, faUserSlash, faDesktop, faTabletAlt, faMobileAlt, faGlobe, faLink, faSearch, faFragile)

const props = defineProps<{
  data?: {
    id?: number
    slug?: string
    state?: string
    status?: string
    created_at?: string
    updated_at?: string
    domain?: string
    code?: string
    title?: string
    typeIcon?: string
    canonical_url_without_domain?: string
    canonical_url?: string
    url?: string
    search_in_website_availability?: any
    layout?: {
      web_blocks?: any[]
    }
  },
  redirected_to?: {
    slug?: string
    code?: string
    url?: string
  },
  pagespeed?: any,
  seo?: any
}>()

const filterBlock = ref<boolean>(true)
const screenMode = ref<'desktop' | 'tablet' | 'mobile'>('desktop')
const isIframeLoading = ref(true)
const _iframe = ref<HTMLIFrameElement | null>(null)

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

const visitRedirect = () => {
  router.visit(route('grp.org.shops.show.web.webpages.show', {
    organisation: route().params['organisation'],
    shop: route().params['shop'],
    website: route().params['website'],
    webpage: props.redirected_to.slug,
  }))
}

</script>

<template>
  <Message v-if="redirected_to" :severity="'error'" class="!bg-red-100">
    <div class="px-2 font-normal hover:underline cursor-pointer grid" @click="visitRedirect">
      <span class="!no-underline">
        {{ trans('This webpage is being redirected to another page') }}:
      </span>
      <span>
        [ <span class="font-semibold italic"> {{ redirected_to?.code }} </span> | ../{{ redirected_to?.url }} ]
        <FontAwesomeIcon
          v-tooltip="trans('Click here to view page')"
          :icon="faExternalLink"
          class="ml-1 !no-underline" fixed-width
        />
      </span>
    </div>
  </Message>

  <div class="px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
      <!-- Left: Preview -->
      <div v-if="!redirected_to" class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 px-6 py-3">
          <!-- Logged In / Logged Out Switch -->
          <div class="flex items-center gap-3">
            <FontAwesomeIcon :icon="['fal', filterBlock ? 'user' : 'user-slash']" class="text-gray-500" fixed-width />
            <ToggleSwitch v-model="filterBlock" :true-value="true" :false-value="false" />
            <span class="text-sm font-medium text-gray-800">
              {{ filterBlock ? trans('Logged In') : trans('Logged Out') }}
            </span>
          </div>
          <!-- Screen Mode SelectButton -->
          <div class="flex items-center">
            <SelectButton v-model="screenMode" :options="screenModeOptions" optionLabel="label" optionValue="value"
              class="p-button-outlined">
              <template #option="slotProps">
                <div class="flex items-center gap-2">
                  <FontAwesomeIcon :icon="slotProps.option.icon" fixed-width />
                  <span>{{ slotProps.option.label }}</span>
                </div>
              </template>
            </SelectButton>
          </div>
        </div>

        <!-- Browser View -->
        <div class="p-6">
          <div class="relative">
            <BrowserView :screenMode="screenMode" :tab="{ icon: data?.typeIcon, label: data?.title }"
              :url="{ domain: data?.domain, page: data?.canonical_url_without_domain }">
              <template #page v-if="data?.layout?.web_blocks?.length">
                <div class="relative w-full h-full">
                  <div v-if="isIframeLoading" class="absolute inset-0 flex items-center justify-center bg-white">
                    <LoadingIcon class="w-24 h-24 text-6xl" />
                  </div>
                  <iframe
                    ref="_iframe"
                    :src="data?.canonical_url"
                    :key="screenMode"
                    :title="'props.title'"
                    class="w-full h-full"
                    @load="isIframeLoading = false" />
                </div>
              </template>
            </BrowserView>

            <div v-if="data?.state === 'closed'" class="absolute inset-0 bg-black/40 flex items-center justify-center rounded-md">
              <img src="/assets/offline_stamp.webp" class="-rotate-[12deg] w-1/2" />
            </div>
          </div>
        </div>

        <!-- Internal Search Availability Checklist -->
        <div v-if="data?.search_in_website_availability" class="border-t border-gray-200 px-6 py-4">
          <SearchInWebsiteAvailabilityChecklist :availability="data?.search_in_website_availability" />
        </div>
      </div>

      <!-- Right: Break cache and page speed -->
      <div v-if="!redirected_to" class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div v-if="data?.state == 'live'" class="border-b border-gray-200 p-4">
          <ModalConfirmationDelete
            :description="trans('Purge all cached files. Purging your cache may slow your website temporarily')"
            :title="trans('Break cache')" :noLabel="trans('Confirm')" noIcon="" :routeDelete="{
              name: 'grp.models.webpage.break_cache',
              parameters: {
                webpage: data?.id
              },
              method: 'post'
            }">
            <template #default="{ changeModel }">
              <Button @click="changeModel" type="primary" :icon="faFragile" :label="trans('Break cache')" full />
            </template>
          </ModalConfirmationDelete>
        </div>

        <PageSpeedInsights embedded :pagespeed="pagespeed" />
      </div>
    </div>

    <WebpageSeo v-if="!redirected_to" :seo="seo" />
  </div>
</template>

<style scoped>
iframe {
  background-color: white;
}
</style>
