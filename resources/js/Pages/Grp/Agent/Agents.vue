<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrash, faPencil } from "@fal"

library.add(faTrash, faPencil)

defineProps<{
	title: string
	pageHeading: []
	data: any
}>()

function editRoute(tag: any) {
	return route("grp.org.tags.edit", [route().params.organisation, tag.slug])
}
</script>

<template>
	<Head :title="title" />
	<PageHeading :data="pageHeading" />

	<Table :resource="data" :name="title">
		<template #cell(specialization)="{ item }">
			<span>
				{{ item.specialization?.join(", ") || "-" }}
			</span>
		</template>

		<template #cell(is_online)="{ item }">
			<span class="font-semibold" :class="item.is_online ? 'text-green-600' : 'text-red-600'">
				{{ item.is_online ? "Online" : "Offline" }}
			</span>
		</template>

		<template #cell(action)="{ item }">
			<div class="flex items-center gap-2">
				<!-- EDIT -->
				<a :href="editRoute(item)">
					<Button
						v-tooltip="trans('Edit Agent')"
						type="secondary"
						icon="fal fa-pencil"
						size="s" />
				</a>

				<!-- DELETE -->
				<ModalConfirmationDelete
					:routeDelete="{ name: 'grp.org.agents.delete', parameters: [item.id] }"
					:title="trans('Are you sure you want to delete this agent?')"
					:noLabel="trans('Delete')"
					noIcon="fal fa-trash">
					<template #default="{ changeModel }">
						<Button
							v-tooltip="trans('Delete Agent')"
							@click="() => changeModel()"
							type="negative"
							icon="fal fa-trash"
							size="s" />
					</template>
				</ModalConfirmationDelete>
			</div>
		</template>
	</Table>
</template>
