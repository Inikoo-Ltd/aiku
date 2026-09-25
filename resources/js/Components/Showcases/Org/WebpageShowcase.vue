<script setup lang="ts">
import { computed, ref, watch } from 'vue'
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
  faExternalLink,
  faPowerOff,
  faArrowRight
} from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import Button from "@/Components/Elements/Buttons/Button.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { ctrans } from "@/Composables/useTrans"
import { Message } from 'primevue'
import { router } from "@inertiajs/vue3"
import SearchInWebsiteAvailabilityChecklist from '@/Components/Utils/SearchInWebsiteAvailabilityChecklist.vue'
import RealUserSpeed from '@/Components/DataDisplay/RealUserSpeed.vue'
import WebpageSeo from '@/Components/DataDisplay/WebpageSeo.vue'
import WebpageEngagement from '@/Components/DataDisplay/WebpageEngagement.vue'
import { useFormatTime } from '@/Composables/useFormatTime'

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
    is_hidden_from_search_engines?: boolean
    layout?: {
      web_blocks?: any[]
    }
  },
  redirected_to?: {
    slug?: string
    code?: string
    url?: string
  },
  closed?: {
    closed_at: string | null
    closed_by: string | null
  } | null,
  pagespeed?: any,
  engagement?: any,
  seo?: any
}>()

const isClosed = computed(() => props.data?.state === 'closed')

const closedDescription = computed(() => {
  const closedAt = props.closed?.closed_at
  const closedBy = props.closed?.closed_by

  if (closedAt && closedBy) {
    return ctrans('Set offline on :date by :name', { date: useFormatTime(closedAt, { formatTime: 'hm' }), name: closedBy })
  }
  if (closedAt) {
    return ctrans('Set offline on :date', { date: useFormatTime(closedAt, { formatTime: 'hm' }) })
  }

  return ctrans('When this webpage was set offline was not recorded')
})

const detailBesidePreview = computed(() => (props.data?.is_hidden_from_search_engines ?? false) || props.data?.state !== 'live')

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
  <div v-if="isClosed" class="px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex flex-col items-center   border-gray-200 bg-white px-6 py-16 text-center">
      <div class="flex h-28 w-28 items-center justify-center rounded-full bg-red-50 ring-8 ring-red-50/50">
        <FontAwesomeIcon :icon="faPowerOff" class="text-6xl text-red-500" fixed-width aria-hidden="true" />
      </div>

      <h2 class="mt-6 text-2xl font-semibold text-gray-900">{{ ctrans('This webpage is offline') }}</h2>
      <p class="mt-2 text-sm text-gray-500">{{ closedDescription }}</p>

      <div class="mt-8 w-full max-w-lg rounded-lg border border-gray-200 bg-gray-50 px-5 py-4 text-left">
        <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans('Visitors are redirected to') }}</div>
        <template v-if="redirected_to">
          <div class="mt-2 flex items-center justify-between gap-4">
            <div class="min-w-0">
              <div class="truncate font-semibold text-gray-900">{{ redirected_to.code }}</div>
              <div class="truncate text-sm text-gray-500">../{{ redirected_to.url }}</div>
            </div>
            <Button type="secondary" size="xs" :label="ctrans('View page')" :iconRight="faArrowRight" @click="visitRedirect" />
          </div>
        </template>
        <div v-else class="mt-2 text-sm text-gray-500">{{ ctrans('No redirect is set for this webpage') }}</div>
      </div>
    </div>
  </div>

  <template v-else>
  <Message v-if="redirected_to" :severity="'error'" class="!bg-red-100">
    <div class="px-2 font-normal hover:underline cursor-pointer grid" @click="visitRedirect">
      <span class="!no-underline">
        {{ ctrans('This webpage is being redirected to another page') }}:
      </span>
      <span>
        [ <span class="font-semibold italic"> {{ redirected_to?.code }} </span> | ../{{ redirected_to?.url }} ]
        <FontAwesomeIcon
          v-tooltip="ctrans('Click here to view page')"
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
              {{ filterBlock ? ctrans('Logged In') : ctrans('Logged Out') }}
            </span>
          </div>
          <!-- Screen Mode SelectButton -->
          <div class="flex items-center gap-2">
            <ModalConfirmationDelete
              v-if="data?.state == 'live'"
              :description="ctrans('Purge all cached files. Purging your cache may slow your website temporarily')"
              :title="ctrans('Break cache')" :noLabel="ctrans('Confirm')" noIcon="" :routeDelete="{
                name: 'grp.models.webpage.break_cache',
                parameters: {
                  webpage: data?.id
                },
                method: 'post'
              }">
              <template #default="{ changeModel }">
                <Button v-tooltip="ctrans('Break cache')" @click="changeModel" type="tertiary" size="xs" :icon="faFragile" :aria-label="ctrans('Break cache')" />
              </template>
            </ModalConfirmationDelete>
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
          </div>
        </div>

        <!-- Internal Search Availability Checklist -->
        <div v-if="data?.search_in_website_availability" class="border-t border-gray-200 px-6 py-4">
          <SearchInWebsiteAvailabilityChecklist :availability="data?.search_in_website_availability" />
        </div>
      </div>

      <!-- Right: real user speed, and the detail when there is no speed to show -->
      <div v-if="!redirected_to" class="space-y-6">
        <div v-if="pagespeed !== null" class="rounded-lg border border-gray-200 bg-white shadow-sm">
          <RealUserSpeed embedded :report="pagespeed" />
        </div>

        <WebpageEngagement v-if="data?.is_hidden_from_search_engines" :engagement="engagement" />

        <WebpageSeo v-if="detailBesidePreview" :seo="seo" stacked />
      </div>
    </div>

    <WebpageSeo v-if="!redirected_to && !detailBesidePreview" :seo="seo" />
  </div>
  </template>
</template>

<style scoped>
iframe {
  background-color: white;
}
</style>
