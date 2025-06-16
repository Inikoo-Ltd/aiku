<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from "@/Composables/styles"

library.add(faCube, faLink)

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Object
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: string | number): void
	(e: "autoSave"): void
}>()
</script>

<template>
	<div :style="getStyles(modelValue.container.properties)">
		<div
			class="relative  px-6 py-24 text-center  l sm:px-16">
			<Editor
				v-model="modelValue.headline"
				@update:modelValue="() => emits('autoSave')"
				:uploadImageRoute="{
					name: webpageData.images_upload_route.name,
					parameters: {
						...webpageData.images_upload_route.parameters,
						modelHasWebBlocks: blockData?.id,
					}
				}"
			/>

			<div class="mt-10 flex items-center justify-center gap-x-6">
				<div
					typeof="button"
					:style="getStyles(modelValue.button.container.properties)"
					class="mt-10 flex items-center justify-center w-64 mx-auto gap-x-6">
					{{ modelValue.button.text }}
				</div>
			</div>
		</div>
	</div>
</template>
