<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Feb 2024 08:28:30 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Navigation } from "@/types/Navigation"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faRoute, faBars } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Link, router } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { isNavigationActive } from "@/Composables/useUrl"
import { onMounted, ref, onUnmounted, inject, computed } from "vue"
import TopBarSubsections from "@/Layouts/Grp/TopBarSubsections.vue"
import { faHandHoldingBox, faIndustry, faInventory } from "@fal"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
library.add(faRoute, faBars, faHandHoldingBox, faIndustry, faInventory)

const props = defineProps<{
	nav: Navigation
	navKey?: string | number // shops_navigation | warehouses_navigation
	number?: number | null
}>()

const layout = inject("layout", layoutStructure)
const isTopMenuActive = ref(false)
const isLoading = ref(false)
const isShortcutLoading = ref(false)

const isNavActive = () =>
	[props.nav.root, ...(props.nav.roots ?? [])]
		.filter(Boolean)
		.some((root) => isNavigationActive(layout.currentRoute, root))

const hasShortcut = computed(() => Boolean(props.nav.shortcut?.route?.name && layout.leftSidebar.show))

const visitShortcut = () => {
	if (!props.nav.shortcut?.route?.name) {
		return
	}

	router.visit(route(props.nav.shortcut.route.name, props.nav.shortcut.route.parameters), {
		onStart: () => (isShortcutLoading.value = true),
		onFinish: () => (isShortcutLoading.value = false),
	})
}

onMounted(() => {
	isTopMenuActive.value = true
})

onUnmounted(() => {
	isTopMenuActive.value = false
})
</script>

<template>
	<component
		:is="nav.route?.name ? Link : 'div'"
		:href="nav.route?.name ? route(nav.route.name, nav.route.parameters) : '#'"
		class="w-full group flex items-center px-2 text-sm gap-x-2"
		:class="[
			isNavActive()
				? 'navigationActive'
				: 'navigation',
			layout.leftSidebar.show ? '' : '',
		]"
		@start="() => (isLoading = true)"
		@finish="() => (isLoading = false)"
		:aria-current="navKey === layout.currentModule ? 'page' : undefined"
		v-tooltip="{
			content: capitalize(nav.tooltip),
			delay: { show: layout.leftSidebar.show ? 500 : 100, hide: 100 },
		}">
		<LoadingIcon v-if="isLoading" class="flex-shrink-0 h-4 w-4" />
		<FontAwesomeIcon
			v-else-if="nav.icon"
			aria-hidden="true"
			class="flex-shrink-0 h-4 w-4"
			fixed-width
			:icon="nav.icon" />

		<Transition name="slide-to-left">
			<span
				v-if="layout.leftSidebar.show"
				class="py-0.5 leading-none whitespace-nowrap"
				:class="[layout.leftSidebar.show ? 'truncate block md:block' : 'block md:hidden']">
				{{ nav.label }}
			</span>
			<span v-else class="leading-none whitespace-nowrap block md:hidden">
				{{ nav.label }}
			</span>
		</Transition>

		<span
			v-if="number"
			class="ml-auto rounded-full bg-white/20 px-1.5 text-xs leading-tight tabular-nums"
			:class="[layout.leftSidebar.show ? '' : 'hidden', hasShortcut ? 'group-hover:hidden' : '', isShortcutLoading ? '!hidden' : '']">
			{{ number }}
		</span>

		<span
			v-if="hasShortcut"
			role="link"
			tabindex="0"
			class="ml-auto flex-shrink-0 rounded px-1 py-0.5 opacity-60 hover:opacity-100 hover:bg-white/20 cursor-pointer"
			:class="number && !isShortcutLoading ? 'hidden group-hover:block' : ''"
			:aria-label="nav.shortcut.label"
			v-tooltip="nav.shortcut.label"
			@click.prevent.stop="visitShortcut"
			@keydown.enter.prevent.stop="visitShortcut">
			<LoadingIcon v-if="isShortcutLoading" class="h-3.5 w-3.5" />
			<FontAwesomeIcon
				v-else
				aria-hidden="true"
				class="h-3.5 w-3.5"
				fixed-width
				:icon="nav.shortcut.icon ?? 'fal fa-bars'" />
		</span>

		<!-- If this Navigation is active, then teleport the SubSections to #TopBarSubsections in <AppTopBar> -->
		<template
			v-if="
				isTopMenuActive &&
				nav.topMenu?.subSections &&
				isNavActive()
			">
			<Teleport
				to="#TopBarSubsections"
				:disabled="
					!isNavActive()
				">
				<TopBarSubsections
					v-if="nav.topMenu?.subSections"
					:subSections="nav.topMenu.subSections" />
			</Teleport>
		</template>
	</component>
</template>
