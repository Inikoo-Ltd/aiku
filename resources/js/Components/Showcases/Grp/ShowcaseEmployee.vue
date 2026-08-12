<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 14:00:48 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
-->

<script setup lang="ts">
import AppLogin from "@/Components/Forms/Fields/AppLogin.vue"
import { ref } from "vue"
import Image from "@common/Components/Image.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { faPen, faSyncAlt, faDownload } from "@fal"
import PermissionsPictogram from "@/Components/DataDisplay/PermissionsPictogram.vue"
import { trans } from "laravel-vue-i18n"
import { Link, router } from "@inertiajs/vue3"
import Tag from "@/Components/Tag.vue"
import { QrcodeCanvas } from "qrcode.vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"

const props = defineProps<{
	data: {
		employee: any
		pin: any
		regenerate_pin_route?: string
		work_schedule?: {
			source: "employee" | "organisation" | null
			days: {
				day_of_week: number
				start_time: string | null
				end_time: string | null
				breaks: { name: string | null; start_time: string | null; end_time: string | null }[]
			}[]
		}
	}
}>()

const isVisitClockingMachine = ref(false)
const isVisitWorkingHours = ref(false)
const isRegeneratingPin = ref(false)
const qrCanvasRef = ref<any | null>(null)

const regeneratePin = async () => {
	if (!props.data?.regenerate_pin_route) {
		return
	}

	isRegeneratingPin.value = true

	try {
		await axios.post(props.data.regenerate_pin_route)

		notify({
			title: trans("Success"),
			text: trans("A new clocking PIN and QR code have been generated."),
			type: "success",
		})

		router.reload({ only: ["showcase"] })
	} catch (e: any) {
		notify({
			title: trans("Failed"),
			text: e?.response?.data?.message ?? trans("Failed to generate a new PIN."),
			type: "error",
		})
	} finally {
		isRegeneratingPin.value = false
	}
}

const downloadQr = () => {
	const container = qrCanvasRef.value as HTMLElement | undefined
	const canvas = container?.querySelector?.("canvas") as HTMLCanvasElement | null

	if (!canvas) {
		notify({
			title: trans("Failed"),
			text: trans("QR code is not ready yet, please try again."),
			type: "error",
		})
		return
	}

	const name = props.data?.employee?.data?.contact_name ?? ""
	const dpr = window.devicePixelRatio || 1
	const padding = 24 * dpr
	const fontSize = 20 * dpr
	const labelTopGap = 16 * dpr
	const labelBottomGap = 20 * dpr
	const labelHeight = name ? labelTopGap + fontSize + labelBottomGap : 0

	const composite = document.createElement("canvas")
	composite.width = canvas.width + padding * 2
	composite.height = canvas.height + padding * 2 + labelHeight

	const ctx = composite.getContext("2d")
	if (!ctx) {
		return
	}

	ctx.fillStyle = "#ffffff"
	ctx.fillRect(0, 0, composite.width, composite.height)
	ctx.drawImage(canvas, padding, padding)

	if (name) {
		ctx.fillStyle = "#1f2937"
		ctx.textAlign = "center"
		ctx.textBaseline = "alphabetic"

		let currentFontSize = fontSize
		const maxTextWidth = composite.width - padding
		ctx.font = `600 ${currentFontSize}px sans-serif`
		while (ctx.measureText(name).width > maxTextWidth && currentFontSize > 10 * dpr) {
			currentFontSize -= 1 * dpr
			ctx.font = `600 ${currentFontSize}px sans-serif`
		}

		ctx.fillText(name, composite.width / 2, canvas.height + padding + labelTopGap + fontSize)
	}

	const link = document.createElement("a")
	link.download = `${props.data?.employee?.data?.slug ?? "employee"}-clocking-qr.png`
	link.href = composite.toDataURL("image/png")
	document.body.appendChild(link)
	link.click()
	document.body.removeChild(link)
}

const formatEmergencyContact = (value: any): string => {
	if (!value) {
		return "-"
	}
	if (typeof value === "string") {
		return value || "-"
	}
	const parts = [value.contact, value.phone_number, value.address, value.status].filter(Boolean)
	return parts.length ? parts.join(" | ") : "-"
}

const dayOfWeekLabels: Record<number, string> = {
	1: "Monday", 2: "Tuesday", 3: "Wednesday", 4: "Thursday", 5: "Friday", 6: "Saturday", 7: "Sunday",
}

const formatHM = (value: string | null): string => value ? value.slice(0, 5) : "-"
</script>

<template>
	<div class="px-6 py-6 grid lg:grid-cols-9 gap-x-8">
		<div
			class="lg:col-span-6 ring-1 ring-gray-300 shadow rounded-2xl py-6 grid lg:grid-cols-2 gap-y-4">
			<div class="flex flex-col gap-y-4 px-8">
				<div
					class="mx-auto w-fit aspect-square rounded-full overflow-hidden md:h-56"
					:src="'person.imageUrl'"
					alt="">
					<Image :src="data?.employee?.data.avatar" />
				</div>

				<div class="col-span-4">
					<div class="flex items-end gap-x-2">
						<div class="font-semibold text-2xl">
							{{ data?.employee?.data.contact_name }}
						</div>
						<Tag
							v-if="data?.employee?.data?.is_on_probation"
							label="Probation"
							class="bg-yellow-100 text-yellow-800" />
						<div class="text-gray-400">
							#{{ data?.employee?.data.id }} {{ data?.employee?.data.username }}
						</div>
					</div>

					<div class="mt-2">
						<div class="text-gray-500 text-sm mb-1">
							{{ trans("Job position") }}
						</div>
						<div
							v-if="data.employee?.data?.job_positions?.length"
							class="flex gap-x-2 gap-y-1">
							<Tag
								v-for="job in data.employee?.data?.job_positions"
								:key="job.slug"
								:label="job.name"
								noHoverColor
								stringToColor />
						</div>
						<!-- <div v-if="data?.employee?.data.about" class="text-gray-500">
                            {{ data?.employee?.data.about }}
                        </div> -->
						<div v-else class="text-gray-400 italic text-sm">
							{{ trans('Have no job position') }}
						</div>
					</div>

					<div v-if="data?.employee?.data?.length_of_service" class="mt-3">
						<div class="text-gray-500 text-sm mb-1">
							{{ trans("Length of Service") }}
						</div>
						<div class="text-gray-700 font-medium">
							{{ data?.employee?.data?.length_of_service }}
						</div>
					</div>
				</div>
			</div>

			<!-- Section: Contact Information -->
			<div class="mt-6 border-l border-gray-300 px-6 space-y-3">
				<div class="font-semibold">{{ trans("Contact Information") }}</div>

				<div class="space-y-2">
					<div class="grid grid-cols-2 xl:grid-cols-3 gap-x-5 text-sm">
						<div class="text-gray-400">
							{{ trans("Contact Name") }}
						</div>
						<div class="xl:col-span-2 font-medium text-right lg:text-left">
							{{ data?.employee?.data.contact_name }}
						</div>
					</div>

					<div class="grid grid-cols-2 xl:grid-cols-3 gap-x-5 text-sm">
						<div class="text-gray-400">
							{{ trans("Emergency Contact") }}
						</div>
						<div class="xl:col-span-2 font-medium text-right lg:text-left">
							{{ formatEmergencyContact(data?.employee?.data.emergency_contact) }}
						</div>
					</div>

					<div class="grid grid-cols-2 xl:grid-cols-3 gap-x-5 text-sm">
						<div class="text-gray-400">
							{{ trans("Start Working") }}
						</div>
						<div class="xl:col-span-2 font-medium text-right lg:text-left">
							{{ useFormatTime(data?.employee?.data.employment_start_at) }}
						</div>
					</div>

					<div v-if="data?.employee?.data?.employment_end_at" class="grid grid-cols-2 xl:grid-cols-3 gap-x-5 text-sm">
						<div class="text-gray-400">
							{{ trans("End Working") }}
						</div>
						<div class="xl:col-span-2 font-medium text-right lg:text-left">
							{{ useFormatTime(data?.employee?.data.employment_end_at) }}
						</div>
					</div>

					<div class="grid grid-cols-2 xl:grid-cols-3 gap-x-5 text-sm">
						<div class="text-gray-400">
							{{ trans("State") }}
						</div>
						<div class="xl:col-span-2 font-medium text-right lg:text-left">
							<Tag :label="data?.employee?.data.state"></Tag>
						</div>
					</div>

					<!-- <div class="grid grid-cols-2 xl:grid-cols-3 gap-x-5 text-sm">
                        <div class="text-gray-400">
                            {{ trans("Job Position") }}
                        </div>
                        <div class="xl:col-span-2 font-medium text-right lg:text-left">
                            {{ data?.employee?.data.job_positions?.length }}
                        </div>
                    </div> -->
				</div>
			</div>
		</div>

		<div class="mt-4 lg:mt-0 w-full lg:max-w-lg lg:col-span-3 flex flex-col gap-y-4">
		<div
			class="w-full h-fit grid ring-1 ring-gray-300 shadow rounded-2xl py-6 px-4 gap-y-6">
			<template v-if="data?.pin">
				<div class="flex justify-center">
					<div ref="qrCanvasRef" class="rounded-lg bg-white p-3 ring-1 ring-gray-200">
						<QrcodeCanvas :value="data.pin" :size="150" level="H" />
					</div>
				</div>

				<div class="text-center text-sm font-medium text-gray-700">
					{{ data?.employee?.data?.contact_name }}
				</div>

				<div class="flex flex-nowrap justify-center gap-2">
					<div
						v-for="(value, index) in Array.from(data.pin)"
						:key="index"
						class="h-6 xl:h-8 aspect-square flex items-center justify-center text-sm xl:text-lg font-semibold border border-gray-300 rounded xl:rounded-md shadow-sm bg-gray-50">
						{{ value }}
					</div>
				</div>

				<div class="flex items-center justify-center gap-x-3">
					<Button
						@click="regeneratePin"
						type="tertiary"
						:loading="isRegeneratingPin"
						:label="trans('Generate QR')"
						:icon="faSyncAlt" />
					<Button
						@click="downloadQr"
						type="secondary"
						:label="trans('Download')"
						:icon="faDownload" />
				</div>
			</template>

			<template v-else>
				<div class="text-center text-gray-400 italic">
					{{ trans("No Clocking machine PIN yet") }}
				</div>
				<Link
					:href="
						route('grp.org.hr.employees.edit', { ...route().params, section: 'pin' })
					"
					@start="() => (isVisitClockingMachine = true)"
					@finish="() => (isVisitClockingMachine = false)"
					class="mx-auto">
					<Button
						type="secondary"
						:loading="isVisitClockingMachine"
						:label="trans('Add Clocking machine PIN')"
						icon="fal fa-plus" />
				</Link>
			</template>
		</div>

		<div
			class="w-full h-fit grid ring-1 ring-gray-300 shadow rounded-2xl py-6 px-4 gap-y-4">
			<div class="flex items-center justify-between">
				<div class="font-semibold">{{ trans("Working hours") }}</div>
				<div v-if="data?.work_schedule?.source === 'organisation'" class="text-xs text-gray-400 italic">
					{{ trans("From organisation default") }}
				</div>
			</div>

			<template v-if="data?.work_schedule?.days?.length">
				<div class="divide-y divide-gray-100">
					<div v-for="day in data.work_schedule.days" :key="day.day_of_week" class="py-2 text-sm">
						<div class="flex items-center justify-between">
							<span class="text-gray-500">{{ trans(dayOfWeekLabels[day.day_of_week]) }}</span>
							<span class="font-medium text-gray-800">
								{{ formatHM(day.start_time) }} – {{ formatHM(day.end_time) }}
							</span>
						</div>
						<div v-if="day.breaks?.length" class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5">
							<span
								v-for="(brk, index) in day.breaks"
								:key="index"
								class="text-xs text-gray-400">
								{{ brk.name || trans("Break") }} {{ formatHM(brk.start_time) }}–{{ formatHM(brk.end_time) }}
							</span>
						</div>
					</div>
				</div>
			</template>

			<template v-else>
				<div class="text-center text-gray-400 italic text-sm">
					{{ trans("No working hours set") }}
				</div>
			</template>

			<Link
				:href="
					route('grp.org.hr.employees.edit', { ...route().params, section: 'working_hours' })
				"
				@start="() => (isVisitWorkingHours = true)"
				@finish="() => (isVisitWorkingHours = false)"
				class="mx-auto">
				<Button
					type="secondary"
					:loading="isVisitWorkingHours"
					:label="trans('Edit working hours')"
					:icon="faPen" />
			</Link>
		</div>
		</div>
	</div>
	<div class="flex py-4 px-8 gap-x-8">
		<div v-if="data?.permissions_pictogram" class="sm:col-span-2">
			<PermissionsPictogram :data_pictogram="data?.permissions_pictogram" />
		</div>
	</div>
</template>
