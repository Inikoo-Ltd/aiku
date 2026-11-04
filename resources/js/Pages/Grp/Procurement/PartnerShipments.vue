<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFileInvoice, faFileExcel } from "@fal"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
library.add(faFileInvoice, faFileExcel)

defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: object
}>()
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<Table :resource="data">
		<template #cell(documents)="{ item }">
			<div class="flex flex-wrap gap-2">
				<a v-for="document in item.documents" :key="document.reference" :href="document.url" download>
					<Button type="tertiary" size="xs" :label="`${document.reference} · ${document.label}`">
						<template #icon>
							<FontAwesomeIcon :icon="faFileExcel" class="text-green-600" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</a>
			</div>
		</template>
		<template #cell(date)="{ item }">
			<span class="whitespace-nowrap">{{ item.date }}</span>
		</template>
	</Table>
</template>
