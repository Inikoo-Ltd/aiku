<script setup lang="ts">
import { onMounted, ref, watch } from "vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import SelectButton from 'primevue/selectbutton'
import ToggleSwitch from 'primevue/toggleswitch'
import { getIrisComponent } from "@/Composables/getIrisComponents"

import { faRocketLaunch, faChevronLeft, faChevronRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { routeType } from "@/types/route"
library.add(faRocketLaunch, faChevronLeft, faChevronRight)

const props = defineProps<{
  modelValue: any
  data: {
    updateRoute: routeType
    web_block_types: {
      data: Array<any>
    }
  }
}>()

const emits = defineEmits(['update:modelValue', 'autoSave'])

const op = ref()
const comment = ref('')
const isLoading = ref(false)

const usedTemplates = ref<any>(null)

const mode = ref({ name: 'Logged In', value: 'login' })
const optionsToogle = ref([
  { name: 'Logged Out', value: 'logout' },
  { name: 'Logged In', value: 'login' },
  { name: 'Membership', value: 'member' }
])

const initializeTemplate = () => {
  if (!props.modelValue.product) {
    props.modelValue.product = {
      code: props.data.web_block_types.data[0].code,
      setting: {
        faqs: true,
        product_specs: true,
        customer_review: true,
        payments_and_policy: true
      }
    }
  }

  usedTemplates.value = props.data.web_block_types.data.find(
    (template) => template.code === props.modelValue.product.code
  ) || props.data.web_block_types.data[0]
}

const selectPreviousTemplate = () => {
  const index = props.data.web_block_types.data.findIndex(
    (item) => item.code === usedTemplates.value?.code
  )
  if (index > 0) {
    usedTemplates.value = props.data.web_block_types.data[index - 1]
  }
}

const selectNextTemplate = () => {
  const index = props.data.web_block_types.data.findIndex(
    (item) => item.code === usedTemplates.value?.code
  )
  if (index < props.data.web_block_types.data.length - 1) {
    usedTemplates.value = props.data.web_block_types.data[index + 1]
  }
}

onMounted(() => {
  initializeTemplate()
})

watch(
  () => props.modelValue?.product?.code,
  (code) => {
    if (code) {
      usedTemplates.value = props.data.web_block_types.data.find(
        (template) => template.code === code
      )
    }
  }
)
</script>

<template>
  <div class="h-[79vh] grid overflow-hidden grid-cols-4">
    <!-- Sidebar -->
    <div class="col-span-1 flex flex-col border-r border-gray-300 shadow-lg relative overflow-auto">
      <div class="px-4 py-3 rounded-t-lg shadow">
        <div class="flex items-center">
          <font-awesome-icon :icon="['fas', 'chevron-left']"
            class="px-4 cursor-pointer text-gray-600 hover:text-gray-800 transition duration-200"
            @click="selectPreviousTemplate" />

          <PureMultiselect
            :options="data.web_block_types.data"
            label="code"
            :object="true"
            :valueProp="'code'"
            :model-value="usedTemplates"
            @update:model-value="(value) => { console.log(value) }"
            :required="true"
            class="mx-2 focus:ring-2 focus:ring-blue-500"
          />

          <font-awesome-icon :icon="['fas', 'chevron-right']"
            class="px-4 cursor-pointer text-gray-600 hover:text-gray-800 transition duration-200"
            @click="selectNextTemplate" />
        </div>
      </div>

      <div class="px-4 py-5 flex-grow">
        <div class="flex justify-center mb-6">
          <SelectButton v-model="mode" :options="optionsToogle" optionLabel="name" aria-labelledby="multiple" />
        </div>

        <div class="px-8">
          <template v-if="modelValue?.product?.setting">
            <div class="py-5 border-t border-gray-300">
              <div class="flex justify-between items-center mb-2">
                <span class="text-lg font-semibold">Show FAQs</span>
                <ToggleSwitch v-model="modelValue.product.setting.faqs" />
              </div>
              <p class="text-xs text-gray-500">Toggle to show or hide frequently asked questions for your product.</p>
            </div>

            <div class="py-5 border-t border-gray-300">
              <div class="flex justify-between items-center mb-2">
                <span class="text-lg font-semibold">Product Specification</span>
                <ToggleSwitch v-model="modelValue.product.setting.product_specs" />
              </div>
              <p class="text-xs text-gray-500">Toggle to show or hide product specifications for your product.</p>
            </div>

            <div class="py-5 border-t border-gray-300">
              <div class="flex justify-between items-center mb-2">
                <span class="text-lg font-semibold">Customer Reviews</span>
                <ToggleSwitch v-model="modelValue.product.setting.customer_review" />
              </div>
              <p class="text-xs text-gray-500">Toggle to show or hide customer reviews for your product.</p>
            </div>

            <div class="py-5 border-t border-gray-300">
              <div class="flex justify-between items-center mb-2">
                <span class="text-lg font-semibold">Payments & Policy</span>
                <ToggleSwitch v-model="modelValue.product.setting.payments_and_policy" />
              </div>
              <p class="text-xs text-gray-500">Toggle to show or hide payment and policy information for your product.</p>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Preview Area -->
    <div class="bg-gray-100 h-full col-span-3 rounded-lg shadow-lg">
      <div class="bg-gray-100 px-6 py-6 h-[79vh] rounded-lg overflow-auto">
        <div :class="usedTemplates?.code ? 'bg-white shadow-md rounded-lg' : ''">
          <section v-if="usedTemplates?.code">
            <component :is="getIrisComponent(usedTemplates.code)" :fieldValue="usedTemplates.data?.fieldValue" />
          </section>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
</style>
