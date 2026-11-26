<script setup lang="ts">
import { computed, watchEffect } from "vue"
import { ctrans } from "@/Composables/useTrans"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import MultiselectTagsInfiniteScroll from "@/Components/Forms/Fields/MultiselectTagsInfiniteScroll.vue"
import { faCheck } from "@fas"
import { faArrowDown } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Button from "@/Components/Elements/Buttons/Button.vue"

const props = defineProps<{
	form: any
	fieldName: string
	submit?: Function
	fieldData: {
		options: { code: string; name: string }[]
		current: string
		value: OrderRelease
		saveConfirmation?: { description?: string }
	}
}>()

type OrderRelease = { except_countries: boolean; countries: string[] }

const value = computed<OrderRelease>(() => props.form[props.fieldName] ?? { except_countries: false, countries: [] })

const mode = computed({
	get: () => (value.value.except_countries ? "except" : "only"),
	set: (newMode: string) => {
		const exceptCountries = newMode === "except"
		if (exceptCountries === value.value.except_countries) {
			return
		}
		props.form[props.fieldName] = { except_countries: exceptCountries, countries: [] }
	},
})

const modeOptions = computed(() =>
	[
		{ label: ctrans("Only these countries wait"), value: "only" },
		{ label: ctrans("All countries wait, except these"), value: "except" },
	].map((option) => ({ ...option, icon: option.value === mode.value ? faCheck : undefined }))
)

const countriesForm = computed(() => ({
	get countries() {
		return value.value.countries ?? []
	},
	set countries(countries: string[]) {
		props.form[props.fieldName] = { ...value.value, countries }
	},
}))

const countryNames = computed(() => {
	const names = props.fieldData.options.filter((country) => value.value.countries?.includes(country.code)).map((country) => country.name)
	if (names.length <= 10) {
		return names.join(", ")
	}
	return ctrans(":countries + :count other countries", { countries: names.slice(0, 10).join(", "), count: names.length - 10 })
})

const outcome = computed(() => {
	if (!value.value.countries?.length) {
		return ctrans("No order waits, every order goes to the warehouse.")
	}
	return value.value.except_countries
		? ctrans("EVERY order waits for the office, except orders to :countries.", { countries: countryNames.value })
		: ctrans("Orders to :countries wait for the office, every other order goes to the warehouse.", { countries: countryNames.value })
})

const isChanged = computed(() => {
	const saved = props.fieldData.value
	const savedCountries = [...(saved?.countries ?? [])].sort().join(",")
	const newCountries = [...(value.value.countries ?? [])].sort().join(",")
	return !!saved?.except_countries !== !!value.value.except_countries || savedCountries !== newCountries
})

/** The dialog covers the "After saving" box, so it says the outcome itself */
watchEffect(() => {
	if (props.fieldData.saveConfirmation) {
		props.fieldData.saveConfirmation.description = ctrans("After saving: :outcome A wrong country means its orders stop, or go out without being checked.", { outcome: outcome.value })
	}
})
</script>

<template>
	<div class="w-full max-w-md space-y-3">
		<SegmentedToggle
			v-model="mode"
			class="order-release-mode"
			:options="modeOptions"
			:ariaLabel="ctrans('Which orders wait for the office')" />

		<MultiselectTagsInfiniteScroll
			:form="countriesForm"
			fieldName="countries"
			:fieldData="{
				options: fieldData.options,
				labelProp: 'name',
				valueProp: 'code',
				placeholder: mode === 'except' ? ctrans('Countries that go to the warehouse on their own') : ctrans('Countries that wait for the office'),
			}" />

		<div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
			<span class="font-semibold">{{ ctrans("Currently") }}:</span> {{ fieldData.current }}
		</div>
		<template v-if="isChanged">
			<div class="-my-1.5 flex justify-center text-gray-400">
				<FontAwesomeIcon :icon="faArrowDown" fixed-width aria-hidden="true" />
			</div>
			<div class="rounded-md border border-amber-400 bg-amber-50 px-3 py-2 text-sm text-amber-800">
				<span class="font-semibold">{{ ctrans("After saving") }}:</span> {{ outcome }}
			</div>
			<div class="flex justify-end">
				<Button
					type="save"
					:label="ctrans('Save')"
					:loading="form.processing"
					:disabled="form.processing"
					@click="() => submit?.()" />
			</div>
		</template>
	</div>
</template>

<style scoped>
.order-release-mode :deep(svg) {
	color: #16a34a;
}
</style>
