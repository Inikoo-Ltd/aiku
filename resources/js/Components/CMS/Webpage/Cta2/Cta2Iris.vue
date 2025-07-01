<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject } from "vue"
import { getStyles } from "@/Composables/styles"
import { resolveMigrationLink, resolveMigrationHrefInHTML } from "@/Composables/SetUrl"
import Button from "@/Components/Elements/Buttons/Button.vue"

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

const layout: any = inject("layout", {})
const migration_redirect = layout?.iris?.migration_redirect

</script>

<template>
	<div id="cta2">
		<div :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(fieldValue.container?.properties, screenType)
		}">
			<div class="relative  px-6 py-24 text-center sm:px-16">
				<section v-html="resolveMigrationHrefInHTML(fieldValue.headline, migration_redirect)" />

				<div class="mt-10 flex items-center justify-center gap-x-6">
					<a :href="resolveMigrationLink(fieldValue?.button?.link?.href, migration_redirect)"
						:target="fieldValue?.button?.link?.target" typeof="button"
						>
					<Button :injectStyle="getStyles(fieldValue?.button?.container?.properties, screenType)"
						:label="fieldValue?.button?.text"  />
					</a>
				</div>
			</div>
		</div>
	</div>

</template>
