<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInventory, faArrowRight, faBox, faClock, faCameraRetro, faPaperclip, faCube, faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign, faGripHorizontal, faTrashAlt } from "@fal"
import { computed, ref } from "vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import type { Navigation } from "@/types/Tabs"
import TradeUnitFamiliesShowcase from "@/Components/Goods/TradeUnitFamiliesShowcase.vue"
import { routeType } from "@/types/route"
import ListSelector from "@/Components/ListSelector.vue"
import TableTradeUnits from "@/Components/Tables/Grp/Goods/TableTradeUnits.vue"
import { useTabChange } from "@/Composables/tab-change"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n" // ✅ import trans

// PrimeVue
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(
  faInventory, faArrowRight, faBox, faClock, faCameraRetro, faPaperclip, faCube,
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
  }
  showcase?: object,
  trade_units?: Object
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const tradeUnits = ref<any[]>([])
const loadingAttach = ref(false)
const isModalOpen = ref(false)

const component = computed(() => {
  const components = {
    showcase: TradeUnitFamiliesShowcase,
    trade_units: TableTradeUnits
  }
  return components[currentTab.value]
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
</script>

<template>
  <Head :title="capitalize(title)" />

  <PageHeading :data="pageHead">
    <template #other>
      <Button @click="isModalOpen = true" label="Add Trade Unit" />
    </template>
  </PageHeading>

  <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

  <component :is="component" :data="props[currentTab]" :tab="currentTab" />

  <!-- PrimeVue Dialog -->
  <Dialog v-model:visible="isModalOpen" modal header="Attach Trade Units" :style="{ width: '50vw' }">
    <ListSelector
      v-model="tradeUnits"
      :routeFetch="props.routes.trade_units_route"
      :withQuantity="false"
      head_label="Selected Trade Units"
    >
      <template #committed-list="{ list, deleteFormCommited }">
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
