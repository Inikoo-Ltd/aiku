<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 12 May 2024 21:59:20 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Employee } from "@/types/employee"
import Icon from "@/Components/Icon.vue"
import Tag from "@/Components/Tag.vue"

const props = defineProps<{
	data: object
	tab?: string
}>()

function employeeRoute(employee: Employee) {
	switch (route().current()) {
		case "grp.org.hr.employees.index":
		case "grp.org.hr.job_positions.show":
			return route("grp.org.hr.employees.show", [
				route().params["organisation"],
				employee.slug,
			])
		case "grp.overview.hr.employees.index":
			return route("grp.org.hr.employees.show", [employee.organisation_slug, employee.slug])
	}
}
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(state)="{ item: employee }">
			<Icon :data="employee['state_icon']" />
		</template>

		<template #cell(is_on_probation)="{ item: employee }">
			<Tag
				v-if="employee.is_on_probation"
				label="Probation"
				class="bg-yellow-100 text-yellow-800" />
		</template>

		<template #cell(length_of_service)="{ item: employee }">
			<span v-if="employee.length_of_service" class="text-sm text-gray-600">
				{{ employee.length_of_service }}
			</span>
		</template>

		<template #cell(slug)="{ item: employee }">
			<Link :href="employeeRoute(employee)" class="primaryLink">
				{{ employee["slug"] }}
			</Link>
		</template>

		<template #cell(positions)="{ item: employee }">
			<div class="flex flex-wrap gap-x-1.5 gap-y-1">
				<Link
					v-for="(position, key) in employee.positions"
					:key="key"
					href="#"
					:title="position.name"
					class="inline-flex">
					<Tag :label="position.name" stringToColor />
				</Link>
			</div>
		</template>
	</Table>
</template>
