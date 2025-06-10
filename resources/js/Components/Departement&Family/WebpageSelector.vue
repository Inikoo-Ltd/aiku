<script setup lang="ts">
import { inject, ref, watch, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { notify } from '@kyvg/vue3-notification'
import PureInput from '@/Components/Pure/PureInput.vue'
import { trans } from 'laravel-vue-i18n'
import { debounce } from 'lodash'
import Pagination from '@/Components/Table/Pagination.vue'
import Image from '@/Components/Image.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faCheckCircle)

const props = defineProps<{
  routeFetch: any
  isLoadingSubmit?: boolean
  isLoadingComponent?: boolean
  headLabel?: string
  submitLabel?: string
  withQuantity?: boolean
  label_result?: string
  valueToRefetch?: string
}>()

const emits = defineEmits<{
  (e: "submit", val: any[]): void
}>()

interface Webpage {
  id: number
  name: string
  code: string
  image: string
  gross_weight: string
  price: number
  currency_code: string
}

const layout = inject('layout', layoutStructure)
const locale = inject('locale', null)

const isLoadingFetch = ref(false)
const searchWebpageKeyword = ref('')
const webpageList = ref<Webpage[]>([])
const webpageMeta = ref()
const webpageLinks = ref()
const selectedItems = ref<Webpage[]>([])

const getWebpageList = async (url?: string) => {
  isLoadingFetch.value = true
  try {
    const urlToFetch = url || route(props.routeFetch.name, {
      ...props.routeFetch.parameters,
      'filter[global]': searchWebpageKeyword.value,
      index_perPage: 25,
    })
    const response = await axios.get(urlToFetch)
    webpageList.value = response.data.data
    webpageMeta.value = response?.data.meta || null
    webpageLinks.value = response?.data.links || null
  } catch (e) {
    console.error('Error', e)
    notify({
      title: trans("Something went wrong."),
      text: trans("Error while getting the webpage list."),
      type: "error"
    })
  } finally {
    isLoadingFetch.value = false
  }
}

const debounceGetWebpageList = debounce(() => getWebpageList(), 500)

const toggleSelect = (item: Webpage) => {
  const index = selectedItems.value.findIndex(i => i.id === item.id)
  if (index !== -1) {
    selectedItems.value.splice(index, 1)
  } else {
    selectedItems.value.push(item)
  }
}

const saveSelection = () => {
  if (selectedItems.value.length > 0) {
    emits('submit', selectedItems.value)
  }
}

onMounted(() => {
  getWebpageList()
})

onUnmounted(() => {
  webpageList.value = []
  webpageMeta.value = null
  webpageLinks.value = null
  searchWebpageKeyword.value = ''
})

watch(() => props.valueToRefetch, () => {
  getWebpageList()
})
</script>

<template>
  <div>
    <slot name="header">
      <div class="mx-auto text-center text-2xl font-semibold pb-4">
        {{ headLabel ?? trans("Add webpages") }}
      </div>
    </slot>

    <div class="relative isolate">
      <div v-if="isLoadingSubmit" class="flex justify-center items-center text-7xl text-white absolute z-10 inset-0 bg-black/40">
        <LoadingIcon />
      </div>

      <!-- Search Input & Save Button -->
      <div class="mb-2 flex justify-between items-center gap-2">
        <div class="w-64">
          <PureInput
            v-model="searchWebpageKeyword"
            @update:modelValue="() => debounceGetWebpageList()"
            :placeholder="trans('Input to search webpages')"
          />
          <slot name="afterInput" />
        </div>
        <div v-if="selectedItems.length > 0">
          <Button @click="saveSelection" type="save" />
        </div>
      </div>

      <!-- Webpage List Section -->
      <div class="text-base font-normal">
        <div class="flex justify-between items-center">
          <div class="font-semibold text-lg py-1">
            {{ props.label_result ?? trans("Result") }} ({{ locale?.number(webpageMeta?.total || 0) }})
          </div>
        </div>
        <div class="border-t border-gray-300 mb-1" />

        <!-- Scrollable Webpage Grid -->
        <div class="h-[400px] overflow-auto py-2 relative">
          <div class="grid grid-cols-3 gap-3 pb-2">
            <template v-if="!isLoadingFetch">
              <template v-if="webpageList?.length > 0">
                <div
                  v-for="(item, index) in webpageList"
                  :key="index"
                  @click="toggleSelect(item)"
                  :class="[
                    'relative h-fit rounded cursor-pointer p-2 flex gap-x-2 border hover:bg-gray-50 transition',
                    selectedItems.some(i => i.id === item.id) ? 'border-green-500 bg-green-50' : ''
                  ]"
                >
                  <slot name="webpage" :item="item">
                    <Image v-if="item.image" :src="item.image" class="w-16 h-16 overflow-hidden" imageCover :alt="item.name" />
                    <div class="flex flex-col justify-between">
                      <div class="w-fit">
                        <div v-tooltip="trans('Code')" class="w-fit text-xs text-gray-400 italic mb-1">
                          {{ item.title || 'no title' }}
                        </div>
                        <div v-tooltip="trans('Name')" class="w-fit font-semibold leading-none mb-1">
                          {{ item.code || 'no code' }}
                        </div>
                      </div>
                    </div>
                  </slot>

                  <FontAwesomeIcon
                    v-if="selectedItems.some(i => i.id === item.id)"
                    icon="fa-solid fa-check-circle"
                    class="absolute top-1 right-1 text-green-600 text-lg"
                  />
                </div>
              </template>
              <div v-else class="text-center text-gray-500 col-span-3">
                {{ trans("No webpages found") }}
              </div>
            </template>

            <!-- Loading Skeleton -->
            <div
              v-else
              v-for="(item, index) in 6"
              :key="index"
              class="rounded cursor-pointer w-full h-20 flex gap-x-2 border skeleton"
            />
          </div>
        </div>

        <!-- Pagination (outside scroll) -->
        <div class="mt-4">
          <Pagination
            v-if="webpageMeta"
            :on-click="getWebpageList"
            :has-data="true"
            :meta="webpageMeta"
            :per-page-options="[]"
          />
        </div>
      </div>
    </div>
  </div>
</template>
