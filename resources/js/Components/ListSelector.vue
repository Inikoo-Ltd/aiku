<script setup lang="ts">
import { inject, ref, computed, onMounted, onUnmounted } from 'vue'
import Dialog from 'primevue/dialog'
import Button from './Elements/Buttons/Button.vue'
import axios from 'axios'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { notify } from '@kyvg/vue3-notification'
import PureInput from '@/Components/Pure/PureInput.vue'
import { trans } from 'laravel-vue-i18n'
import { debounce } from 'lodash-es'
import Pagination from '@/Components/Table/Pagination.vue'
import Image from '@/Components/Image.vue'
import { routeType } from '@/types/route'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle } from "@fas"
import { faPlus, faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { faTrashAlt } from '@far'

library.add(faCheckCircle, faTimes)

const props = withDefaults(defineProps<{
  modelValue?: Portfolio[]
  routeFetch: routeType
  isLoadingSubmit?: boolean
  isLoadingComponent?: boolean
  withQuantity?: boolean
  label_result?: string
  valueToRefetch?: string
  idxSubmitSuccess?: number
  key_quantity?: string
  head_label?: string
  tabs?: Array<{ label: string, routeFetch: routeType }>
}>(), {
  key_quantity: 'quantity_selected',
  head_label: 'Selected Products'
})

const emits = defineEmits<{
  (e: "update:modelValue", val: Portfolio[]): void
  (e: "close"): void
  (e: "after-delete"): void
  (e: "on-select"): void
}>()

interface Portfolio {
  id: number
  name: string
  code: string
  image: any
  gross_weight: string
  price: number
  currency_code: string
  reference?: string
  no_code?: boolean
  no_price?: boolean
  is_recommended?: boolean
}

const layout = inject('layout', layoutStructure)
const locale = inject('locale', null)

const isLoadingFetch = ref(false)
const showDialog = ref(false)

const queryPortfolio = ref('')
const list = ref<Portfolio[]>([])
const meta = ref()
const link = ref()

// --- States ---
// committed selections (parent synced)
const committedProducts = ref<Portfolio[]>(props.modelValue || [])
// temporary selections (inside dialog)
const selectedProduct = ref<Portfolio[]>([])

const activeTab = ref(0)

// --- helpers
const openDialog = () => {
  // copy committed -> temp when opening
  selectedProduct.value = [...committedProducts.value]
  showDialog.value = true
}

const confirmSelection = () => {
  committedProducts.value = [...selectedProduct.value]
  emits("update:modelValue", committedProducts.value)
  emits("on-select")
  showDialog.value = false
}

const getPortfoliosList = async (url?: string) => {
  isLoadingFetch.value = true
  try {
    const tabRoute = props.tabs?.[activeTab.value]?.routeFetch || props.routeFetch
    const currentTab = props.tabs?.[activeTab.value]
    console.log('sdsd', props.routeFetch)
    const params: Record<string, any> = { ...tabRoute.parameters }

    // ✅ Only append search if tab has "search: true"
    if (currentTab?.search && queryPortfolio.value) {
      params['filter[global]'] = queryPortfolio.value
    }

    const urlToFetch = url || route(tabRoute.name, params)

    const response = await axios.get(urlToFetch)
    list.value = response.data.data
    meta.value = response?.data.meta || null
    link.value = response?.data.links || null
  } catch (e) {
    console.error('Error', e)
    notify({
      title: trans("Something went wrong."),
      text: trans("Error while getting the portfolios list."),
      type: "error"
    })
  } finally {
    isLoadingFetch.value = false
  }
}


const debounceGetPortfoliosList = debounce(() => (getPortfoliosList()), 500)

const compSelectedProduct = computed(() =>
  selectedProduct.value.map((item: Portfolio) => item.id)
)

const selectProduct = (item: Portfolio) => {
  const exists = selectedProduct.value.find(p => p.id === item.id)
  if (exists) {
    selectedProduct.value = selectedProduct.value.filter(p => p.id !== item.id)
  } else {
    const newItem: any = { ...item }
    if (props.withQuantity && !newItem[props.key_quantity]) {
      newItem[props.key_quantity] = 1
    }
    selectedProduct.value = [...selectedProduct.value, newItem]
  }
}

const deleteProduct = (id: number) => {
  selectedProduct.value = selectedProduct.value.filter(p => p.id !== id)
}

const isAllSelected = computed(() => {
  if (list.value.length === 0) return false
  return list.value.every(item =>
    selectedProduct.value.some(selected => selected.id === item.id)
  )
})

const selectAllProducts = () => {
  if (isAllSelected.value) {
    const currentPageIds = list.value.map(item => item.id)
    selectedProduct.value = selectedProduct.value.filter(
      selected => !currentPageIds.includes(selected.id)
    )
  } else {
    const currentSelectedIds = selectedProduct.value.map(item => item.id)
    const productsToAdd = list.value.filter(
      item => !currentSelectedIds.includes(item.id)
    )
    selectedProduct.value = [...selectedProduct.value, ...productsToAdd]
  }
}

const changeTab = (index: number) => {
  activeTab.value = index
  queryPortfolio.value = ''
  getPortfoliosList()
}

// lifecycle
onMounted(() => getPortfoliosList())
onUnmounted(() => {
  list.value = []
  meta.value = null
  link.value = null
  queryPortfolio.value = ''
})

const clearAll = () => {
  selectedProduct.value = []
}

const deleteFormCommited = (item) => {
  committedProducts.value = committedProducts.value.filter(p => p.id !== item.id); emits('update:modelValue', [...committedProducts.value]), emits('after-delete')
}

</script>

<template>
  <div>
    <!-- Selected list -->
    <div class="">
      <div class="flex justify-between">
        <div>
          <h3 class="font-semibold mb-2">{{ trans(props.head_label) }}</h3>
        </div>
        <div>
          <Button v-if="committedProducts.length" @click="openDialog" :label="'select'" size="xs" type="dashed"
            :icon="faPlus"></Button>
        </div>
      </div>

      <div v-if="committedProducts.length" class=" rounded-md overflow-hidden">
        <slot name="committed-list" :list="committedProducts" :deleteFormCommited="deleteFormCommited" >
          <div v-for="item in committedProducts" :key="item.id"
            class="flex items-center justify-between gap-4 p-2 border-b last:border-b-0 bg-white hover:bg-gray-50">

            <!-- Info -->
            <div class="flex items-center gap-3">
              <Image v-if="item.image" :src="item.image.thumbnail" class="w-12 h-12 rounded object-cover" />
              <div>
                <div class="font-medium leading-none">{{ item.name }}</div>
                <div class="flex justify-beetween mt-1 gap-5">
                  <div class="text-xs text-gray-500">{{ item.code || '-' }}</div>
                  <div v-if="item.value" class="text-xs text-gray-500">
                    {{ locale.currencyFormat(layout.app?.currency?.code, item.value || 0) }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Quantity + Delete -->
            <div  class="flex items-center gap-2">
              <NumberWithButtonSave v-if="withQuantity" :key="item.id + '-' + (item[props.key_quantity] || 1)"
                :modelValue="item[props.key_quantity] || 1" :bindToTarget="{ min: 1 }"
                @update:modelValue="(val: number) => { item[props.key_quantity] = val; emits('update:modelValue', [...committedProducts]) }"
                noUndoButton noSaveButton parentClass="w-min" />
              <button class="text-red-500 hover:text-red-700 px-4"
                @click="()=>deleteFormCommited(item)">
                <FontAwesomeIcon :icon="faTrashAlt" />
              </button>
            </div>
          </div>
        </slot>

      </div>
      <div v-else>
        <div class="border rounded-md bg-gray-50 p-6 text-center text-gray-500">
          <p class="font-medium">No Data Available</p>
          <p class="text-sm text-gray-400 mb-4">Please add an item to see content here.</p>
          <div><Button @click="openDialog" :label="'select'" size="xs" type="dashed" :icon="faPlus"></Button></div>
        </div>
      </div>
    </div>

    <!-- Dialog -->
    <Dialog v-model:visible="showDialog" modal header="Select Products" :style="{ width: '80vw', maxWidth: '1200px' }"
      :content-style="{ overflow: 'hidden', paddingLeft: '20px', paddingRight: '20px', }" @hide="$emit('close')">

      <div class="relative isolate">
        <div v-if="isLoadingSubmit"
          class="flex justify-center items-center text-7xl text-white absolute z-10 inset-0 bg-black/40">
          <LoadingIcon />
        </div>

        <!-- Tabs -->
        <div v-if="tabs?.length" class="flex gap-4 mb-4 border-b">
          <div v-for="(tab, index) in tabs" :key="index" @click="changeTab(index)"
            class="cursor-pointer px-4 py-2 -mb-px font-medium border-b-2" :class="activeTab === index
              ? 'text-indigo-600 border-indigo-600'
              : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300'">
            {{ trans(tab.label) }}
          </div>
        </div>

        <!-- search -->
        <div class="mb-2">
          <!-- search -->
          <div v-if="tabs?.length && tabs[activeTab]?.search" class="mb-2">
            <PureInput v-model="queryPortfolio" @update:modelValue="() => debounceGetPortfoliosList()"
              :placeholder="trans('Input to search')" />
          </div>

        </div>

        <!-- list + pagination -->
        <div class="h-full md:h-[570px] text-base font-normal">
          <div class="col-span-4 pb-8 md:pb-2 h-fit overflow-auto flex flex-col">

            <!-- header -->
            <div class="flex justify-between items-center">
              <div class="font-semibold text-lg py-1">
                {{ props.label_result ?? trans("Result") }} ({{ locale?.number(meta?.total || 0) }})
              </div>
              <div class="flex gap-2">
                <div @click="selectAllProducts"
                  :class="isAllSelected ? 'text-green-400' : 'cursor-pointer text-green-600 hover:text-green-700 hover:underline'">
                  {{ trans("Select :number products in this page", { number: list.length }) }}
                </div>
                <div v-if="compSelectedProduct.length" @click="clearAll"
                  class="cursor-pointer text-red-400 hover:text-red-600 hover:underline">
                  {{ trans('Clear :number selections', { number: compSelectedProduct.length }) }}
                  <FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
                </div>
              </div>
            </div>

            <div class="border-t border-gray-300 mb-1"></div>

            <!-- list -->
            <div class="h-full md:h-[400px] overflow-auto py-2 relative">
              <div class="grid grid-cols-2 md:grid-cols-3 gap-3 pb-2">
                <template v-if="!isLoadingFetch">
                  <template v-if="list.length > 0">
                    <div v-for="(item, index) in list" :key="index" @click="selectProduct(item)"
                      class="relative h-fit rounded cursor-pointer p-2 flex flex-col md:flex-row gap-x-2 border" :class="compSelectedProduct.includes(item.id)
                        ? 'bg-indigo-100 border-indigo-300'
                        : 'bg-white hover:bg-gray-200 border-gray-300'">

                      <FontAwesomeIcon v-if="compSelectedProduct.includes(item.id)" icon="fas fa-check-circle"
                        class="bottom-2 right-2 absolute text-green-500" fixed-width aria-hidden="true" />

                      <slot name="product" :item="item">
                        <Image v-if="item.image" :src="item.image?.thumbnail"
                          class="w-16 h-16 overflow-hidden mx-auto md:mx-0 mb-4 md:mb-0" imageCover :alt="item.name" />
                        <div class="flex flex-col justify-between w-full">
                          <div class="flex items-center gap-2">
                            <div class="font-semibold leading-none mb-1">{{ item.name || 'no name' }}</div>
                          </div>
                          <div v-if="!item.no_code" class="text-xs text-gray-400 italic">
                            {{ item.code || 'no code' }}
                          </div>
                          <div v-if="item.reference" class="text-xs text-gray-400 italic">
                            {{ item.reference }}
                          </div>
                          <div v-if="item.gross_weight" class="text-xs text-gray-400 italic">
                            {{ item.gross_weight }}
                          </div>
                          <div v-if="!item.no_price && item.price" class="text-xs text-gray-x500">
                            {{ locale?.currencyFormat(item.currency_code || 'usd',
                              item.price || 0) }}
                          </div>
                          <NumberWithButtonSave v-if="withQuantity"
                            :modelValue="selectedProduct.find(p => p.id === item.id)?.[props.key_quantity] || 1"
                            :bindToTarget="{ min: 1 }" @update:modelValue="(val: number) => {
                              const target = selectedProduct.find(p => p.id === item.id)
                              if (target) {
                                target[props.key_quantity] = val
                              } else {
                                const newItem: any = { ...item, [props.key_quantity]: val }
                                selectedProduct.push(newItem)
                              }
                            }" noUndoButton noSaveButton parentClass="w-min" />

                        </div>
                      </slot>
                    </div>
                  </template>
                  <div v-else class="text-center text-gray-500 col-span-3">
                    {{ trans("No Results found") }}
                  </div>
                </template>
                <div v-else v-for="(item, index) in 6" :key="index"
                  class="rounded cursor-pointer w-full h-20 flex gap-x-2 border skeleton" />
              </div>
            </div>

            <!-- pagination -->
            <Pagination v-if="meta" :on-click="getPortfoliosList" :has-data="true" :meta="meta"
              :per-page-options="[]" />
          </div>
        </div>
      </div>

      <!-- footer -->
      <template #footer>
        <Button type="secondary" @click="showDialog = false" label="Cancel"></Button>
        <Button type="create" @click="confirmSelection" label="Select"></Button>
      </template>
    </Dialog>
  </div>
</template>
