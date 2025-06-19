<!--
  Author: Raul Perusquia <raul@inikoo.com>
  Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  Copyright (c) 2023, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCube, faLink, faImage } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import type { FieldValue } from "@/types/webpageTypes"

library.add(faCube, faLink, faImage)

const props = defineProps<{
  fieldValue: FieldValue
  webpageData?: any
  blockData?: object
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()
</script>

<template>
  <div
    class="grid grid-cols-1 md:grid-cols-2 relative"
    :style="getStyles(fieldValue.container?.properties, screenType)"
  >
    <!-- 📝 Text & CTA Section -->
    <div
      class="flex flex-col justify-center"
      :style="getStyles(fieldValue?.text_block?.properties, screenType)"
    >
      <div class="max-w-xl w-full mx-auto">
        <!-- Text Content -->
        <div v-html="fieldValue.text" class="mb-6" />

        <!-- CTA Button -->
        <div class="flex justify-center">
          <a
            :href="fieldValue?.button?.link?.href"
            :target="fieldValue?.button?.link?.taget"
            typeof="button"
            class="mt-10 w-64 flex items-center justify-center gap-x-6"
            :style="getStyles(fieldValue?.button?.container?.properties, screenType)"
          >
            {{ fieldValue?.button?.text }}
          </a>
        </div>
      </div>
    </div>
	<div class="w-full flex justify-end"
         :style="getStyles(fieldValue?.image?.container?.properties, screenType)">
      <template v-if="fieldValue?.image?.source">
        <Image
          :src="fieldValue.image.source"
          :imageCover="true"
          :alt="fieldValue.image.alt || 'Image preview'"
          :imgAttributes="fieldValue.image.attributes"
          :style="getStyles(fieldValue.image.properties, screenType)"
        />
      </template>
      <template v-else>
        <img
          src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png"
          :alt="fieldValue?.image?.alt || 'Default placeholder image'"
        />
      </template>
    </div>
  </div>
</template>
