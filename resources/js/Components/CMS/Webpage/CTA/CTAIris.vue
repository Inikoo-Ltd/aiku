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
import { inject } from "vue"
import { resolveMigrationLink, resolveMigrationHrefInHTML } from "@/Composables/SetUrl"
import { layoutStructure } from "@/Composables/useLayoutStructure"

library.add(faCube, faLink, faImage)

const props = defineProps<{
	fieldValue: FieldValue
	webpageData?: any
	blockData?: Object,
	screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const layout = inject('layout', layoutStructure)
const migration_redirect = layout?.iris?.migration_redirect

</script>

<template>
	<div class="grid grid-cols-1 md:grid-cols-2 relative"
		:style="getStyles(fieldValue.container?.properties, screenType)">
		<!-- 📷 Image Column -->
		<div>
			<div class="w-full flex" :style="getStyles(fieldValue?.image?.container?.properties, screenType)" >
				<template v-if="fieldValue?.image?.source">
					<Image :src="fieldValue.image.source" :imageCover="true"
						:alt="fieldValue.image.alt || 'Image preview'" :imgAttributes="fieldValue.image.attributes"
						:style="getStyles(fieldValue.image.properties, screenType)" :class="null" />
				</template>
				<template v-else>
					<img src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png"
						:alt="fieldValue?.image?.alt || 'Default placeholder image'"
						 />
				</template>
			</div>

		</div>

		<!-- 📝 Text & Button Column -->
		<div class="flex flex-col justify-center" :style="getStyles(fieldValue?.text_block?.properties, screenType)">

			<div class="max-w-xl mx-auto w-full">
				<!-- Rich Text Editor -->
				<div v-html="resolveMigrationHrefInHTML(fieldValue.text,migration_redirect)" class="mb-6"></div>

				<!-- CTA Button -->
			
				<div class="flex justify-center">
					<a :href="resolveMigrationLink(fieldValue?.button?.link?.href,migration_redirect)" :target="fieldValue?.button?.link?.taget"
					typeof="button" :style="getStyles(fieldValue?.button?.container?.properties, screenType)"
						class="mt-10 flex items-center justify-center w-64 gap-x-6">
						{{ fieldValue?.button?.text }}
					</a>
				</div>
			</div>
		</div>
	</div>
</template>
