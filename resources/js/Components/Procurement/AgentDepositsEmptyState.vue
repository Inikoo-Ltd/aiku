<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFileInvoice, faHandHoldingUsd } from "@fal"
import { ctrans } from "@/Composables/useTrans"

library.add(faHandHoldingUsd, faFileInvoice)

withDefaults(
	defineProps<{
		title: string
		filtered?: boolean
		icon?: string
		description?: string
	}>(),
	{ icon: "fal fa-hand-holding-usd" }
)
</script>

<template>
	<div class="flex flex-col items-center px-6 py-12 text-center">
		<span class="flex h-12 w-12 items-center justify-center rounded-full bg-[--app-accent-soft] text-[--app-accent]">
			<FontAwesomeIcon :icon="icon" class="text-xl" fixed-width aria-hidden="true" />
		</span>
		<h2 class="mt-4 text-base font-semibold text-gray-900">{{ title }}</h2>
		<p v-if="filtered" class="mt-2 max-w-md text-sm text-gray-500">
			{{ ctrans("Nothing matches this search or filter. Clear it to see every record.") }}
		</p>
		<p v-else class="mt-2 max-w-lg text-sm leading-relaxed text-gray-500">
			{{ description ?? ctrans("A deposit is money you pay a sub-supplier up front for one of our purchase orders. Once it is recorded here, the organisation that placed the order reimburses you through a deposit request, item by item, until the request is settled.") }}
		</p>
	</div>
</template>
