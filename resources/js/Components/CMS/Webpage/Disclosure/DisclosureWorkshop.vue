<script setup lang="ts">
import {
  Disclosure,
  DisclosureButton,
  DisclosurePanel,
} from '@headlessui/vue'

import { inject } from "vue"
import { getStyles } from '@/Composables/styles'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPlus, faMinus } from '@fas'
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import Blueprint from './Blueprint'
import { sendMessageToParent } from "@/Composables/Workshop"
import { ref, nextTick } from "vue"


library.add(faPlus, faMinus)

const props = defineProps<{
  modelValue: any
  webpageData?: any
  blockData?: Object
  indexBlock?: Number
  screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
  (e: "update:modelValue", value: string): void
  (e: "autoSave"): void
}>()
const disclosureBtns = ref<any[]>([])
const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || []

const openDisclosure = async (index: number) => {
  await nextTick()
  const btn = disclosureBtns.value[index]
  if (btn) btn.click()
}
</script>

<template>
  <div id="disclosure">
    <div :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(modelValue.container?.properties, screenType)
    }">
      <dl class="space-y-0  rounded-md overflow-hidden" @click="() => {
        sendMessageToParent('activeBlock', indexBlock)
        sendMessageToParent('activeChildBlock', bKeys[0])
      }
      ">
        <Disclosure v-for="(faq, index) in modelValue.value" :key="index" as="div"
          :class="index !== 0 ? 'border-t border-gray-200' : ''" v-slot="{ open }">
          <dt class="flex items-start justify-between gap-2 px-4 py-3">
            <div class="flex-1">
              <DisclosureButton class="flex w-full" :ref="el => disclosureBtns[index] = el">
                <EditorV2 v-model="faq.label" @focus="openDisclosure(index)"
                  @update:modelValue="() => emits('autoSave')" :uploadImageRoute="{
                    name: webpageData?.images_upload_route?.name ?? '',
                    parameters: { modelHasWebBlocks: blockData?.id ?? '' }
                  }" />
              </DisclosureButton>
            </div>

            <DisclosureButton class="ml-2 flex h-8 w-8 items-center justify-center transition">
              <font-awesome-icon :icon="open ? 'minus' : 'plus'" />
            </DisclosureButton>
          </dt>

          <DisclosurePanel class="px-4 pb-4 text-base text-gray-600">
            <EditorV2 v-model="faq.description" @focus="openDisclosure(index)"
              @update:modelValue="() => emits('autoSave')" :uploadImageRoute="{
                name: webpageData?.images_upload_route?.name ?? '',
                parameters: { modelHasWebBlocks: blockData?.id ?? '' }
              }" />
          </DisclosurePanel>
        </Disclosure>

      </dl>
    </div>
  </div>

</template>
