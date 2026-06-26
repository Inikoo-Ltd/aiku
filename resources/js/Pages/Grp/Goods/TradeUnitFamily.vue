<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faAtomAlt, faInventory, faArrowRight, faBox, faClock, faCameraRetro, faPaperclip, faCube, faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign, faGripHorizontal, faTrashAlt } from "@fal"
import { computed, onMounted, ref } from "vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import type { Navigation } from "@/types/Tabs"
import TradeUnitFamiliesShowcase from "@/Components/Goods/TradeUnitFamiliesShowcase.vue"
import { routeType } from "@/types/route"
import ListSelector from "@/Components/ListSelector.vue"
import TableTradeUnits from "@/Components/Tables/Grp/Goods/TableTradeUnits.vue"
import { useTabChange } from "@/Composables/tab-change"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import AttachmentManagement from "@/Components/Goods/AttachmentManagement.vue"
import Image from "@common/Components/Image.vue"
import { faHandHoldingMagic, faPlus } from "@far"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import Tag from '@/Components/Tag.vue'
import { Message } from "primevue"
import { faWarning } from "@fortawesome/free-solid-svg-icons"

library.add(
    faAtomAlt,faInventory, faArrowRight, faBox, faClock, faCameraRetro, faPaperclip, faCube,
  faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign, faGripHorizontal, faTrashAlt
)

const props = defineProps<{
  title: string,
  pageHead: PageHeadingTypes
  tabs: {
    current: string;
    navigation: Navigation
  }
  routes?: {
    trade_units_route: routeType
    attach_route: routeType
    assign_brand_tags_route: routeType
  }
  showcase?: {
    brand?: {}
    tags?: []
  },
  trade_units?: Object
  attachments?:any
}>()
console.log(props)
const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const tradeUnits = ref<any[]>([])
const loadingAttach = ref(false)
const loadingMassAssign = ref(false)
const isModalOpen = ref(false)
const isModalOpenMassAssign = ref(false)

const component = computed(() => {
  const components = {
    showcase: TradeUnitFamiliesShowcase,
    trade_units: TableTradeUnits,
    attachments : AttachmentManagement
  }
  return components[currentTab.value]
})

onMounted(() => {
  if (props.showcase?.tags) {
    tags.value = props.showcase.tags;
  }

  if (props.showcase?.brand) {
    brands.value = props.showcase.brand;
  }
})

/**
 * Attach selected trade units
 */
const attachTradeUnit = () => {
  const payload = {
    trade_units: tradeUnits.value.map(item => item.id),
  }

  router.post(
    route(props.routes.attach_route.name, props.routes.attach_route.parameters),
    payload,
    {
      onStart: () => { // ✅ instead of onStart
        loadingAttach.value = true
      },
      onSuccess: () => {
        isModalOpen.value = false
        tradeUnits.value = []
      },
      onError: (error) => {
        notify({
          title: trans("Something went wrong"),
          text: error?.response?.data?.message || trans("Please try again"),
          type: "error",
        })
      },
      onFinish: () => {
        loadingAttach.value = false
      }
    }
  )
}


const brands = ref<any | null>(null)
const tags = ref<any[]>([])

const isModified = computed(() => {
  const brandsChanged = brands.value !== props.showcase.brand

  const tagsChanged =
    tags.value.length !== props.showcase.tags.length ||
    tags.value.some((tag, i) => tag !== props.showcase.tags[i])

  return brandsChanged || tagsChanged
})

const handleMassAssign = () => {
  const payload = {
    brands: brands.value?.id ?? null,
    tags: tags.value.map(item => item.id) ?? [],
  }

  router.post(
    route(props.routes.assign_brand_tags_route.name, props.routes.assign_brand_tags_route.parameters),
    payload,
    {
      onStart: () => {
        loadingMassAssign.value = true
      },
      onSuccess: () => {
        isModalOpenMassAssign.value = false
      },
      onError: (error) => {
        console.error("ERR", error)
        notify({
          title: trans("Something went wrong"),
          text: error?.response?.data?.message || trans("Please try again"),
          type: "error",
        })
      },
      onFinish: () => {
        loadingMassAssign.value = false
      }
    }
  )
}
</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <template #otherBefore>
      <Button @click="isModalOpenMassAssign = true" :icon="faHandHoldingMagic" label="Edit Brand/Tag" :style="'secondary'"/>
      <Button @click="isModalOpen = true" :icon="faPlus" label="Trade Unit"/>
    </template>
  </PageHeading>

  <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
  <component :is="component" :data="props[currentTab]" :tab="currentTab" />
  
  <!-- PrimeVue Dialog -->
  <Dialog v-model:visible="isModalOpenMassAssign" modal header="Attach Brands & Tags" :contentClass="'w-[40vw] lg:w-[35vw]'"
    :dismissableMask="true" :contentStyle="{ maxHeight: 'auto', overflowY: 'visible' }">
    <!-- BRANDS -->
    <Message :severity="'warn'" xclosable="true" class="mb-3 !bg-yellow-100 !text-sm">
      <span class="!text-sm">
        <FontAwesomeIcon :icon="faWarning" />
        {{ trans('Modifying this value would affect all of the corresponding children Trade Units') }}
      </span>
    </Message>
    <div class="mb-3 font-medium">Brands</div>
    <PureMultiselectInfiniteScroll v-model="brands" :fetchRoute="{ name: 'grp.json.brands.index', parameters: {} }"
      :placeholder="trans('Select brand')" valueProp="id" :mode="'single'" :object="true" />

    <!-- TAGS -->
    <div class="mt-5 mb-3 font-medium">Tags</div>

    <!-- Selected Tags -->
    <div class="flex flex-wrap gap-2 mb-3">
      <Tag v-for="tag in tags" :key="tag.id" :label="tag.name" stringToColor>
        <template #closeButton>
          <div class="cursor-pointer px-1 text-red-500" @click="tags = tags.filter(t => t.id !== tag.id)">
            <FontAwesomeIcon icon="fal fa-trash-alt" class="text-xs" />
          </div>
        </template>
      </Tag>
    </div>

    <!-- Tag Selector -->
    <PureMultiselectInfiniteScroll v-model="tags" :fetchRoute="{ name: 'grp.trade_units.tags.index', parameters: {} }"
      :placeholder="trans('Select tag')" :mode="'multiple'" :object="true" />

    <!-- Footer -->
    <template #footer>
      <div class="flex justify-end gap-3 w-full">
        <Button label="Cancel" type="negative" @click="isModalOpenMassAssign = false" />
        <Button type="save" label="Save" @click="handleMassAssign" :loading="loadingMassAssign" :disabled="!isModified"/>
      </div>
    </template>
  </Dialog>
  <!-- PrimeVue Dialog -->
  <Dialog v-model:visible="isModalOpen" modal header="Attach Trade Units" :style="{ width: '50vw' }" :dismissableMask="true">
    <ListSelector
      v-model="tradeUnits"
      :routeFetch="props.routes.trade_units_route"
      :withQuantity="false"
      head_label="Selected Trade Units"
      :enable_search="true"
    >
      <template #committed-list="{ committedProducts : list, deleteFormCommited }">
        <div class="h-full md:h-[400px] overflow-auto relative">
          <div class="grid grid-cols-2 md:grid-cols-3 gap-3 pb-2">
            <div
              v-for="(item, index) in list"
              :key="index"
              class="relative rounded-lg cursor-pointer p-3 flex flex-col md:flex-row gap-3 border transition duration-200 bg-white hover:bg-gray-50 border-gray-200"
            >
              <!-- Image -->
              <Image
                v-if="item.image"
                :src="item.image?.thumbnail"
                class="w-16 h-16 rounded object-cover mx-auto md:mx-0"
                imageCover
                :alt="item.name"
              />

              <!-- Info -->
              <div class="flex flex-col justify-center flex-1 min-w-0">
                <div class="font-semibold text-gray-800 truncate">
                  {{ item.name || "no name" }}
                </div>
                <div v-if="!item.no_code" class="text-xs text-gray-500 truncate">
                  {{ item.code || "no code" }}
                </div>
              </div>

              <!-- Delete button -->
              <button
                class="text-red-500 hover:text-red-700 px-4"
                @click="() => deleteFormCommited(item)"
              >
                <FontAwesomeIcon :icon="faTrashAlt" />
              </button>
            </div>
          </div>
        </div>
      </template>
    </ListSelector>

    <template #footer>
      <div class="flex justify-end gap-3 border-t pt-3 w-full">
        <Button
          label="Cancel"
          type="negative"
          class="!px-5"
          @click="isModalOpen = false"
        />
        <Button
          :loading="loadingAttach"
          type="save"
          :label="`Attach to ${title}`"
          :disabled="tradeUnits.length < 1 || loadingAttach"
          class="!px-6"
          @click="attachTradeUnit"
        />
      </div>
    </template>
  </Dialog>
</template>
