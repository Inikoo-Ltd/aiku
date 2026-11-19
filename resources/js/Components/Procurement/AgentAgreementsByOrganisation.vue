<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 18 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

defineProps<{
	rows: {
		organisation_code: string
		organisation_name: string
		terms: number
		notes: number
		open_incidents: number
		route: routeType
	}[]
}>()
</script>

<template>
	<div class="px-4 pt-4">
		<div class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Agreement by organisation") }}</div>
		<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
			<Link
				v-for="row in rows"
				:key="row.organisation_code"
				:href="route(row.route.name, row.route.parameters)"
				class="rounded-md border border-gray-200 px-3 py-2 hover:border-[var(--app-accent-muted)] hover:bg-[var(--app-accent-soft)]">
				<div class="flex items-baseline gap-2">
					<span class="whitespace-nowrap font-semibold text-gray-800">{{ row.organisation_code }}</span>
					<span class="truncate text-xs text-gray-500">{{ row.organisation_name }}</span>
				</div>
				<dl class="mt-1 grid grid-cols-3 gap-1 text-xs text-gray-500">
					<div>
						<dt>{{ ctrans("Agreed terms") }}</dt>
						<dd class="tabular-nums text-gray-800">{{ row.terms }}</dd>
					</div>
					<div>
						<dt>{{ ctrans("Pinned notes") }}</dt>
						<dd class="tabular-nums text-gray-800">{{ row.notes }}</dd>
					</div>
					<div>
						<dt>{{ ctrans("Open incidents") }}</dt>
						<dd class="tabular-nums" :class="row.open_incidents ? 'text-red-600' : 'text-gray-800'">{{ row.open_incidents }}</dd>
					</div>
				</dl>
			</Link>
		</div>
	</div>
</template>
