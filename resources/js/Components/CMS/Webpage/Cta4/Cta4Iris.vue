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
import LinkIris from "@/Components/Iris/LinkIris.vue"

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
	<div id="cta4" class="w-full">
		<div :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(fieldValue.container?.properties, screenType),
		}">
			<div class="grid w-full grid-cols-1 md:grid-cols-2 gap-2 md:gap-4 min-h-[auto] md:min-h-[400px]">
				<!-- 🖼️ Image Block -->
				<div 
					class="relative w-full cursor-pointer overflow-hidden order-1 md:order-2  md:aspect-auto" 
					:class="!fieldValue.image.source ? '' : ' h-[250px] sm:h-[300px] md:h-[400px]'"
					:style="getStyles(fieldValue.image.properties, screenType)" 
				>
					<Image 
						:src="fieldValue.image.source" 
						:imageCover="true"
						:alt="fieldValue.image.alt || 'Image preview'"
						class="absolute inset-0 w-full h-full object-cover" 
						:imgAttributes="fieldValue.image.attributes"
					/>
				</div>

				<!-- 📝 Text & Button Block -->
				<div class="flex flex-col justify-center m-auto order-2 md:order-1"
					:style="getStyles(fieldValue?.text_block?.properties, screenType)">
					<div class="max-w-xl w-full">
						<div v-html="fieldValue.text" class="mb-6"></div>
						<div class="flex justify-center">
							<LinkIris :href="fieldValue?.button?.link?.href" :target="fieldValue?.button?.link?.taget"
								typeof="button" :type="fieldValue?.button?.link?.type"
								:canonical_url="fieldValue?.button?.link?.canonical_url">
								<template #default>
									<Button
										:injectStyle="getStyles(fieldValue?.button?.container?.properties, screenType)"
										:label="fieldValue?.button?.text" />
								</template>
							</LinkIris>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
