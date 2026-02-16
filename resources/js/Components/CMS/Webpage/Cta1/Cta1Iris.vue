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
import { inject, computed } from 'vue'
import { faCube, faLink, faImage } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"
import { get, isPlainObject } from 'lodash-es'

library.add(faCube, faLink, faImage)

const props = defineProps<{
	fieldValue: FieldValue
	webpageData?: any
	blockData?: Object,
	screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const layout: any = inject("layout", {})


const valueForField = computed(() => {
	const rawVal = get(props.fieldValue, ['column_position'])
	if (!isPlainObject(rawVal)) return rawVal

	const view = props.screenType!
	return rawVal?.[view] ?? rawVal?.desktop ?? 'Image-left'
})

const isImageLeft = computed(() => valueForField.value === 'Image-left')
</script>

<template>
	<div :id="fieldValue?.id ? fieldValue?.id  : 'cta1'" class="w-full" component="cta1">
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
					class="relative cursor-pointer overflow-hidden w-full" 
				:class="[
					!fieldValue.image.source ? '' : 'h-[250px] sm:h-[300px] md:h-[400px]',
					isImageLeft ? 'order-1' : 'order-2']"
					:style="getStyles(fieldValue?.image?.container?.properties, screenType)"
				>
					<Image :src="fieldValue.image.source" :imageCover="true"
						:alt="fieldValue.image.alt || 'Image preview'"
						class="absolute inset-0 w-full h-full object-fill"
						:imgAttributes="fieldValue.image.attributes" 
						:height="getStyles(fieldValue?.image?.container?.properties, screenType, false)?.height"
						:width="getStyles(fieldValue?.image?.container?.properties, screenType, false)?.width"
						/>
				</component>

				<div class="flex flex-col justify-center m-auto p-4" :class="isImageLeft ? 'order-2' : 'order-1'"
					:style="getStyles(fieldValue?.text_block?.properties, screenType)">
					<div class="max-w-xl w-full">
						<div v-html="fieldValue.text"></div>
						<div class="flex justify-center mt-6">
							<LinkIris :href="fieldValue?.button?.link?.href"
								:canonical_url="fieldValue?.button?.link?.canonical_url"
								:target="fieldValue?.button?.link?.taget" typeof="button"
								:type="fieldValue?.button?.link?.type">
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
