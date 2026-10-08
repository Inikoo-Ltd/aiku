<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Table from "@/Components/Table/Table.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBan, faUndo } from "@fal"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
library.add(faBan, faUndo)

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: object
	orgPartner: { id: number }
	canEdit: boolean
}>()

const unblockingId = ref<number | null>(null)

function unblock(item: { id: number }) {
	router.delete(
		route("grp.org.procurement.org_partners.show.shopping_list.unblock", [
			route().params["organisation"],
			props.orgPartner.id,
			item.id,
		]),
		{
			preserveScroll: true,
			onStart: () => (unblockingId.value = item.id),
			onFinish: () => (unblockingId.value = null),
		}
	)
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<Table :resource="data">
		<template #cell(quantity_available)="{ item }">
			{{ Number(item.quantity_available) }}
		</template>
		<template #cell(actions)="{ item }">
			<Button
				v-if="canEdit"
				:label="ctrans('Unblock')"
				icon="fal fa-undo"
				:tooltip="ctrans('Suggest this product again when we run low')"
				type="tertiary"
				size="xs"
				:loading="unblockingId === item.id"
				@click="unblock(item)" />
		</template>
	</Table>
</template>
