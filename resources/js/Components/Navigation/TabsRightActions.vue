<!--
  - Author: Oggie Sutrisna <oggiesutrisna@proton.me>
  - Desc : A Button for overriding clock-out just for admins only.
  - Created: Sat, 18 Mar 2023 04:01:00 Bali, Indonesia
  - Copyright (c) 2026
  -->

<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { inject } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"

interface ActionButton {
	label: string
	icon?: string
	type?: string
	onClick: (event: MouseEvent) => void
}

defineProps<{
	actions: Array<ActionButton>
}>()

const layoutStore = inject("layout", layoutStructure)
</script>

<template>
	<template v-if="actions && actions.length > 0">
		<button
			v-for="(action, index) in actions"
			:key="`action-${index}`"
			v-tooltip="action.label"
			@click="action.onClick($event)"
			class="relative group inline-flex gap-x-1.5 justify-center items-center py-2 px-2 border-b-2 font-medium text-sm tabNavigation">
			<FontAwesomeIcon
				v-if="action.icon"
				:icon="action.icon"
				class="h-5 w-5"
				aria-hidden="true" />
			<span v-if="action.type !== 'icon'" class="whitespace-nowrap">{{ action.label }}</span>
		</button>
	</template>
</template>

<style lang="scss" scoped>
.tabNavigation {
	@apply transition-all duration-75;
	filter: saturate(0);
	border-bottom: v-bind("`2px solid transparent`");
	color: v-bind("`${layoutStore.app.theme[0]}99`");

	&:hover {
		filter: saturate(0.85);
		border-bottom: v-bind("`2px solid ${layoutStore.app.theme[0]}AA`");
		color: v-bind("`${layoutStore.app.theme[0]}AA`");
	}
}
</style>
