<script setup lang="ts">
import { ref, watch, onMounted } from "vue"
import { trans } from "laravel-vue-i18n"
import RadioButton from "primevue/radiobutton"
import PureInput from "@/Components/Pure/PureInput.vue"
import SelectQuery from "@/Components/SelectQuery.vue"
import { set } from "lodash"


const props = withDefaults(defineProps<{
	modelValue: {
		type: String  // external|internal
		href: string
		target: string // "_self"|"_blank",
		id: string | number
		workshop_route: string
	},
	defaultValue?: {
		type: String  // external|internal
		href: string
		target: string // "_self"|"_blank",
		id: string | number
		workshop_route: string
	},
	props_radio_type?: any,
	props_radio_target?: any,
	props_input?: any,
	props_selectquery?: any
}>(), {})


const emit = defineEmits(['update:modelValue']);

const localModel = ref({
	type: 'external',
	href: null,
	workshop: null,
	id: null,
	target: "_self",
	url : null,
	data: props.modelValue || {}
});

const options = ref([
	{ label: "Internal", value: "internal" },
	{ label: "External", value: "external" },
])

const targets = ref([
	{ label: "In this Page", value: "_self" },
	{ label: "New Page", value: "_blank" },
])


function getRoute() {
	if (route().current().includes('fulfilments')) {
		return route('grp.org.fulfilments.show.web.webpages.index', {
			organisation: route().params['organisation'],
			fulfilment: route().params['fulfilment'],
			website: route().params['website'],
		})

	} else if (route().current().includes('shop')) {
		return route('grp.org.shops.show.web.webpages.index', {
			organisation: route().params['organisation'],
			shop: route().params['shop'],
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

watch(localModel, (newValue) => {
	const data = {
		type: newValue.type,
		href: newValue.href,
		workshop: newValue.workshop,
		id: newValue.id,
		target: newValue.target,
		url : newValue.href,
	}
	emit('update:modelValue', data)
}, { deep: true })

onMounted(() => {
	if (props.modelValue) localModel.value = { ...props.modelValue, data: props.modelValue }
	else {
		if(props.defaultValue) {
			localModel.value = {...props.defaultValue, data: props.defaultValue }
		}
	}
});

</script>

<template>
	<div>
		<div>
			<div class="text-gray-500 text-xs tracking-wide mb-2">{{ trans("Target") }}</div>
			<div class="mb-3 border border-gray-300 rounded-md w-full px-4 py-2">
				<div class="flex flex-wrap justify-between w-full">
					<div v-for="(option, indexOption) in targets" class="flex items-center gap-2">
						<RadioButton :modelValue="localModel.target" v-bind="props_radio_target"
							@update:modelValue="(e: string) => set(localModel, 'target', e)"
							:inputId="`${option.value}${indexOption}`" name="target" size="small"
							:value="option.value" />
						<label :for="`${option.value}${indexOption}`"
							class="cursor-pointer">{{ option.label }}</label>
					</div>
				</div>
			</div>
		</div>

		<div>
			<div class="text-gray-500 text-xs tracking-wide mb-2">{{ trans("Type") }}</div>
			<div class="mb-3 border border-gray-300 rounded-md w-full px-4 py-2">
				<div class="flex flex-wrap justify-between w-full">
					<div v-for="(option, indexOption) in options" class="flex items-center gap-2">
						<RadioButton :modelValue="localModel.type" v-bind="props_radio_type"
							@update:modelValue="(e: string) => set(localModel, 'type', e)"
							:inputId="`${option.value}${indexOption}`" name="type" size="small" :value="option.value" />
						<label  :for="`${option.value}${indexOption}`"
							class="cursor-pointer">{{ option.label }}</label>
					</div>
				</div>
			</div>
		</div>

		<div v-if="localModel?.type">
			<div class="my-2 text-gray-500 text-xs tracking-wide mb-2">{{ trans("Destination") }}</div>
		<!-- 	{{ defaultValue }}
			{{ localModel }} -->
			<PureInput
				v-if="localModel?.type == 'external'"
				v-model="localModel.href"
				placeholder="www.anotherwebsite.com/page"
				v-bind="props_input"
			/>

			<SelectQuery v-if="localModel?.type == 'internal'" :object="true" fieldName="data" :value="localModel"
				:closeOnSelect="true" label="href" :onChange="(e) => { localModel.url = e?.url, localModel.href = e?.href, localModel.id = e?.id, localModel.workshop = e?.workshop  }"
				:urlRoute="getRoute()" v-bind="props_selectquery"/>
		</div>
	</div>
</template>

<style lang="scss" scoped></style>
