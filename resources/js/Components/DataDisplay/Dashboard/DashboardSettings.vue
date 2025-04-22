<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import ToggleSwitch from "primevue/toggleswitch"
import { debounce, get } from "lodash"
import axios from "axios"
import { RadioGroup, RadioGroupLabel, RadioGroupOption } from '@headlessui/vue'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCog } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import PureRadio from "@/Components/Pure/PureRadio.vue"
import { options } from "marked"
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
		[key: string]: {  // 'data_display_type' || 'model_state_type' || 'currency_type'
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
	currentTab: string
}>()

// const dashboardTabActive = inject("dashboardTabActive", ref(''))
// const compShowShopStateSetting = computed(() => {
// 	if (dashboardTabActive.value === "shops") {
// 		return true
// 	}

// 	return false
// })

const layout = inject("layout", layoutStructure)
const isLoadingOnTable = inject("isLoadingOnTable", ref(false))
const isSectionVisible = ref(false)

// Section: Interval
const storeIntervalCode = debounce((interval_code) => {
	axios.patch(
		route("grp.models.profile.update"),
		{
			settings: {
				selected_interval: interval_code,
			},
		}
	)
}, 1500)
const isLoadingInterval = ref<string | null>(null)
const updateInterval = (interval_code: string) => {
	props.intervals.value = interval_code
	storeIntervalCode(interval_code)
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

// Section: update data_display_type (minified, full)
const debStoreDataDisplayType = debounce((value: string) => {
	axios.patch(route("grp.models.profile.update"), {
		settings: {
			data_display_type: value,
		},
	})
}, 1500)
const updateDataDisplayType = (value: string) => {
	props.settings.data_display_type.value = value
	debStoreDataDisplayType(value)
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

			<!-- Button: advanced settings -->
			<div
				v-tooltip="trans('Open advanced settings')"
				@click="isSectionVisible = !isSectionVisible"
				class="cursor-pointer p-2 rounded border flex items-center justify-center"
				:class="isSectionVisible ? 'bg-indigo-200 text-indigo-500 border-transparent' : 'border-gray-300 text-gray-400 hover:bg-gray-200'">
				<FontAwesomeIcon icon="far fa-cog" fixed-width aria-hidden="true" class="text-2xl" />
			</div>
		</div>
		
		<transition name="slide-to-right">
			<div v-show="isSectionVisible" id="dashboard-settings" class="flex flex-wrap justify-between items-center gap-4 lg:gap-8 mb-2">

				<div class="flex items-center space-x-4">
					<!-- Toggle: model_state -->
					<Transition name="slide-to-right">
						<div v-if="settings.model_state_type && currentTab === 'shops' " class="flex items-center space-x-4">
							<p v-tooltip="settings.model_state_type.options[0].tooltip" class="" :class="[ settings.model_state_type.options[0].value === settings.model_state_type.value ? 'font-semibold text-indigo-500 underline' : 'opacity-50', ]">
								{{ settings.model_state_type.options[0].label }}
							</p>
							<ToggleSwitch
								:modelValue="settings.model_state_type.value"
								@update:modelValue="(value: any) => updateToggle(settings.model_state_type.id, value, `left_model_state_type`, false)"
								:falseValue="settings.model_state_type.options[0].value"
								:trueValue="settings.model_state_type.options[1]?.value"
								:disabled="`left_model_state_type` === isLoadingToggle"
							/>
							<p v-tooltip="settings.model_state_type.options[1]?.tooltip" class="" :class="[ settings.model_state_type.options[1]?.value === settings.model_state_type.value ? 'font-semibold text-indigo-500 underline' : 'opacity-50', ]">
								{{ settings.model_state_type.options[1]?.label }}
							</p>
						</div>
					</Transition>
				</div>

				<div class="flex items-center gap-x-8">
					<!-- Toggle: data_display_type (minified, full) -->
					<div v-if="settings.data_display_type" class="flex items-center space-x-4">
						<p v-tooltip="settings.data_display_type.options[0].tooltip" class="" :class="[ settings.data_display_type.options[0].value === settings.data_display_type.value ? 'font-semibold text-indigo-500 underline' : 'opacity-50', ]">
							{{ settings.data_display_type.options[0].label }}
						</p>
						<ToggleSwitch
							:modelValue="settings.data_display_type.value"
							@update:modelValue="(value: any) => updateDataDisplayType(value)"
							:falseValue="settings.data_display_type.options[0].value"
							:trueValue="settings.data_display_type.options[1]?.value"
							:disabled="`left_data_display_type` === isLoadingToggle"
						/>
						<p v-tooltip="settings.data_display_type.options[1]?.tooltip" class="" :class="[ settings.data_display_type.options[1]?.value === settings.data_display_type.value ? 'font-semibold text-indigo-500 underline' : 'opacity-50', ]">
							{{ settings.data_display_type.options[1]?.label }}
						</p>
					</div>

					<!-- Toggle: currency_type -->
					<div v-if="settings.currency_type" class="flex items-center space-x-4">
						<RadioGroup class="relative"
							:modelValue="settings.currency_type.value"
							@update:modelValue="(value: any) => updateToggle(settings.currency_type.id, value, `right_currency_type`)"
						>
							<div v-if="`right_currency_type` === isLoadingToggle" class="absolute inset-0 bg-black/50 rounded-md flex items-center justify-center">
								<LoadingIcon class="text-white text-xl m-auto" />
							</div>
							<RadioGroupLabel class="sr-only">Choose the radio</RadioGroupLabel>
							<div class="flex gap-y-1 flex-wrap border border-gray-300 rounded-md overflow-hidden">
								<RadioGroupOption
									as="template" v-for="(option, index) in settings.currency_type.options"
									:key="option.value"
									:value="option.value"
									v-slot="{ active, checked }"
								>
									<div :class="[
											'cursor-pointer focus:outline-none flex items-center justify-center py-3 px-3 text-sm font-medium capitalize',
											checked ? 'bg-indigo-500 text-white' : ' bg-white text-gray-700 hover:bg-gray-200',
										]"
										v-tooltip="option.tooltip"
									>
										<RadioGroupLabel as="span">{{ option.label }}</RadioGroupLabel>
									</div>
								</RadioGroupOption>
							</div>
						</RadioGroup>
					</div>
				</div>
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