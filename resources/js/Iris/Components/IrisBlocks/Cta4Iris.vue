<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@common/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { ctaImageBoxCss } from "@/Iris/Composables/useCtaImageBoxCss"
import { FieldValue } from "@/types/webpageTypes"
import { inject, computed } from 'vue'
import { faCube, faLink, faImage } from "@fal"
import { faSpinnerThird } from "@fas"
import Button from "@iris/Components/IrisButton.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import { get, isPlainObject } from 'lodash-es'

library.add(faCube, faLink, faImage, faSpinnerThird)

const props = defineProps<{
	fieldValue: FieldValue
	webpageData?: any
	blockData?: Object,
	screenType: 'mobile' | 'tablet' | 'desktop'
	indexBlock?:number |string
}>()

const layout: any = inject("layout", {})

const valueForField = computed(() => {
	const rawVal = get(props.fieldValue, ['column_position'])
	if (!isPlainObject(rawVal)) return rawVal

	const view = props.screenType!
	return rawVal?.[view] ?? rawVal?.desktop ?? 'Image-right'
})

const isImageRight = computed(() => valueForField.value === 'Image-right')

const blockDomId = computed(() => props.fieldValue?.id ? props.fieldValue.id : 'cta4' + props.indexBlock)
const imageBoxCss = computed(() => ctaImageBoxCss(blockDomId.value, props.fieldValue?.image?.properties?.dimension))
const imageBoxStyle = computed(() => {
	const { height, width, ...rest } = getStyles(props.fieldValue?.image?.properties, props.screenType) || {}
	return rest
})
</script>

<template>
	<div :id="blockDomId" component="cta4" class="w-full">
		<component :is="'style'" v-if="imageBoxCss">{{ imageBoxCss }}</component>
		<div :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(fieldValue.container?.properties, screenType),
		}">
			<div class="grid grid-cols-1 md:grid-cols-2 w-full min-h-[250px] md:min-h-[400px]">
				<!-- 🖼️ Image Block -->
				<component 
					:is="fieldValue?.image?.link?.href ? LinkIris : 'div'" 
					:href="fieldValue?.image?.link?.href" 
					:target="fieldValue?.image?.link?.target"
					:type="fieldValue?.image?.link?.type" 
					class="relative cursor-pointer overflow-hidden w-full cta-image-slot"
					:class="[ !fieldValue.image.source ? '' : 'h-[250px] sm:h-[300px] md:h-[400px]', isImageRight ? 'order-2' : 'order-1']"
					:style="imageBoxStyle"
				>
					<template #default="{ isLoading } = { isLoading: false }">
						<Image :src="fieldValue.image.source" :imageCover="true"
							:alt="fieldValue.image.alt || 'Image preview'"
							class="w-full h-full object-cover md:absolute md:inset-0"
							:imgAttributes="fieldValue.image.attributes"
							:height="getStyles(fieldValue?.image?.properties, 'desktop', false)?.height"
							:width="getStyles(fieldValue?.image?.properties, 'desktop', false)?.width"
							:preload="Number(indexBlock) <= 2"
							/>

						<div
							v-if="isLoading"
							class="absolute inset-0 z-10 flex items-center justify-center bg-black/35 pointer-events-none"
						>
							<FontAwesomeIcon
								icon="fas fa-spinner-third"
								spin
								class="text-3xl text-white"
								fixed-width
								aria-hidden="true"
							/>
						</div>
					</template>
				</component>


				<!-- 📝 Text & Button Block -->
				<div class="flex flex-col justify-center m-auto p-4" :class="isImageRight ? 'order-1' : 'order-2'"
					:style="getStyles(fieldValue?.text_block?.properties, screenType)">
					<div class="max-w-xl w-full">
						<div v-html="fieldValue.text" class="mb-6"></div>
						<div class="flex justify-center">
							<LinkIris :href="fieldValue?.button?.link?.href" :target="fieldValue?.button?.link?.taget"
								typeof="button" :type="fieldValue?.button?.link?.type"
								:canonical_url="fieldValue?.button?.link?.canonical_url">
								<template #default="{ isLoading } = { isLoading: false }">
									<Button
										:injectStyle="getStyles(fieldValue?.button?.container?.properties, screenType)"
										:label="fieldValue?.button?.text"
										:loading="isLoading" />
								</template>
							</LinkIris>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
