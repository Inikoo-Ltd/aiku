<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import BrowserView from '@/Components/Pure/BrowserView.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import InputSwitch from 'primevue/inputswitch'
import SelectButton from 'primevue/selectbutton'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faUser,
  faUserSlash,
  faDesktop,
  faTabletAlt,
  faMobileAlt,
  faGlobe, faLink, faSearch, faFragile
} from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { trans } from "laravel-vue-i18n"
import { useFormatTime } from '@/Composables/useFormatTime'

library.add(faUser, faUserSlash, faDesktop, faTabletAlt, faMobileAlt, faGlobe, faLink, faSearch, faFragile)

const props = defineProps<{
  data: {
    slug: string
    state: string
    status: string
    created_at: string
    updated_at: string
    domain: string
    code: string
    typeIcon: string
    canonical_url_without_domain: string
    canonical_url: string
    url: string
    layout: {
      web_blocks?: any[]
    }
    luigi_data: {
      last_reindexed: string
      luigisbox_tracker_id: string
      luigisbox_private_key: string
      luigisbox_lbx_code: string
    }
  },
  
}>()

const filterBlock = ref<Boolean>(true)
const screenMode = ref<'desktop' | 'tablet' | 'mobile'>('desktop')
const isIframeLoading = ref(true)
const _iframe = ref<HTMLIFrameElement | null>(null)

const iframeSrc = route('grp.websites.preview', [
  route().params['website'],
  route().params['webpage'],
  {
    organisation: route().params['organisation'],
    shop: route().params['shop'],
    fulfilment: route().params['fulfilment']
  }
])

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

// Section: Button reindex website search
const isAbleReindex = computed(() => {
  const lastReindexed30Minutes = new Date(props.data?.luigi_data.last_reindexed)
  lastReindexed30Minutes.setMinutes(lastReindexed30Minutes.getMinutes() + 30)

  return lastReindexed30Minutes < new Date()
})
</script>

<template>
  <div class="px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
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
        <BrowserView :screenMode="screenMode" :tab="{ icon: data.typeIcon, label: data.title }"
          :url="{ domain: data.domain, page: data.canonical_url_without_domain }">
          <template #page v-if="data.layout.web_blocks?.length">
            <div class="relative w-full h-full">
              <div v-if="isIframeLoading" class="absolute inset-0 flex items-center justify-center bg-white">
                <LoadingIcon class="w-24 h-24 text-6xl" />
              </div>
              <iframe ref="_iframe" :src="iframeSrc" :title="'props.title'" class="w-full h-full"
                @load="isIframeLoading = false" />
            </div>
          </template>
        </BrowserView>
      </div>

      <!-- Right Panel (Optional) -->
      <div class="hidden xl:flex justify-end w-full">
        <!-- Optional sidebar -->
        <div class="w-64 border border-gray-300 rounded-md p-2 h-fit">
          <div class="space-y-2">
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
                <ButtonWithLink @click="changeModel" :icon="faFragile" type="tertiary" :label="trans('Break cache')"
                  full>
                </ButtonWithLink>
              </template>
            </ModalConfirmationDelete>

            <ButtonWithLink v-if="data?.luigi_data?.luigisbox_tracker_id" s
              xv-tooltip="isAbleReindex ? '' : trans('You can reindex again at :date', { date: useFormatTime(new Date(dateAdd30MinutesLastReindex), { formatTime: 'hm' }) })"
              xdisabled="!isAbleReindex"
              :routeTarget="{
                name: 'grp.models.webpage_luigi.reindex',
                parameters: {
                  webpage: data?.id
                }
              }" icon="fal fa-search" method="post"
              :type="data?.luigi_data?.luigisbox_private_key ? 'tertiary' : 'warning'" full>
              <template #label>
                <span class="text-xs">
                  {{ trans('Reindex Webpage Search') }}
                </span>
              </template>
              <template v-if="isAbleReindex" #iconRight>
                <!-- <div v-if="data?.luigi_data?.luigisbox_private_key"
                  v-tooltip="trans('This will reindexing the product that will appear in the search feature')"
                  class="text-gray-400 hover:text-gray-700">
                  <FontAwesomeIcon icon="fal fa-info-circle" class="" fixed-width aria-hidden="true" />
                </div>
                <div v-else v-tooltip="trans('Please input Luigi Private Key do start reindexing')"
                  class="text-amber-500">
                  <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="" fixed-width aria-hidden="true" />
                </div> -->
                <div v-if="!data?.luigi_data?.luigisbox_private_key" v-tooltip="trans('Please input Luigi Private Key do start reindexing')"
                  class="text-amber-500">
                  <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="" fixed-width aria-hidden="true" />
                </div>
              </template>
            </ButtonWithLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
iframe {
  background-color: white;
}
</style>
