<script setup lang="ts">
import { inject } from "vue"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop"
import Blueprint from "@/Components/CMS/Webpage/Cta3/Blueprint"
import Button from "@/Components/Elements/Buttons/Button.vue"

const props = defineProps<{
  modelValue: any
  webpageData?: any
  blockData?: Object
  indexBlock?: number
  screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
  (e: "update:modelValue", value: string): void
  (e: "autoSave"): void
}>()

const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || []

// Define preferred image ratio, e.g., 16:9
const imageSettings = {
	key: ["image", "source"],
	stencilProps: {
		aspectRatio: [16 / 9],
		movable: true,
		scalable: true,
		resizable: true,
	},
}

</script>

<template>
  <div id="cta3">
    <div
      class="relative grid rounded-lg  shadow-lg"
      :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(modelValue.container?.properties, screenType)
      }"
    >
      <!-- Image section -->
      <div
        class="absolute inset-0 z-0 cursor-pointer transition-transform duration-300 hover:scale-105"
        :style="{
          ...getStyles(modelValue.image.properties, screenType),
          aspectRatio: imageSettings.aspectRatio
        }"
		    @dblclick.stop="() => sendMessageToParent('uploadImage', imageSettings)"
        @click="() => {
          sendMessageToParent('activeBlock', indexBlock)
          sendMessageToParent('activeChildBlock', bKeys[0])
        }"
      >
        <Image
          :src="modelValue?.image?.source
            ? modelValue.image.source
            : { original: 'https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png' }"
          :alt="modelValue?.image?.alt ?? 'CTA Image'"
          imageCover
          class="rounded-lg object-cover w-full h-full"
        />
      </div>

      <!-- Text + button overlay -->
    
      <div
        :style="getStyles(modelValue.container.properties?.block, screenType)"
        class="relative z-10 w-full bg-white  p-6 backdrop-blur-xs sm:flex sm:flex-col sm:items-start lg:w-96 rounded-lg shadow-md"
        @click="() => {
          sendMessageToParent('activeBlock', indexBlock)
          sendMessageToParent('activeChildBlock', bKeys[1])
        }"
      >
        <div class="text-center lg:text-left text-gray-700 pr-3 mb-4 w-full">
          <Editor
            v-if="modelValue?.text"
            v-model="modelValue.text"
            @update:modelValue="() => emits('autoSave')"
            @focus="() => {
              sendMessageToParent('activeBlock', indexBlock)
              sendMessageToParent('activeChildBlock', bKeys[1])
            }"
            :uploadImageRoute="{
              name: webpageData.images_upload_route.name,
              parameters: {
                ...webpageData.images_upload_route.parameters,
                modelHasWebBlocks: blockData?.id
              },
            }"
            :cropOptions="{ aspectRatio: imageRatio }"
          />
        </div>

        <Button
          :injectStyle="{
            ...getStyles(modelValue?.button?.container?.properties, screenType),
            width: 'fit-content !important'
          }"
          :label="modelValue?.button?.text"
          @click.stop="() => {
            sendMessageToParent('activeBlock', indexBlock)
            sendMessageToParent('activeChildBlock', bKeys[2])
          }"
        />
      </div>
    </div>
  </div>
</template>
