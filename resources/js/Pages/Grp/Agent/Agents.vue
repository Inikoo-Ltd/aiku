<script setup lang="ts">
import { onMounted } from "vue"
import { router } from "@inertiajs/vue3"
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrash, faPencil } from "@fortawesome/free-solid-svg-icons"

library.add(faTrash, faPencil)

const props = defineProps<{
	title: string
	pageHeading: []
	data: any
}>()

function editRoute(id: number) {
	return route("grp.org.crm.agents.edit", [route().params.organisation, id])
}

const waitEchoReady = (callback: Function) => {
	if (window.Echo?.connector?.pusher) {
		callback()
		return
	}
	const interval = setInterval(() => {
		if (window.Echo?.connector?.pusher) {
			clearInterval(interval)
			callback()
		}
	}, 300)
}

onMounted(() => {
	waitEchoReady(() => {
		window.Echo.join("chat-list").listen(".chatlist", () => {
			console.log("🔥 chat-list update — Reloading table")
			router.reload({
				only: ["data"],
			})
		})
	})
})
</script>

<template>
	<Head :title="title" />
	<PageHeading :data="pageHeading" />

	<Table :resource="props.data" :name="'agents'">
		<template #cell(specialization)="{ item }">
			<span class="text-sm">
				{{
					Array.isArray(item.specialization)
						? item.specialization.join(", ")
						: item.specialization || "-"
				}}
			</span>
		</template>

		<template #cell(is_online)="{ item }">
			<span
				class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium"
				:class="item.is_online ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
				<span
					class="w-1.5 h-1.5 rounded-full"
					:class="item.is_online ? 'bg-green-500' : 'bg-red-500'"></span>
				{{ item.is_online ? "Online" : "Offline" }}
			</span>
		</template>

		<template #cell(is_available)="{ item }">
			<span
				class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium"
				:class="
					item.is_available
						? 'bg-green-100 text-green-800'
						: 'bg-yellow-100 text-yellow-800'
				">
				<span
					class="w-1.5 h-1.5 rounded-full"
					:class="item.is_available ? 'bg-green-500' : 'bg-yellow-500'"></span>
				{{ item.is_available ? "Available" : "Busy" }}
			</span>
		</template>

		<template #cell(auto_accept)="{ item }">
			<span
				class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium"
				:class="
					item.auto_accept ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'
				">
				{{ item.auto_accept ? "Yes" : "No" }}
			</span>
		</template>

		<template #cell(action)="{ item }">
			<div class="flex items-center gap-2">
				<a :href="editRoute(item.id)">
					<Button
						v-tooltip="trans('Edit Agent')"
						type="secondary"
						icon="fa-pencil"
						size="s" />
				</a>
				<ModalConfirmationDelete
					:routeDelete="{
						name: 'grp.org.crm.agents.delete',
						parameters: [route().params.organisation, item.id],
					}"
					:title="trans('Are you sure you want to delete this agent?')"
					:noLabel="trans('Delete')"
					noIcon="fal fa-trash">
					<template #default="{ changeModel }">
						<Button
							v-tooltip="trans('Delete Agent')"
							@click="() => changeModel()"
							type="negative"
							icon="fa-trash"
							size="s" />
					</template>
				</ModalConfirmationDelete>
			</div>
		</template>
	</Table>
</template>
