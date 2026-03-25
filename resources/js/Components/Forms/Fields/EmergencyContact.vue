<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 11 Mar 2025
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

const props = defineProps<{
	form: any
	fieldName: string
	options: any
	fieldData: {
		placeholder: string
		required: boolean
		isWithRequiredField?: boolean
	}
}>()

const normalizeValue = () => {
	const value = props.form[props.fieldName]
	if (value && typeof value === "object") {
		return
	}

	let parsed: any = null
	if (typeof value === "string") {
		try {
			parsed = JSON.parse(value)
		} catch {
			parsed = null
		}
	}

	if (parsed && typeof parsed === "object") {
		props.form[props.fieldName] = {
			contact: parsed.contact ?? "",
			phone_number: parsed.phone_number ?? "",
			address: parsed.address ?? "",
			status: parsed.status ?? "",
		}
		return
	}

	props.form[props.fieldName] = {
		contact: value ?? "",
		phone_number: "",
		address: "",
		status: "",
	}
}

normalizeValue()

const handleChange = (field?: string) => {
	if (field) {
		props.form.clearErrors(`${props.fieldName}.${field}`)
	}
	props.form.clearErrors(props.fieldName)
}

const statusOptions = props.options?.status_options ?? [
	{ label: "Spouse", value: "Spouse" },
	{ label: "Partner", value: "Partner" },
	{ label: "Parent", value: "Parent" },
	{ label: "Sibling", value: "Sibling" },
	{ label: "Child", value: "Child" },
	{ label: "Friend", value: "Friend" },
	{ label: "Neighbor", value: "Neighbor" },
	{ label: "Other", value: "Other" },
]
</script>

<template>
	<div class="space-y-3">
		<div>
			<label :for="`${fieldName}_contact`" class="block text-sm font-medium text-gray-700">
				<FontAwesomeIcon
					v-if="fieldData.isWithRequiredField && fieldData.required"
					icon="fas fa-asterisk"
					class="text-red-500 text-xxs !text-[0.5rem] align-middle mr-1"
					fixed-width
					aria-hidden="true" />
				Contact Name
			</label>
			<input
				@input="handleChange('contact')"
				v-model="form[fieldName]['contact']"
				type="text"
				:name="`${fieldName}_contact`"
				:id="`${fieldName}_contact`"
				class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
				:class="
					form.errors[fieldName] || form.errors[`${fieldName}.contact`]
						? 'border-red-500'
						: ''
				"
				placeholder="Emergency contact name" />
			<span v-if="form.errors[`${fieldName}.contact`]" class="mt-1 text-sm text-red-600">{{
				form.errors[`${fieldName}.contact`]
			}}</span>
		</div>

		<div>
			<label
				:for="`${fieldName}_phone_number`"
				class="block text-sm font-medium text-gray-700">
				<FontAwesomeIcon
					v-if="fieldData.isWithRequiredField && fieldData.required"
					icon="fas fa-asterisk"
					class="text-red-500 text-xxs !text-[0.5rem] align-middle mr-1"
					fixed-width
					aria-hidden="true" />
				Phone Number
			</label>
			<input
				@input="handleChange('phone_number')"
				v-model="form[fieldName]['phone_number']"
				type="text"
				:name="`${fieldName}_phone_number`"
				:id="`${fieldName}_phone_number`"
				class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
				:class="
					form.errors[fieldName] || form.errors[`${fieldName}.phone_number`]
						? 'border-red-500'
						: ''
				"
				placeholder="Emergency contact phone number" />
			<span
				v-if="form.errors[`${fieldName}.phone_number`]"
				class="mt-1 text-sm text-red-600"
				>{{ form.errors[`${fieldName}.phone_number`] }}</span
			>
		</div>

		<div>
			<label :for="`${fieldName}_address`" class="block text-sm font-medium text-gray-700">
				<FontAwesomeIcon
					v-if="fieldData.isWithRequiredField && fieldData.required"
					icon="fas fa-asterisk"
					class="text-red-500 text-xxs !text-[0.5rem] align-middle mr-1"
					fixed-width
					aria-hidden="true" />
				Address
			</label>
			<input
				@input="handleChange('address')"
				v-model="form[fieldName]['address']"
				type="text"
				:name="`${fieldName}_address`"
				:id="`${fieldName}_address`"
				class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
				:class="
					form.errors[fieldName] || form.errors[`${fieldName}.address`]
						? 'border-red-500'
						: ''
				"
				placeholder="Emergency contact address" />
			<span v-if="form.errors[`${fieldName}.address`]" class="mt-1 text-sm text-red-600">{{
				form.errors[`${fieldName}.address`]
			}}</span>
		</div>

		<div>
			<label :for="`${fieldName}_status`" class="block text-sm font-medium text-gray-700">
				<FontAwesomeIcon
					v-if="fieldData.isWithRequiredField && fieldData.required"
					icon="fas fa-asterisk"
					class="text-red-500 text-xxs !text-[0.5rem] align-middle mr-1"
					fixed-width
					aria-hidden="true" />
				Status
			</label>
			<select
				@change="handleChange('status')"
				v-model="form[fieldName]['status']"
				:name="`${fieldName}_status`"
				:id="`${fieldName}_status`"
				class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
				:class="
					form.errors[fieldName] || form.errors[`${fieldName}.status`]
						? 'border-red-500'
						: ''
				">
				<option value="" disabled>Select relationship</option>
				<option v-for="option in statusOptions" :key="option.value" :value="option.value">
					{{ option.label }}
				</option>
			</select>
			<span v-if="form.errors[`${fieldName}.status`]" class="mt-1 text-sm text-red-600">{{
				form.errors[`${fieldName}.status`]
			}}</span>
		</div>

		<span v-if="form.errors[fieldName]" class="block mt-2 text-sm text-red-600">{{
			form.errors[fieldName]
		}}</span>
	</div>
</template>
