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
import { inject } from 'vue'
import { faCube, faLink, faImage } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"

library.add(faCube, faLink, faImage)

const props = defineProps<{
	fieldValue: FieldValue
	webpageData?: any
	blockData?: Object,
	screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const layout: any = inject("layout", {})
</script>

<template>
	<div id="cta1">
		<div class="grid grid-cols-1 md:grid-cols-2" :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(fieldValue.container?.properties, screenType)
		}">
			<!-- 🖼️ Image Block -->
			<div>
				<div class="w-full flex" :style="getStyles(fieldValue?.image?.container?.properties, screenType)">
					<Image v-if="fieldValue?.image?.source" :src="fieldValue.image.source" :imageCover="true"
						:alt="fieldValue.image.alt || 'Image preview'" :imgAttributes="fieldValue.image.attributes"
						:style="getStyles(fieldValue.image.properties, screenType)" />
					<img v-else
						src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png"
						:alt="fieldValue?.image?.alt || 'Default placeholder image'" />
				</div>
			</div>

			<!-- 📝 Text & Button Block -->
			<div class="flex flex-col justify-center"
				:style="getStyles(fieldValue?.text_block?.properties, screenType)">
				<div class="max-w-xl mx-auto w-full">
					<div v-html="fieldValue.text" class="mb-6"></div>

					<div class="flex justify-center">
						<a :href="fieldValue?.button?.link?.href" :target="fieldValue?.button?.link?.taget"
							typeof="button">
							<Button :injectStyle="getStyles(fieldValue?.button?.container?.properties, screenType)"
								:label="fieldValue?.button?.text" />
						</a>

					</div>
				</div>
			</div>
		</div>
	</div>
</template>
