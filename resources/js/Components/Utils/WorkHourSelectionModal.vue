<script setup lang="ts">
import { ref, computed, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { Dialog } from "primevue"
import Dropdown from "primevue/dropdown"
import RadioButton from "primevue/radiobutton"
import axios from "axios"

interface WorkSchedule {
	id: number
	name: string
	type: string
	is_active: boolean
}

interface Props {
	visible: boolean
	allowShift: boolean
	shiftSchedules: WorkSchedule[]
}

interface Emits {
	(e: "update:visible", value: boolean): void
	(e: "confirm", workScheduleId: number | null): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const localVisible = computed({
	get: () => props.visible,
	set: (value) => emit("update:visible", value),
})

const selectedType = ref<"default" | "shift">("default")
const selectedShiftId = ref<number | null>(null)

const availableShifts = computed(() => {
	return props.shiftSchedules.filter((s) => s.type === "shift" && s.is_active)
})

const canConfirm = computed(() => {
	if (selectedType.value === "default") {
		return true
	}
	return selectedShiftId.value !== null
})

const closeModal = () => {
	emit("update:visible", false)
}

const handleConfirm = () => {
	const workScheduleId = selectedType.value === "shift" ? selectedShiftId.value : null
	emit("confirm", workScheduleId)
	closeModal()
}

watch(
	() => props.visible,
	(newValue) => {
		if (newValue) {
			selectedType.value = "default"
			selectedShiftId.value = null
		}
	}
)
</script>

<template>
	<Dialog
		v-model:visible="localVisible"
		modal
		:closable="false"
		:style="{ width: '420px' }"
		appendTo="body">
		<div class="space-y-6 py-4">
			<div>
				<h3 class="text-xl font-semibold text-gray-800 mb-2">
					{{ trans("Select Working Hours") }}
				</h3>
				<p class="text-sm text-gray-500">
					{{ trans("Choose your work schedule for this clocking") }}
				</p>
			</div>

			<div class="space-y-4">
				<div
					class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors"
					:class="{ 'border-blue-500 bg-blue-50': selectedType === 'default' }"
					@click="selectedType = 'default'">
					<RadioButton v-model="selectedType" value="default" inputId="default" />
					<label for="default" class="flex-1 cursor-pointer">
						<div class="font-medium text-gray-800">{{ trans("Default Schedule") }}</div>
						<div class="text-xs text-gray-500">
							{{ trans("Your regular working hours") }}
						</div>
					</label>
				</div>

				<div
					class="flex items-center gap-3 p-3 border rounded-lg transition-colors"
					:class="[
						allowShift
							? 'cursor-pointer hover:bg-gray-50'
							: 'opacity-50 cursor-not-allowed bg-gray-100',
						{ 'border-blue-500 bg-blue-50': selectedType === 'shift' },
					]"
					@click="allowShift ? (selectedType = 'shift') : null">
					<RadioButton
						v-model="selectedType"
						value="shift"
						inputId="shift"
						:disabled="!allowShift" />
					<label
						for="shift"
						class="flex-1"
						:class="allowShift ? 'cursor-pointer' : 'cursor-not-allowed'">
						<div class="font-medium text-gray-800">{{ trans("Shift Schedule") }}</div>
						<div class="text-xs text-gray-500">
							{{
								allowShift
									? trans("Select a specific shift")
									: trans("Shift not enabled for your account")
							}}
						</div>
					</label>
				</div>

				<div v-if="selectedType === 'shift' && allowShift" class="ml-8 mt-3">
					<label class="block text-sm font-medium text-gray-700 mb-2">
						{{ trans("Select Shift") }}
					</label>
					<Dropdown
						v-model="selectedShiftId"
						:options="availableShifts"
						optionLabel="name"
						optionValue="id"
						:placeholder="trans('Choose a shift')"
						class="w-full"
						:disabled="!allowShift" />
					<p v-if="availableShifts.length === 0" class="text-xs text-gray-500 mt-1">
						{{ trans("No shift schedules available") }}
					</p>
				</div>
			</div>

			<div class="flex gap-3 pt-4">
				<Button :label="trans('Cancel')" type="exit" @click="closeModal" full />
				<Button
					:label="trans('Confirm')"
					type="save"
					@click="handleConfirm"
					:disabled="!canConfirm"
					full />
			</div>
		</div>
	</Dialog>
</template>
