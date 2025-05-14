<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLink, faLongArrowRight } from "@far"
import {
	faArrowAltFromBottom,
	faArrowAltFromTop,
	faWallet,
	faSync,
	faCalendarAlt,
	faEnvelope,
	faPhone,
	faChevronRight,
	faExternalLink,
	faMapMarkerAlt,
	faAddressCard,
} from "@fal"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"

import { faCheck } from "@fas"
import { trans } from "laravel-vue-i18n"
library.add(
	faWallet,
	faLink,
	faSync,
	faCalendarAlt,
	faEnvelope,
	faPhone,
	faChevronRight,
	faExternalLink,
	faMapMarkerAlt,
	faAddressCard,
	faLongArrowRight,
	faCheck,
	faArrowAltFromBottom,
	faArrowAltFromTop
)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	customer: {}
	status: string  // 'approved'
	additional_data: {}
	currency_code: string  // 'EUR'
	address_management: {}
	users: {
		active: number
		inactive: number
	}
}>()

// const locale = inject("locale", aikuLocaleStructure)
// const layout = inject("layout", layoutStructure)


</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead"></PageHeading>

	<div class="px-4 py-5 md:px-6 lg:px-8 grid md:grid-cols-2 gap-x-8 lg:gap-x-12 gap-y-3">
		<!-- Section: Radio -->
		<div class="space-y-3 relative w-full max-w-[500px]">
			<div class="flex justify-between items-center mb-2">
				<h2 class="text-xl font-semibold">Your users</h2>
			</div>

			<div
				class="relative px-5 py-4 ring-1 ring-gray-300 rounded-lg bg-gray-50 shadow-sm space-y-2">
				<div class="text-gray-600">
					<strong class="text-gray-500">{{ trans("Active") }}:</strong>
					{{ users?.active }}
				</div>
				<div class="text-gray-600">
					<strong class="text-gray-500">{{ trans("Inactive") }}:</strong>
					{{ users?.inactive }}
				</div>
			</div>
		</div>

	</div>
</template>
