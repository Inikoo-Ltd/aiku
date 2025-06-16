<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref } from "vue"
import { getStyles } from "@/Composables/styles"

library.add(faCube, faLink)

const props = defineProps<{
	fieldValue: {
		headline: String,
		button: {
			text: String
			container: {
				properties: object
			}
		}
	},
	container: {
		properties: Object
	}
	screenType: "mobile" | "tablet" | "desktop"
}>()

</script>

<template>
	<div :style="getStyles(fieldValue.container.properties,screenType)">
		<div class="relative  px-6 py-24 text-center sm:px-16">
			<section v-html="fieldValue.headline" />

			<div class="mt-10 flex items-center justify-center gap-x-6">
				<a  :href="fieldValue?.button?.link?.href" :target="fieldValue?.button?.link?.target" typeof="button" :style="getStyles(fieldValue.button.container.properties,screenType)"
					class="mt-10 flex items-center justify-center w-64 mx-auto gap-x-6">
					{{ fieldValue.button.text }}
			</a>
			</div>
		</div>
	</div>
</template>
