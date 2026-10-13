<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import InputNumber from "primevue/inputnumber"
import Select from "primevue/select"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faCheck, faArrowLeft } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LeaveBalanceChangesPreview from "@/Components/HumanResources/LeaveBalanceChangesPreview.vue"
import { ctrans } from "@/Composables/useTrans"
import { dateToIso, fieldFocusClass, inputFocusClass, isoToDate } from "@/Composables/useHrFormControls"
import { useLeaveBalancePreview } from "@/Composables/useLeaveBalancePreview"

library.add(faTimes, faCheck, faArrowLeft)

interface ContractRow {
	id: number
	start_date: string
	end_date: string | null
	annual_leave_days: number
	notes: string | null
}

interface UnlinkedBalance {
	id: number
	annual_used: number
	medical_used: number
	unpaid_used: number
}

const props = defineProps<{
	employeeId: number
	contract?: ContractRow | null
	defaultAnnualLeaveDays?: number
	unlinkedBalances?: UnlinkedBalance[]
}>()

const visible = defineModel<boolean>("visible", { default: false })

const step = ref<"form" | "preview">("form")
const isSaving = ref(false)
const saveErrors = ref<Record<string, string>>({})

const form = reactive({
	start_date: "",
	end_date: "",
	annual_leave_days: 0 as number | null,
	notes: "",
	link_balance_id: null as number | null,
})

const isEditing = computed(() => !!props.contract)

const payload = () => ({
	start_date: form.start_date,
	end_date: form.end_date || null,
	annual_leave_days: form.annual_leave_days ?? 0,
	notes: form.notes || null,
	...(form.link_balance_id ? { link_balance_id: form.link_balance_id } : {}),
	...(isEditing.value ? { operation: "update" } : {}),
})

const preview = useLeaveBalancePreview(
	() =>
		props.contract
			? route("grp.models.employee.contracts.preview", { contract: props.contract.id })
			: route("grp.models.employee.contracts.preview_store", { employee: props.employeeId }),
	payload
)

const errors = computed<Record<string, string>>(() => ({ ...preview.errors.value, ...saveErrors.value }))

const startDate = computed<Date | null>({
	get: () => isoToDate(form.start_date),
	set: (value) => {
		form.start_date = dateToIso(value)
	},
})

const endDate = computed<Date | null>({
	get: () => isoToDate(form.end_date),
	set: (value) => {
		form.end_date = dateToIso(value)
	},
})

const linkOptions = computed(() =>
	(props.unlinkedBalances ?? []).map((balance) => ({
		value: balance.id,
		label: ctrans("Balance: :annual annual used, :medical medical used", {
			annual: String(Number(balance.annual_used)),
			medical: String(Number(balance.medical_used)),
		}),
	}))
)

watch(visible, (isVisible) => {
	if (!isVisible) {
		return
	}

	step.value = "form"
	saveErrors.value = {}
	preview.reset()
	form.start_date = props.contract?.start_date ?? ""
	form.end_date = props.contract?.end_date ?? ""
	form.annual_leave_days = Number(props.contract?.annual_leave_days ?? props.defaultAnnualLeaveDays ?? 0)
	form.notes = props.contract?.notes ?? ""
	form.link_balance_id = null
})

const save = (isConfirmed: boolean) => {
	const data = {
		...payload(),
		...(isConfirmed ? { confirm_balance_changes: true, balance_choices: preview.choices.value } : {}),
	}
	const options = {
		preserveScroll: true,
		onStart: () => {
			isSaving.value = true
			saveErrors.value = {}
		},
		onSuccess: () => {
			visible.value = false
		},
		onError: (responseErrors: Record<string, string>) => {
			saveErrors.value = responseErrors
			if (Object.keys(responseErrors).some((key) => !["balance_changes", "balance_choices"].includes(key))) {
				step.value = "form"
			}
		},
		onFinish: () => {
			isSaving.value = false
		},
	}

	if (props.contract) {
		router.patch(route("grp.models.employee.contracts.update", { contract: props.contract.id }), data, options)
	} else {
		router.post(route("grp.models.employee.contracts.store", { employee: props.employeeId }), data, options)
	}
}

const review = async () => {
	saveErrors.value = {}

	if (!(await preview.load())) {
		return
	}

	if (preview.requiresConfirmation.value) {
		step.value = "preview"

		return
	}

	save(false)
}

watch(
	() => preview.choices.value,
	(current, previous) => {
		if (step.value === "preview" && JSON.stringify(current) !== JSON.stringify(previous)) {
			preview.load(true)
		}
	}
)
</script>

<template>
	<Dialog
		v-model:visible="visible"
		modal
		:draggable="false"
		:header="step === 'preview' ? ctrans('Review leave balance changes') : isEditing ? ctrans('Edit contract') : ctrans('Add contract')"
		:style="{ width: step === 'preview' ? '40rem' : '34rem', maxWidth: 'calc(100vw - 2rem)' }">
		<form v-if="step === 'form'" class="space-y-4" @submit.prevent="review">
			<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
				<div>
					<label for="contract-start" class="mb-1 block text-sm font-medium text-gray-700">
						{{ ctrans("Start date") }} <span class="text-red-500">*</span>
					</label>
					<DatePicker
						v-model="startDate"
						inputId="contract-start"
						dateFormat="d M yy"
						:manualInput="false"
						:invalid="!!errors.start_date"
						showIcon
						iconDisplay="input"
						fluid
						:class="fieldFocusClass" />
					<p v-if="errors.start_date" class="mt-1 text-sm text-red-500">{{ errors.start_date }}</p>
				</div>
				<div>
					<label for="contract-end" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("End date") }}</label>
					<DatePicker
						v-model="endDate"
						inputId="contract-end"
						:minDate="startDate ?? undefined"
						dateFormat="d M yy"
						:manualInput="false"
						:invalid="!!errors.end_date"
						:placeholder="ctrans('Open-ended')"
						showIcon
						iconDisplay="input"
						showClear
						fluid
						:class="fieldFocusClass" />
					<p v-if="errors.end_date" class="mt-1 text-sm text-red-500">{{ errors.end_date }}</p>
				</div>
			</div>

			<div>
				<label for="contract-annual-leave" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Annual leave days") }}</label>
				<InputNumber
					v-model="form.annual_leave_days"
					inputId="contract-annual-leave"
					:min="0"
					:max="365"
					:step="0.5"
					:minFractionDigits="0"
					:maxFractionDigits="1"
					showButtons
					fluid
					:invalid="!!errors.annual_leave_days"
					:class="fieldFocusClass" />
				<p v-if="errors.annual_leave_days" class="mt-1 text-sm text-red-500">{{ errors.annual_leave_days }}</p>
			</div>

			<div v-if="linkOptions.length">
				<label for="contract-link-balance" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Link existing leave balance") }}</label>
				<Select
					v-model="form.link_balance_id"
					inputId="contract-link-balance"
					:options="linkOptions"
					optionLabel="label"
					optionValue="value"
					showClear
					fluid
					:invalid="!!errors.link_balance_id"
					:placeholder="isEditing ? ctrans('Keep current balance (default)') : ctrans('Create new balance (default)')"
					:class="fieldFocusClass" />
				<p v-if="errors.link_balance_id" class="mt-1 text-sm text-red-500">{{ errors.link_balance_id }}</p>
			</div>

			<div>
				<label for="contract-notes" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Notes") }}</label>
				<Textarea
					v-model="form.notes"
					id="contract-notes"
					rows="3"
					autoResize
					fluid
					maxlength="1000"
					:invalid="!!errors.notes"
					:class="inputFocusClass" />
				<p v-if="errors.notes" class="mt-1 text-sm text-red-500">{{ errors.notes }}</p>
			</div>

			<p v-if="errors.balance_changes || errors.balance_choices" class="text-sm text-red-500">
				{{ errors.balance_changes ?? errors.balance_choices }}
			</p>

			<div class="flex justify-end gap-2 pt-2">
				<Button type="tertiary" :label="ctrans('Cancel')" @click.prevent="visible = false">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" nativeType="submit" :label="ctrans('Save')" :loading="preview.isLoading.value || isSaving" :disabled="!form.start_date">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</form>

		<div v-else class="space-y-4">
			<LeaveBalanceChangesPreview v-model:choices="preview.choices.value" :entries="preview.entries.value" :loading="preview.isLoading.value" />

			<p v-if="errors.balance_changes || errors.balance_choices" class="text-sm text-red-500">
				{{ errors.balance_changes ?? errors.balance_choices }}
			</p>

			<div class="flex flex-wrap justify-end gap-2 pt-2">
				<Button type="tertiary" :label="ctrans('Back')" :disabled="isSaving" @click.prevent="step = 'form'">
					<template #icon>
						<FontAwesomeIcon :icon="faArrowLeft" class="text-[--app-accent]" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" :label="ctrans('Confirm and save')" :loading="isSaving" :disabled="preview.isLoading.value" @click.prevent="save(true)">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</div>
	</Dialog>
</template>
