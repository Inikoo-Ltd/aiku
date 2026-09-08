<script setup lang="ts">
import {
  Disclosure,
  DisclosureButton,
  DisclosurePanel,
} from '@headlessui/vue'

import { getStyles } from '@/Composables/styles'
import { trans } from 'laravel-vue-i18n'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPlus, faMinus } from '@fas'
import { inject, onBeforeUnmount, onMounted, ref } from "vue"
import { useFaqStructuredData } from "@/Iris/Composables/useFaqStructuredData"

library.add(faPlus, faMinus)

const props = defineProps<{
  fieldValue: any
  screenType: 'mobile' | 'tablet' | 'desktop'
  indexBlock: number
  webpageData?: Record<string, unknown>
}>()

const layout: any = inject("layout", {})
const injectedWebpageData = inject<any>("webpage_data", null)

const { mountFaqStructuredData, removeStructuredDataScript } = useFaqStructuredData()
const faqStructuredDataScript = ref<HTMLScriptElement | null>(null)

onMounted(() => {
  if (props.fieldValue?.seo?.is_faq !== true) return

  faqStructuredDataScript.value = mountFaqStructuredData({
    faqs: props.fieldValue?.value,
    webpageData: (props.webpageData ?? injectedWebpageData) as any,
    listId: props.fieldValue?.id ?? props.indexBlock,
  })
})

onBeforeUnmount(() => {
  removeStructuredDataScript(faqStructuredDataScript.value)
})

</script>

<template>
  <div :id="fieldValue?.id ? fieldValue?.id  : 'disclosure'+indexBlock"  component="disclosure">
    <div :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(fieldValue.container?.properties, screenType)
    }">
      <dl class="space-y-0  rounded-md overflow-hidden">
        <Disclosure v-for="(faq, index) in fieldValue.value" :key="index" as="div"
          :class="index !== 0 ? 'border-t border-gray-200' : ''" v-slot="{ open }">
          <dt class="flex items-start justify-between gap-2 px-4 py-3">
            <div class="flex-1">
              <DisclosureButton class="flex w-full !text-left">
                <div v-html="faq.label"></div>
              </DisclosureButton>

            </div>
            <DisclosureButton class="ml-2 flex h-[44px] w-[44px] items-center justify-center transition "
              :name="'disclosure-button-' + index"
              :aria-label="open ? ctrans('Hide answer') : ctrans('Show answer')">
              <font-awesome-icon :icon="open ? 'minus' : 'plus'" aria-hidden="true" />
            </DisclosureButton>
          </dt>
          <DisclosurePanel as="dd" :unmount="false" class="px-4 pb-4 text-base text-gray-600 transition-all duration-300 ease-in-out !text-left">
            <div v-html="faq.description"></div>
          </DisclosurePanel>
        </Disclosure>
      </dl>
    </div>
  </div>
</template>
