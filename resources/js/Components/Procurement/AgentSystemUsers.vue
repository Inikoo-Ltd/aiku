<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TableUsers from "@/Components/Tables/Grp/SysAdmin/TableUsers.vue"
import AgentSystemUserDialog from "@/Components/Procurement/AgentSystemUserDialog.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPencil, faPlus } from "@fal"
import { ctrans } from "@/Composables/useTrans"

defineProps<{
	data: object
	tab?: string
	management?: { store_route: { name: string; parameters: number[] }; update_route: { name: string; parameters: number[] }; positions: { value: string; label: string }[] } | null
}>()

const dialog = ref<InstanceType<typeof AgentSystemUserDialog> | null>(null)

const userLink = (user: { slug: string }) => route("grp.supply-chain.agents.show.system_users.show", [route().params.agent, user.slug])
</script>

<template>
	<div>
		<TableUsers :data="data" :tab="tab" :userLink="userLink">
			<template #tableExtraAction>
				<Button v-if="management" type="tertiary" size="xs" :label="ctrans('New user')" @click="dialog?.openCreate()">
					<template #icon>
						<FontAwesomeIcon :icon="faPlus" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</template>
			<template #actions="{ user }">
				<Button type="tertiary" size="xs" :label="ctrans('Edit')" @click="dialog?.openEdit(user)">
					<template #icon>
						<FontAwesomeIcon :icon="faPencil" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</template>
		</TableUsers>

		<AgentSystemUserDialog v-if="management" ref="dialog" :management="management" />
	</div>
</template>
