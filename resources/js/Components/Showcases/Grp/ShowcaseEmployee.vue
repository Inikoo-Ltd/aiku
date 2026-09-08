<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 14:00:48 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import { useFormatTime, useHMAP, useSecondsToMS } from "@/Composables/useFormatTime"
import { faPen, faSyncAlt, faDownload, faPlus } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PermissionsPictogram from "@/Components/DataDisplay/PermissionsPictogram.vue"
import { trans } from "laravel-vue-i18n"
import { Link, router } from "@inertiajs/vue3"
import { QrcodeCanvas } from "qrcode.vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"

const props = defineProps<{
	data: {
		employee: any
		pin: any
		regenerate_pin_route?: string
		attendance?: {
			days_present: number
			late_days: number
			total_seconds: number
			average_seconds: number
			leave_days_this_year: number
			last_clocking_at: string | null
			recent: { id: number; date: string; start_at: string | null; end_at: string | null; total_duration: number; is_late: boolean; is_open: boolean; route: { name: string; parameters: Record<string, unknown> } }[]
		}
		leave?: {
			balance: { annual_used: number; annual_remaining: number; medical_used: number; unpaid_used: number; period_end: string | null } | null
			recent: { id: number; type: string; start_date: string; end_date: string; days: number; status: string }[]
			route: { name: string; parameters: Record<string, unknown> }
		}
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
const hours = (seconds: number): string => seconds ? `${(seconds / 3600).toFixed(1)}h` : "-"

const stateClass: Record<string, string> = {
	hired: "bg-blue-50 text-blue-700 ring-blue-200",
	working: "bg-green-50 text-green-700 ring-green-200",
	leaving: "bg-amber-50 text-amber-700 ring-amber-200",
	left: "bg-gray-100 text-gray-600 ring-gray-200",
}

const initials = (name: string | undefined) =>
	(name ?? "").split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join("")

const leaveStatusClass: Record<string, string> = {
	approved: "text-green-700 bg-green-50",
	pending: "text-amber-700 bg-amber-50",
	rejected: "text-red-700 bg-red-50",
}

const attendanceStats = computed(() => {
	const attendance = props.data?.attendance
	if (!attendance) {
		return []
	}
	return [
		{ label: trans("Days present"), value: attendance.days_present },
		{ label: trans("Late"), value: attendance.late_days },
		{ label: trans("Hours worked"), value: hours(attendance.total_seconds) },
		{ label: trans("Average per day"), value: hours(attendance.average_seconds) },
		{ label: trans("Leave days this year"), value: attendance.leave_days_this_year },
	]
})
</script>

<template>
	<div class="grid gap-4 px-4 py-4 xl:grid-cols-3">
		<div class="space-y-4 xl:col-span-2">
			<div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
				<div class="flex items-start gap-4">
					<img v-if="data?.employee?.data?.avatar" :src="data.employee.data.avatar" :alt="data.employee.data.contact_name" class="h-14 w-14 shrink-0 rounded-full object-cover bg-gray-100" />
					<div v-else class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-lg font-semibold text-indigo-700">{{ initials(data?.employee?.data?.contact_name) }}</div>
					<div class="min-w-0 flex-1">
						<div class="flex flex-wrap items-center gap-2">
							<span class="text-lg font-semibold text-gray-800">{{ data?.employee?.data?.contact_name }}</span>
							<span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1" :class="stateClass[data?.employee?.data?.state] ?? stateClass.left">{{ data?.employee?.data?.state }}</span>
							<span v-if="data?.employee?.data?.is_on_probation" class="rounded-full bg-yellow-50 px-2 py-0.5 text-xs font-medium text-yellow-800 ring-1 ring-yellow-200">{{ trans("Probation") }}</span>
						</div>
						<div class="mt-0.5 text-sm text-gray-500">
							{{ data?.employee?.data?.job_title || trans("No job title") }}
							<span v-if="data?.employee?.data?.employment_type" class="text-gray-400"> · {{ data.employee.data.employment_type }}</span>
						</div>
						<div v-if="data.employee?.data?.job_positions?.length" class="mt-2 flex flex-wrap gap-1">
							<span v-for="job in data.employee.data.job_positions" :key="job.slug" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ job.name }}</span>
						</div>
					</div>
				</div>

				<dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 border-t border-gray-100 pt-4 text-sm md:grid-cols-3">
					<div><dt class="text-gray-400">{{ trans("Started") }}</dt><dd class="font-medium text-gray-800">{{ useFormatTime(data?.employee?.data?.employment_start_at) || "-" }}</dd></div>
					<div><dt class="text-gray-400">{{ trans("Length of service") }}</dt><dd class="font-medium text-gray-800">{{ data?.employee?.data?.length_of_service || "-" }}</dd></div>
					<div v-if="data?.employee?.data?.employment_end_at"><dt class="text-gray-400">{{ trans("Ended") }}</dt><dd class="font-medium text-gray-800">{{ useFormatTime(data.employee.data.employment_end_at) }}</dd></div>
					<div><dt class="text-gray-400">{{ trans("Email") }}</dt><dd class="truncate font-medium text-gray-800">{{ data?.employee?.data?.email || "-" }}</dd></div>
					<div><dt class="text-gray-400">{{ trans("Phone") }}</dt><dd class="font-medium text-gray-800">{{ data?.employee?.data?.phone || "-" }}</dd></div>
					<div><dt class="text-gray-400">{{ trans("Emergency contact") }}</dt><dd class="truncate font-medium text-gray-800">{{ formatEmergencyContact(data?.employee?.data?.emergency_contact) }}</dd></div>
					<div v-if="data?.employee?.data?.user"><dt class="text-gray-400">{{ trans("User") }}</dt><dd class="font-medium text-gray-800">{{ data.employee.data.user.username }}</dd></div>
				</dl>
			</div>

			<div v-if="data?.attendance" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
				<div class="flex items-baseline justify-between">
					<h3 class="text-sm font-semibold text-gray-700">{{ trans("Attendance") }}</h3>
					<div class="flex items-center gap-3 text-xs">
						<span class="text-gray-400">{{ trans("Last 30 days") }}</span>
						<Link :href="route('grp.org.hr.employees.show.timesheets.index', route().params)" class="text-indigo-600 hover:underline">{{ trans("All timesheets") }}</Link>
					</div>
				</div>
				<dl class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-5">
					<div v-for="stat in attendanceStats" :key="stat.label" class="rounded-lg bg-gray-50 px-3 py-2">
						<dd class="text-xl font-bold tabular-nums text-gray-800">{{ stat.value }}</dd>
						<dt class="truncate text-xs text-gray-500">{{ stat.label }}</dt>
					</div>
				</dl>

				<table v-if="data.attendance.recent.length" class="mt-4 w-full text-sm">
					<thead>
						<tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
							<th class="py-1.5 pr-3">{{ trans("Date") }}</th>
							<th class="py-1.5 px-3">{{ trans("Start") }}</th>
							<th class="py-1.5 px-3">{{ trans("End") }}</th>
							<th class="py-1.5 pl-3 text-right">{{ trans("Worked") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="row in data.attendance.recent" :key="row.id" class="hover:bg-gray-50">
							<td class="py-1.5 pr-3">
								<Link :href="route(row.route.name, row.route.parameters)" class="font-medium text-gray-800 hover:text-indigo-600 hover:underline">{{ useFormatTime(row.date) }}</Link>
							</td>
							<td class="py-1.5 px-3 tabular-nums" :class="row.is_late ? 'font-medium text-red-600' : 'text-gray-700'">{{ useHMAP(row.start_at) }}</td>
							<td class="py-1.5 px-3 tabular-nums text-gray-700">
								<span v-if="row.is_open" class="italic text-blue-500">{{ trans("Still working") }}</span>
								<span v-else>{{ useHMAP(row.end_at) }}</span>
							</td>
							<td class="py-1.5 pl-3 text-right tabular-nums text-gray-700">{{ row.is_open || row.total_duration <= 0 ? "-" : useSecondsToMS(row.total_duration) }}</td>
						</tr>
					</tbody>
				</table>
				<div v-else class="mt-4 text-center text-sm text-gray-400">{{ trans("No attendance recorded in the last 30 days.") }}</div>
			</div>

			<div v-if="data?.permissions_pictogram" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
				<PermissionsPictogram :data_pictogram="data.permissions_pictogram" />
			</div>
		</div>

		<div class="space-y-4">
			<div v-if="data?.leave" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
				<div class="flex items-center justify-between">
					<h3 class="text-sm font-semibold text-gray-700">{{ trans("Leave") }}</h3>
					<Link :href="route(data.leave.route.name, data.leave.route.parameters)" class="text-xs text-indigo-600 hover:underline">{{ trans("All leaves") }}</Link>
				</div>
				<dl v-if="data.leave.balance" class="mt-3 grid grid-cols-3 gap-2 text-center">
					<div class="rounded-lg bg-gray-50 px-2 py-2">
						<dd class="text-xl font-bold tabular-nums text-gray-800">{{ data.leave.balance.annual_remaining }}</dd>
						<dt class="text-xs text-gray-500">{{ trans("Annual left") }}</dt>
					</div>
					<div class="rounded-lg bg-gray-50 px-2 py-2">
						<dd class="text-xl font-bold tabular-nums text-gray-800">{{ data.leave.balance.annual_used }}</dd>
						<dt class="text-xs text-gray-500">{{ trans("Annual used") }}</dt>
					</div>
					<div class="rounded-lg bg-gray-50 px-2 py-2">
						<dd class="text-xl font-bold tabular-nums text-gray-800">{{ data.leave.balance.medical_used }}</dd>
						<dt class="text-xs text-gray-500">{{ trans("Sick days") }}</dt>
					</div>
				</dl>
				<div v-else class="mt-2 text-xs text-gray-400">{{ trans("No leave balance for the current contract.") }}</div>
				<div v-if="data.leave.recent.length" class="mt-3 divide-y divide-gray-100 text-sm">
					<div v-for="leave in data.leave.recent" :key="leave.id" class="flex items-center justify-between gap-2 py-1.5">
						<div class="min-w-0">
							<div class="truncate font-medium text-gray-800">{{ leave.type }}</div>
							<div class="text-xs text-gray-500">{{ useFormatTime(leave.start_date) }}<template v-if="leave.end_date !== leave.start_date"> – {{ useFormatTime(leave.end_date) }}</template> · {{ leave.days }}d</div>
						</div>
						<span class="rounded-full px-2 py-0.5 text-xs" :class="leaveStatusClass[leave.status] ?? 'bg-gray-100 text-gray-600'">{{ leave.status }}</span>
					</div>
				</div>
				<div v-else class="mt-3 text-xs text-gray-400">{{ trans("No leave recorded.") }}</div>
			</div>

			<div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
				<div class="flex items-center justify-between">
					<h3 class="text-sm font-semibold text-gray-700">{{ trans("Working hours") }}</h3>
					<span v-if="data?.work_schedule?.source === 'organisation'" class="text-xs text-gray-400">{{ trans("Organisation default") }}</span>
				</div>
				<div v-if="data?.work_schedule?.days?.length" class="mt-2 divide-y divide-gray-100 text-sm">
					<div v-for="day in data.work_schedule.days" :key="day.day_of_week" class="flex items-center justify-between py-1.5">
						<span class="text-gray-500">{{ trans(dayOfWeekLabels[day.day_of_week]) }}</span>
						<span class="tabular-nums font-medium text-gray-800">
							{{ formatHM(day.start_time) }} – {{ formatHM(day.end_time) }}
							<span v-for="(brk, index) in day.breaks" :key="index" class="ml-2 text-xs font-normal text-gray-400">{{ brk.name || trans("Break") }} {{ formatHM(brk.start_time) }}–{{ formatHM(brk.end_time) }}</span>
						</span>
					</div>
				</div>
				<div v-else class="mt-2 text-sm text-gray-400">{{ trans("No working hours set") }}</div>
				<Link :href="route('grp.org.hr.employees.edit', { ...route().params, section: 'working_hours' })" @start="() => (isVisitWorkingHours = true)" @finish="() => (isVisitWorkingHours = false)" class="mt-3 inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:underline">
					<FontAwesomeIcon :icon="faPen" fixed-width /> {{ trans("Edit working hours") }}
				</Link>
			</div>

			<div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
				<h3 class="text-sm font-semibold text-gray-700">{{ trans("Clocking PIN") }}</h3>
				<template v-if="data?.pin">
					<div class="mt-3 flex items-center gap-4">
						<div ref="qrCanvasRef" class="shrink-0 rounded-lg bg-white p-1.5 ring-1 ring-gray-200">
							<QrcodeCanvas :value="data.pin" :size="96" level="H" />
						</div>
						<div class="min-w-0">
							<div class="font-mono text-2xl font-semibold tracking-[0.3em] text-gray-800">{{ data.pin }}</div>
							<div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
								<button type="button" class="inline-flex items-center gap-1.5 text-indigo-600 hover:underline disabled:opacity-50" :disabled="isRegeneratingPin" @click="regeneratePin">
									<FontAwesomeIcon :icon="faSyncAlt" fixed-width :spin="isRegeneratingPin" /> {{ trans("Regenerate") }}
								</button>
								<button type="button" class="inline-flex items-center gap-1.5 text-indigo-600 hover:underline" @click="downloadQr">
									<FontAwesomeIcon :icon="faDownload" fixed-width /> {{ trans("Download QR") }}
								</button>
							</div>
						</div>
					</div>
				</template>
				<template v-else>
					<div class="mt-2 text-sm text-gray-400">{{ trans("No clocking machine PIN yet") }}</div>
					<Link :href="route('grp.org.hr.employees.edit', { ...route().params, section: 'pin' })" @start="() => (isVisitClockingMachine = true)" @finish="() => (isVisitClockingMachine = false)" class="mt-3 inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:underline">
						<FontAwesomeIcon :icon="faPlus" fixed-width /> {{ trans("Add clocking machine PIN") }}
					</Link>
				</template>
			</div>
		</div>
	</div>
</template>
