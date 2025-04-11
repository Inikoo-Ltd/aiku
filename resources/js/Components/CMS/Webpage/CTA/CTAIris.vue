<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { FieldValue } from "@/types/webpageTypes"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCube, faLink, faImage } from "@fal"

library.add(faCube, faLink, faImage)

const props = defineProps<{
	fieldValue: FieldValue
	webpageData?: any
	blockData?: Object,
	screenType: 'mobile' | 'tablet' | 'desktop'
}>()


</script>

<template>
	<!-- {{ fieldValue?.container?.properties }} -->
	<div class="relative" :style="getStyles(fieldValue?.container?.properties,screenType)">
		<div class="relative h-80 overflow-hidden md:absolute md:left-0 md:h-full md:w-1/3 lg:w-1/2">
			<template v-if="fieldValue?.image?.source">
				<Image :src="fieldValue?.image?.source" :imageCover="true" :alt="fieldValue?.image?.alt"
					:imgAttributes="fieldValue?.image?.attributes" :style="getStyles(fieldValue?.image?.properties)" />
			</template>
			<template v-else>
				<img src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png"
					:alt="fieldValue?.image?.alt" class="h-full w-full object-cover" />
			</template>
		</div>

		<div class="py-16 sm:py-32 lg:px-8 lg:py-40">
			<div class="pl-6 pr-6 md:ml-auto md:w-2/3 md:pl-16 lg:w-1/2 lg:pl-24 lg:pr-0 xl:pl-32">
				<div v-html="fieldValue?.text" />
				<div class="flex justify-center">
				<div>
					<a typeof="button" :href="fieldValue?.button?.link?.href" :target="fieldValue?.button?.link?.target"
						:style="getStyles(fieldValue?.button?.container?.properties)"
						class="mt-10 flex items-center justify-center w-64 mx-auto gap-x-6">
						{{ fieldValue?.button?.text }}
					</a>
				</div>
			</div>
			</div>
		</div>
	</div>
</template>
