<script setup lang="ts">
import { inject, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import ToggleSwitch from "primevue/toggleswitch"
import { get } from "lodash"
import axios from "axios"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCog } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
library.add(faCog)

const props = defineProps<{
	intervals: {
		options: {
			label: string
			value: string
			labelShort: string
		}[]
		value: string
	}
	settings: {
		[key: string]: {  // 'data_display_type'
			align: string
			id: string
			options: {
				label: string
				value: string
				tooltip?: string
			}[]
			type: string
			value: string
		}
	}
}>()

const layout = inject("layout", layoutStructure)
const isLoadingOnTable = inject("isLoadingOnTable", ref(false))
const isSectionVisible = ref(false)
// const page = usePage()
// const tabDashboardInterval = computed(() => {
// 	const currentUrl = new URL(page.url, window.location.origin)
// 	return currentUrl.searchParams.get("tab_dashboard_interval")
// })

// watch(
// 	tabDashboardInterval,
// 	(newVal, oldVal) => {
// 		console.log("tab_dashboard_interval changed from", oldVal, "to", newVal)
// 	},
// 	{ immediate: true }
// )

// Section: Interval
const isLoadingInterval = ref<string | null>(null)
const updateInterval = (interval_code: string) => {
	router.patch(
		route("grp.models.profile.update"),
		{
			settings: {
				selected_interval: interval_code,
			},
		},
		{
			onStart: () => {
				isLoadingOnTable.value = true
				isLoadingInterval.value = interval_code
			},
			onFinish: () => {
				isLoadingInterval.value = null
				isLoadingOnTable.value = false
			},
			preserveScroll: true,
		}
	)
}

const isLoadingToggle = ref<string | null>(null)
const updateToggle = async (key: string, value: string, valLoading: string, isAxios?: boolean) => {
	if (isAxios) {  // use Axios ()
		isLoadingToggle.value = valLoading
		isLoadingOnTable.value = true
		await axios.patch(route("grp.models.profile.update"), {
			settings: {
				[key]: value,
			},
		}).then(() => {
			isLoadingToggle.value = null
			props.settings[key].value = value
		}).catch(() => {

		})
		isLoadingToggle.value = null
		isLoadingOnTable.value = false
	} else {  // use Inertia
		router.patch(
			route("grp.models.profile.update"),
			{
				settings: {
					[key]: value,
				},
			},
			{
				onStart: () => {
					isLoadingToggle.value = valLoading
					isLoadingOnTable.value = true
				},
				onFinish: () => {
					isLoadingToggle.value = null
					isLoadingOnTable.value = false
				},
				preserveScroll: true,
			}
		)
	}
}
</script>

<template>
	<div class="relative px-4 mt-4">
		<div class="mb-2 flex justify-between gap-x-2">
			<!-- Section: Period options list -->
			<nav class="isolate rounded border p-1 hidden sm:flex w-fit" aria-label="Tabs">
				<div class="flex w-fit">
					<div
						v-for="(interval, idxInterval) in intervals.options"
						:key="idxInterval"
						@click="updateInterval(interval.value)"
						:class="[
							interval.value === intervals.value
								? 'bg-indigo-500 text-white font-medium'
								: 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
						]"
						v-tooltip="interval.label"
						class="relative flex-1 rounded py-1.5 px-4 text-center w-fit text-sm cursor-pointer select-none">
						<span :class="isLoadingInterval === interval.value ? 'opacity-0' : ''">
							{{ interval.value }}
						</span>
						<span
							class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2"
							:class="isLoadingInterval === interval.value ? '' : 'opacity-0'">
							<LoadingIcon />
						</span>
					</div>
				</div>
			</nav>

			<div
				v-tooltip="trans('Open advanced settings')"
				@click="isSectionVisible = !isSectionVisible"
				class="cursor-pointer p-2 rounded border"
				:class="isSectionVisible ? 'bg-indigo-200 text-indigo-500 border-transparent' : 'border-gray-300 text-gray-400 hover:bg-gray-200'">
				<FontAwesomeIcon icon="far fa-cog" fixed-width aria-hidden="true" class="text-2xl" />
			</div>

			<!-- Mobile: Interval Dropdown -->
		</div>
		
		<transition name="slide-to-right">
			<div v-show="isSectionVisible" id="dashboard-settings" class="flex flex-wrap justify-between items-center gap-4 lg:gap-8 mb-2">
				<!-- Toggle: Align left (open/closed) -->
				<template v-for="setting, indexSetting in settings" :key="indexSetting">
					<div v-if="setting.align !== 'right'" class="flex items-center space-x-4">
						<template v-if="setting.type === 'toggle'">
							<p v-tooltip="setting.options[0].tooltip" class="" :class="[ setting.options[0].value === setting.value ? 'font-medium' : 'opacity-50', ]">
								{{ setting.options[0].label }}
							</p>
							<ToggleSwitch
								:modelValue="setting.value"
								@update:modelValue="(value: any) => updateToggle(setting.id, value, `left${indexSetting}`, true)"
								:falseValue="setting.options[0].value"
								:trueValue="setting.options[1]?.value"
								:disabled="`left${indexSetting}` === isLoadingToggle"
							/>
							<p v-tooltip="setting.options[1]?.tooltip" class="" :class="[ setting.options[1]?.value === setting.value ? 'font-medium' : 'opacity-50', ]">
								{{ setting.options[1]?.label }}
							</p>
						</template>
					</div>
				</template>
				<!-- Toggle: Align right (minified/full) -->
				<template v-for="setting, indexSetting in settings" :key="indexSetting">
					<div v-if="setting.align === 'right'" class="flex items-center space-x-4">
						<template v-if="setting.type === 'toggle'">
							<p v-tooltip="setting.options[0].tooltip" class="" :class="[ setting.options[0].value === setting.value ? 'font-medium' : 'opacity-50', ]">
								{{ setting.options[0].label }}
							</p>
							<ToggleSwitch
								:modelValue="setting.value"
								@update:modelValue="(value: any) => updateToggle(setting.id, value, `right${indexSetting}`, true)"
								:falseValue="setting.options[0].value"
								:trueValue="setting.options[1]?.value"
								:disabled="`right${indexSetting}` === isLoadingToggle"
							/>
							<p v-tooltip="setting.options[1]?.tooltip" class="" :class="[ setting.options[1]?.value === setting.value ? 'font-medium' : 'opacity-50', ]">
								{{ setting.options[1]?.label }}
							</p>
						</template>
					</div>
				</template>
			</div>
		</transition>
	</div>
</template>

<style scoped>
:deep(#dashboard-settings) {
	--p-toggleswitch-background: v-bind('layout?.app?.theme[4]');
	--p-toggleswitch-hover-background: v-bind('layout?.app?.theme[2]');
	--p-toggleswitch-checked-background: v-bind('layout?.app?.theme[4]');
	--p-toggleswitch-checked-hover-background: v-bind('layout?.app?.theme[2]');
}
</style>