<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import RadioButton from "primevue/radiobutton"
import PureInput from "@/Components/Pure/PureInput.vue"
import SelectQuery from "@/Components/SelectQuery.vue"
import { set } from 'lodash-es'

const props = withDefaults(defineProps<{
	modelValue?: {
		type: string,  // external|internal
		href: string | null,
		target: string, // "_self"|"_blank",
		id: string | number | null,
		workshop_route: string
	},
	defaultValue?: {
		type: string,  // external|internal
		href: string | null,
		target: string, // "_self"|"_blank",
		id: string | number | null,
		workshop_route: string
	},
	props_radio_type?: any,
	props_radio_target?: any,
	props_input?: any,
	props_selectquery?: any
}>(), {})

const emit = defineEmits(['update:modelValue'])

// Menggunakan computed untuk menetapkan nilai default jika modelValue kosong
const localModel = computed({
	get: () => {
		return props.modelValue ?? (props.defaultValue 
			? { ...props.defaultValue, data: props.defaultValue } 
			: { type: 'internal', href: null, workshop: null, id: null, target: "_self", url: null, data: {} })
	},
	set: (newValue) => {
		emit('update:modelValue', newValue) // 🔥 Emit perubahan ke modelValue
	}
})

const options = [
	{ label: "Internal", value: "internal" },
	{ label: "External", value: "external" },
]

const targets = [
	{ label: "In this Page", value: "_self" },
	{ label: "New Page", value: "_blank" },
]

function getRoute() {
	if (route().current().includes('fulfilments')) {
		return route('grp.org.fulfilments.show.web.webpages.index', {
			organisation: route().params['organisation'],
			fulfilment: route().params['fulfilment'],
			website: route().params['website'],
		})
	} else {
		return route('grp.org.shops.show.web.webpages.index', {
			organisation: route().params['organisation'],
			shop: route().params['shop'],
			website: route().params['website'],
		})
	}
}
</script>

<template>
	<div>
		<!-- Target Selection -->
		<div>
			<div class="text-gray-500 text-xs tracking-wide mb-2">{{ trans("Target") }}</div>
			<div class="mb-3 border border-gray-300 rounded-md w-full px-4 py-2">
				<div class="flex flex-wrap justify-between w-full">
					<div v-for="(option, indexOption) in targets" class="flex items-center gap-2">
						<RadioButton 
							:modelValue="localModel.target" 
							v-bind="props_radio_target"
							@update:modelValue="(e: string) => {
								set(localModel, 'target', e)
								emit('update:modelValue', localModel) // 🔥 Emit setiap perubahan
							}"
							:inputId="`${option.value}${indexOption}`" 
							name="target" 
							size="small"
							:value="option.value" 
						/>
						<label :for="`${option.value}${indexOption}`" class="cursor-pointer">{{ option.label }}</label>
					</div>
				</div>
			</div>
		</div>

		<!-- Type Selection -->
		<div>
			<div class="text-gray-500 text-xs tracking-wide mb-2">{{ trans("Type") }}</div>
			<div class="mb-3 border border-gray-300 rounded-md w-full px-4 py-2">
				<div class="flex flex-wrap justify-between w-full">
					<div v-for="(option, indexOption) in options" class="flex items-center gap-2">
						<RadioButton 
							:modelValue="localModel.type" 
							v-bind="props_radio_type"
							@update:modelValue="(e: string) => {
								set(localModel, 'type', e)
								emit('update:modelValue', localModel) // 🔥 Emit setiap perubahan
							}"
							:inputId="`${option.value}${indexOption}`" 
							name="type" 
							size="small" 
							:value="option.value" 
						/>
						<label :for="`${option.value}${indexOption}`" class="cursor-pointer">{{ option.label }}</label>
					</div>
				</div>
			</div>
		</div>

		<!-- Destination Input -->
		<div v-if="localModel?.type">
			<div class="my-2 text-gray-500 text-xs tracking-wide mb-2">{{ trans("Destination") }}</div>
			
			<PureInput
				v-if="localModel?.type == 'external'"
				v-model="localModel.href"
				placeholder="www.anotherwebsite.com/page"
				v-bind="props_input"
				@update:modelValue="(e) => {
					set(localModel, 'href', e)
					emit('update:modelValue', localModel) // 🔥 Emit setiap perubahan
				}"
			/>

			<SelectQuery 
				v-if="localModel?.type == 'internal'" 
				:object="true" 
				fieldName="data" 
				:value="localModel"
				:closeOnSelect="true" 
				:searchable="true"
				label="href" 
				:canClear="true"
				:clearOnSearch="true"
				:onChange="(e) => { 
					set(localModel, 'url', e?.url)
					set(localModel, 'href', e?.href)
					set(localModel, 'id', e?.id)
					set(localModel, 'workshop', e?.workshop)
					emit('update:modelValue', localModel) // 🔥 Emit setiap perubahan
				}"
				:urlRoute="getRoute()" 
				v-bind="props_selectquery"
			/>
		</div>
	</div>
</template>

<style lang="scss" scoped></style>
