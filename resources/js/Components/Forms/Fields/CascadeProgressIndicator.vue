<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck } from "@fal"
import { faSpinnerThird } from "@fad"
import type { CascadeProgress } from "@/Stores/echo-master-product-category"

library.add(faCheck, faSpinnerThird)

defineProps<{
	progress?: CascadeProgress
}>()
</script>

<template>
	<div
		v-if="progress"
		class="flex h-5 items-center justify-end gap-x-1.5 text-xs"
		:class="progress.state === 'done' ? 'text-green-600' : 'text-gray-500'">
		<FontAwesomeIcon
			v-if="progress.state !== 'done'"
			icon="fad fa-spinner-third"
			class="animate-spin"
			fixed-width
			aria-hidden="true" />
		<FontAwesomeIcon v-else icon="fal fa-check" fixed-width aria-hidden="true" />

		<span v-if="progress.state !== 'done'">
			{{ progress.done }}/{{ progress.total }} {{ trans("shops updated") }}
		</span>
		<span v-else>{{ trans("Shops updated") }} ({{ progress.total }})</span>
	</div>
</template>
