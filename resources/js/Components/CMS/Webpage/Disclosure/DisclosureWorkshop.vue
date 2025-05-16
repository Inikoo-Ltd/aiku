<script setup lang="ts">
import {
    Disclosure,
    DisclosureButton,
    DisclosurePanel,
} from '@headlessui/vue'

import { getStyles } from '@/Composables/styles'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPlus, faMinus } from '@fas'
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"

library.add(faPlus, faMinus)

const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: Object
	  screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
    (e: "update:modelValue", value: string): void
    (e: "autoSave"): void
}>()


</script>

<template>
  <div :style="getStyles(modelValue.container.properties,screenType)">
    <dl class="space-y-0  rounded-md overflow-hidden">
      <Disclosure
        v-for="(faq, index) in modelValue.value"
        :key="index"
        as="div"
        :class="index !== 0 ? 'border-t border-gray-200' : ''"
        v-slot="{ open }"
      >
        <dt class="flex items-start justify-between gap-2 px-4 py-3">
          <div class="flex-1">
            <EditorV2
              v-model="faq.label"
              @update:modelValue="() => emits('autoSave')"
              :uploadImageRoute="{
                name: webpageData?.images_upload_route?.name ?? '',
                parameters: { modelHasWebBlocks: blockData?.id ?? '' }
              }"
            />
          </div>
          <DisclosureButton
            class="ml-2 flex h-8 w-8 items-center justify-center transition"
          >
            <font-awesome-icon :icon="open ? 'minus' : 'plus'" />
          </DisclosureButton>
        </dt>
        <DisclosurePanel
          as="dd"
          class="px-4 pb-4 text-base text-gray-600 transition-all duration-300 ease-in-out"
        >
          <EditorV2
            v-model="faq.description"
            @update:modelValue="() => emits('autoSave')"
            :uploadImageRoute="{
              name: webpageData?.images_upload_route?.name ?? '',
              parameters: { modelHasWebBlocks: blockData?.id ?? '' }
            }"
          />
        </DisclosurePanel>
      </Disclosure>
    </dl>
  </div>
</template>

