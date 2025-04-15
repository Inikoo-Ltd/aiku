<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"

import { Link, router } from "@inertiajs/vue3"
import { useLocaleStore } from "@/Stores/locale"

defineProps<{
	data: object
	tab: string
	status?: string
}>()


</script>

<template>
	<div class="h-min">
		<Table :resource="data" :name="tab" class="mt-5" :is-check-box="false">
			<template #cell(description)="{ item }">
				<div
					v-if="
						item.description?.model ||
						item.description?.title ||
						item.description?.after_title
					">
					<span v-if="item.description?.model">{{ item.description.model }}:</span>
					<Link
						v-if="item.description?.title && item.description.route?.name"
						:href="
							route(item.description.route?.name, item.description.route?.parameters)
						"
						class="primaryLink">
						{{ item.description.title }}
					</Link>
					<span v-else>&nbsp;{{ item.description.title }}</span>

					<div v-if="item.description.after_title" class="text-gray-400 italic text-xs">
						({{ item.description.after_title }})
					</div>
				</div>

				<div v-else>
					<span class="text-gray-400 italic text-xs">data unavailable</span>
                </div>
			</template>

			<template #cell(code)="{ item }">
				<div>
					{{ item.code }} <br />
					<span class="text-gray-400">({{ item.name }})</span>
				</div>
			</template>
			<template #cell(net_amount)="{ item }">
				<div class="text-gray-500">
					{{ useLocaleStore().currencyFormat(item.currency_code, item.net_amount) }}
				</div>
			</template>
		</Table>
	</div>
</template>
