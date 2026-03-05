<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 18 Mar 2023 04:04:35 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<!--
    TODO: Icon loading is unlimited if change tabs is failed
-->
<script setup lang="ts">
import { inject, ref, watch } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle, faPallet, faCircle, faUndo } from "@fas"
import { faSpinnerThird } from "@fad"
import Icon from "@/Components/Icon.vue"
import {
	faRoad,
	faClock,
	faDatabase,
	faNetworkWired,
	faEye,
	faThLarge,
	faTachometerAltFast,
	faMoneyBillWave,
	faHeart,
	faShoppingCart,
	faCameraRetro,
	faStream,
	faTachometerAlt,
	faTransporter,
	faDotCircle,
	faFolderTree,
	faAlbumCollection,
	faPenAlt,
	faShapes
} from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import type { Navigation } from "@/types/Tabs"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import {
  Listbox,
  ListboxButton,
  ListboxOptions,
  ListboxOption
} from "@headlessui/vue"

library.add(
	faShapes,
	faInfoCircle,
	faRoad,
	faClock,
	faDatabase,
	faPallet,
	faCircle,
	faNetworkWired,
	faSpinnerThird,
	faEye,
	faThLarge,
	faTachometerAltFast,
	faMoneyBillWave,
	faHeart,
	faShoppingCart,
	faCameraRetro,
	faStream,
	faTachometerAlt,
	faTransporter,
	faDotCircle,
	faFolderTree,
	faAlbumCollection,
	faUndo,
	faPenAlt,
)

const layoutStore = inject("layout", layoutStructure)
const locale = inject('locale', aikuLocaleStructure)

const props = defineProps<{
	navigation: Navigation
	current: string | number
}>()

const emits = defineEmits<{
	(e: "update:tab", value: string): void
}>()

const currentTab = ref<string | number>(props.current)
const tabLoading = ref<boolean | string>(false)

// Method: click Tab
const onChangeTab = async (tabSlug: string) => {
	if (tabSlug === currentTab.value) return // To avoid click on the current tab occurs loading
	tabLoading.value = tabSlug
	emits("update:tab", tabSlug)
}

// Set new active Tab after parent has changed page
watch(
	() => props.current,
	(newVal) => {
		currentTab.value = newVal
		tabLoading.value = false
	}
)

const tabIconClass = function (
	isCurrent: boolean,
	type: string | undefined,
	align: string | undefined,
	extraIconClass: string
) {
	// console.log(isCurrent, type, align, extraIconClass)
	let iconClass = "-ml-0.5 h-5 w-5   " + extraIconClass
	// iconClass += isCurrent ? 'text-indigo-500 ' : 'text-gray-400 group-hover:text-gray-500 ';
	iconClass += type == "icon" && align == "right" ? "ml-2 " : "mr-2 "
	return iconClass
}
</script>

<template>
	<div>
		<!-- Tabs: Mobile view -->
		<div v-if="Object.keys(navigation ?? {})?.length > 1" class="sm:hidden px-3 pt-2">

			<Listbox :model-value="currentTab" @update:modelValue="onChangeTab">
				<div class="relative">

					<!-- Button -->
					<ListboxButton
						class="relative w-full cursor-pointer rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 text-left shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500 sm:text-sm">

						<span class="flex items-center">

							<!-- Loading spinner -->
							<FontAwesomeIcon
								v-if="tabLoading"
								icon="fad fa-spinner-third"
								class="animate-spin mr-2 h-5 w-5"
							/>

							<!-- Icon -->
							<FontAwesomeIcon
								v-else-if="navigation[currentTab]?.icon"
								:icon="navigation[currentTab].icon"
								class="mr-2 h-5 w-5"
							/>

							<!-- Title -->
							<span class="block truncate">
								{{ navigation[currentTab]?.title }}
							</span>
						</span>

					</ListboxButton>

					<!-- Options -->
					<ListboxOptions
						class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">

						<ListboxOption
						v-for="(tab, tabSlug) in navigation"
						:key="tabSlug"
						:value="tabSlug"
						v-slot="{ active, selected }"
						>

						<li
							:class="[
							active ? 'bg-gray-100' : '',
							'relative cursor-pointer select-none py-2 pl-3 pr-9'
							]"
						>
							<div class="flex items-center">

								<!-- Spinner -->
								<FontAwesomeIcon
									v-if="tabLoading === tabSlug"
									icon="fad fa-spinner-third"
									class="animate-spin mr-2 h-5 w-5"
								/>

								<!-- Icon -->
								<FontAwesomeIcon
									v-else-if="tab.icon"
									:icon="tab.icon"
									class="mr-2 h-5 w-5"
								/>

								<!-- Title -->
								<span
									:class="[
									selected ? 'font-semibold' : 'font-normal',
									'block truncate'
									]"
								>
									{{ tab.title }}
								</span>

							</div>
						</li>

						</ListboxOption>

					</ListboxOptions>

				</div>
			</Listbox>

		</div>
		<!-- Tabs: Desktop view -->
		<div class="hidden sm:block">
			<div class="border-b border-gray-200 flex">
				<!-- Left section -->
				<nav class="-mb-px flex w-full gap-x-6 ml-4" aria-label="Tabs">
					<template v-for="(tab, tabSlug) in navigation" :key="tabSlug">
						<button
							v-if="tab.align !== 'right'"
							@click="onChangeTab(tabSlug)"
							:class="[
								tabSlug === currentTab ? 'tabNavigationActive' : 'tabNavigation',
							]"
							class="relative group flex items-center py-2 px-1 font-medium text-left text-sm md:text-base w-fit"
							:aria-current="tabSlug === currentTab ? 'page' : undefined">
							<FontAwesomeIcon
								v-if="tabLoading === tabSlug"
								icon="fad fa-spinner-third"
								class="animate-spin"
								:class="
									tabIconClass(
										tabSlug === currentTab,
										tab.type,
										tab.align,
										tab.iconClass || ''
									)
								"
								aria-hidden="true" />
							<FontAwesomeIcon
								v-else-if="tab.icon"
								:icon="tab.icon"
								:class="
									tabIconClass(
										tabSlug === currentTab,
										tab.type,
										tab.align,
										tab.iconClass || ''
									)
								"
								aria-hidden="true"
								:rotation="tab.icon_rotation"
							/>
							<span>{{ tab.title }}</span>

							<div v-if="typeof tab.number == 'number'"
                                class="ml-2 inline-flex items-center w-fit rounded-full px-2 py-0.5 text-xs font-medium tabular-nums"
                                :class="tabSlug === currentTab ? 'bg-[var(--theme-color-0)] text-[var(--theme-color-1)]' : 'bg-gray-200 '">
                                {{ locale.number(tab.number || 0) }}
                            </div>

							<Icon v-if="tab.icon_right" :data="tab.icon_right" :class="tab.icon_right.class" style="margin-left: 4px" />

							<FontAwesomeIcon
								v-if="tab.indicator"
								icon="fas fa-circle"
								class="animate-pulse absolute top-3 -right-1 text-blue-500 text-[6px]"
								fixed-width
								aria-hidden="true" />
						</button>
					</template>
				</nav>

				<!-- Right section -->
				<nav class="flex flex-row-reverse mr-4" aria-label="Secondary Tabs">
					<template v-for="(tab, tabSlug) in navigation" :key="tabSlug">
						<button
							v-if="tab.align === 'right'"
							@click="onChangeTab(tabSlug)"
							:class="[
								tabSlug === currentTab ? 'tabNavigationActive' : 'tabNavigation',
							]"
							class="relative group inline-flex gap-x-1.5 justify-center items-center py-2 px-2 border-b-2 font-medium text-sm"
							:aria-current="tabSlug === currentTab ? 'page' : undefined"
							v-tooltip="tab.title">
							<FontAwesomeIcon
								v-if="tabLoading === tabSlug"
								icon="fad fa-spinner-third"
								class="animate-spin h-5 w-5"
								aria-hidden="true" />
							<FontAwesomeIcon
								v-else-if="tab.icon"
								:icon="tab.icon"
								class="h-5 w-5"
								aria-hidden="true" />
							<span v-if="tab.type !== 'icon'" class="whitespace-nowrap">{{
								tab.title
							}}</span>

							<div v-if="typeof tab.number == 'number'"
                                class="ml-0.5 inline-flex items-center w-fit rounded-full px-2 py-0.5 text-xs font-medium tabular-nums"
                                :class="tabSlug === currentTab ? 'bg-[var(--theme-color-0)] text-[var(--theme-color-1)]' : 'bg-gray-200 '">
                                {{ locale.number(tab.number || 0) }}
                            </div>

							<Icon v-if="tab.icon_right" :data="tab.icon_right" :class="tab.icon_right.class" style="margin-left: 4px" />

							<FontAwesomeIcon
								v-if="tab.indicator"
								icon="fas fa-circle"
								class="animate-ping absolute top-3 -right-1 text-blue-500 text-[6px]"
								fixed-width
								aria-hidden="true" />
							<FontAwesomeIcon
								v-if="tab.indicator"
								icon="fas fa-circle"
								class="absolute top-3 -right-1 text-blue-500 text-[6px]"
								fixed-width
								aria-hidden="true" />
						</button>
					</template>
				</nav>
			</div>
		</div>
	</div>
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

.tabNavigationActive {
	border-bottom: v-bind("`2px solid ${layoutStore.app.theme[0]}`");
	color: v-bind("layoutStore.app.theme[0]");
}
</style>
