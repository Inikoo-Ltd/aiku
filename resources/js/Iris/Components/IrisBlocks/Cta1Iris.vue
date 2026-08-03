<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@common/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { ctaImageBoxCss } from "@/Iris/Composables/useCtaImageBoxCss"
import { FieldValue } from "@/types/webpageTypes"
import { inject, computed } from 'vue'
import { faCube, faLink, faImage } from "@fal"
import Button from "@iris/Components/IrisButton.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import { get, isPlainObject } from 'lodash-es'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSpinnerThird } from "@fas"

library.add(faCube, faLink, faImage, faSpinnerThird)

const props = defineProps<{
	fieldValue: FieldValue
	webpageData?: any
	blockData?: Object,
	screenType: 'mobile' | 'tablet' | 'desktop'
	indexBlock?: number | string
	code?: string
}>()

const layout: any = inject("layout", {})    


const valueForField = computed(() => {
	const rawVal = get(props.fieldValue, ['column_position'])
	if (!isPlainObject(rawVal)) return rawVal

	const view = props.screenType!
	return rawVal?.[view] ?? rawVal?.desktop ?? 'Image-left'
})

const isImageLeft = computed(() => valueForField.value === 'Image-left')

const blockDomId = computed(() => props.fieldValue?.id ? props.fieldValue.id : 'cta1' + props.indexBlock)
const imageBoxCss = computed(() => ctaImageBoxCss(blockDomId.value, props.fieldValue?.image?.container?.properties?.dimension))
const imageBoxStyle = computed(() => {
	const { height, width, ...rest } = getStyles(props.fieldValue?.image?.container?.properties, props.screenType) || {}
	return rest
})
</script>

<template>
	<div :id="blockDomId" class="w-full" component="cta1">
		<component :is="'style'" v-if="imageBoxCss">{{ imageBoxCss }}</component>
		<div :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(fieldValue.container?.properties, screenType),
		}">
			<div class="grid grid-cols-1 md:grid-cols-2 w-full min-h-[250px] md:min-h-[400px]">
				<component 
					:is="fieldValue?.image?.link?.href ? LinkIris : 'div'" 
					:href="fieldValue?.image?.link?.href" 
					:target="fieldValue?.image?.link?.target"
					:type="fieldValue?.image?.link?.type"
					class="relative cursor-pointer overflow-hidden w-full cta-image-slot"
				:class="[
					!fieldValue.image.source ? '' : 'h-[250px] sm:h-[300px] md:h-[400px]',
					isImageLeft ? 'order-1' : 'order-2']"
					:style="imageBoxStyle"
				>
					<template #default="{ isLoading } = { isLoading: false }">
						<Image :src="fieldValue.image.source" :imageCover="true"
							:alt="fieldValue.image.alt || 'Image preview'"
							class="absolute inset-0 w-full h-full object-fill"
							:imgAttributes="fieldValue.image.attributes"
							:height="getStyles(fieldValue?.image?.container?.properties, 'desktop', false)?.height"
							:width="getStyles(fieldValue?.image?.container?.properties, 'desktop', false)?.width"
							:preload="Number(indexBlock) === 0"
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

				<div class="flex flex-col justify-center m-auto p-4" :class="isImageLeft ? 'order-2' : 'order-1'"
					:style="getStyles(fieldValue?.text_block?.properties, screenType)">
					<div class="max-w-xl w-full">
						<div v-html="fieldValue.text"></div>
						<div v-if="fieldValue.button?.use_button ?? true" class="flex justify-center mt-6">
							<LinkIris :href="fieldValue?.button?.link?.href"
								:canonical_url="fieldValue?.button?.link?.canonical_url"
								:target="fieldValue?.button?.link?.taget" typeof="button"
								:type="fieldValue?.button?.link?.type">
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
