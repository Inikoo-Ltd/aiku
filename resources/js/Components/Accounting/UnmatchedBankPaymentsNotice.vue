<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 17 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationTriangle } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

defineProps<{
	notice: { count: number; currency: string; route: { name: string; parameters: Record<string, string> } }
}>()

const locale = useLocaleStore()
</script>

<template>
	<div class="mx-3 mt-3 md:mx-6">
		<Link
			:href="route(notice.route.name, notice.route.parameters)"
			class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
		>
			<FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-600" fixed-width aria-hidden="true" />
			<span class="whitespace-nowrap">{{ ctrans("Bank payments to match") }}</span>
			<span class="font-semibold tabular-nums">{{ locale.number(notice.count) }}</span>
			<span class="text-xs text-amber-800 dark:text-amber-200">{{ ctrans("Payments the bank received that look like they come from your customers and need a person to place them") }}</span>
		</Link>
	</div>
</template>
