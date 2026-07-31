<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faCheck, faClock } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useFormatTime } from "@/Composables/useFormatTime"

interface ClockingRecord {
	id: number
	clocked_at: string | null
	type: string | null
	is_late: boolean
	notes: string | null
}

interface ClockingSession {
	id: number
	sequence: number
	status: string | null
	is_open: boolean
	starts_at: string | null
	ends_at: string | null
	duration: number | null
	clock_in: ClockingRecord | null
	clock_out: ClockingRecord | null
}

const props = defineProps<{
	activeTimeTracker?: any
	clockingStatus?: string
	todayTimesheet?: any
	clockingSessions?: ClockingSession[]
	timezone?: string
}>()

library.add(faTimes, faCheck, faClock)

const isClockedIn = computed(() => props.clockingStatus === "clocked_in")

const statusClasses = computed(() => ({
	container: isClockedIn.value
		? "bg-green-50 border border-green-200"
		: "bg-gray-50 border border-gray-200",
	iconWrapper: isClockedIn.value ? "bg-green-100" : "bg-gray-200",
	icon: isClockedIn.value ? "text-green-600" : "text-gray-500",
	text: isClockedIn.value ? "text-green-800" : "text-gray-700",
}))

const statusText = computed(() =>
	isClockedIn.value ? trans("You're currently clocked in") : trans("You're currently clocked out")
)

const formatInTimezone = (dateString: string | undefined, options: Intl.DateTimeFormatOptions) => {
	if (!dateString) return "-"
	return new Date(dateString).toLocaleString("en-US", {
		...options,
		timeZone: props.timezone || "UTC",
	})
}

const displayDate = computed(() =>
	formatInTimezone(props.todayTimesheet?.date, {
		year: "numeric",
		month: "2-digit",
		day: "2-digit",
	})
)

const displayTime = (date?: string) =>
	formatInTimezone(date, {
		hour: "numeric",
		minute: "2-digit",
		second: "2-digit",
		hour12: true,
	})

const formatDurationLocal = (seconds: number) => {
	const hours = Math.floor(seconds / 3600)
	const minutes = Math.floor((seconds % 3600) / 60)
	if (hours === 0 && minutes === 0) return trans("0m")
	return hours === 0 ? `${minutes}m` : `${hours}h ${minutes}m`
}

const clockingSessions = computed<ClockingSession[]>(() => props.clockingSessions ?? [])

const hasMultipleSessions = computed(() => clockingSessions.value.length > 1)

const sessionElapsedSeconds = (session: ClockingSession) => {
	if (session.duration !== null && session.duration !== undefined) return session.duration
	if (!session.starts_at) return null
	const end = session.ends_at ? new Date(session.ends_at) : new Date()
	return Math.max(0, Math.floor((end.getTime() - new Date(session.starts_at).getTime()) / 1000))
}
</script>

<template>
	<div v-if="clockingStatus" class="mb-6 p-4 rounded-lg" :class="statusClasses.container">
		<div class="flex items-center justify-between mb-3">
			<div class="flex items-center gap-3">
				<div
					class="w-10 h-10 rounded-full flex items-center justify-center"
					:class="statusClasses.iconWrapper">
					<FontAwesomeIcon
						:icon="isClockedIn ? faCheck : faTimes"
						class="text-lg"
						:class="statusClasses.icon" />
				</div>
				<div>
					<p class="text-sm font-medium" :class="statusClasses.text">
						{{ statusText }}
					</p>
					<p v-if="isClockedIn && activeTimeTracker?.starts_at" class="text-xs text-gray-500">
						{{ trans("Since") }}:
						{{ useFormatTime(activeTimeTracker.starts_at, { formatTime: "hms" }) }}
					</p>
				</div>
			</div>
			<div class="text-right">
				<p class="text-xs text-gray-400">{{ trans("Date") }}</p>
				<p class="text-sm font-semibold text-gray-700">
					{{ displayDate }}
				</p>
			</div>
		</div>

		<div
			v-if="todayTimesheet?.start_at || todayTimesheet?.end_at"
			class="grid grid-cols-2 gap-3 pt-3 border-t border-gray-200">
			<div v-if="todayTimesheet?.start_at" class="text-center p-2 rounded bg-white/50">
				<p class="text-xs text-gray-400">{{ trans("First Clock In") }}</p>
				<p class="text-sm font-semibold text-gray-800">
					{{ displayTime(todayTimesheet.start_at) }}
				</p>
			</div>
			<div v-if="todayTimesheet?.end_at" class="text-center p-2 rounded bg-white/50">
				<p class="text-xs text-gray-400">{{ trans("Last Clock Out") }}</p>
				<p class="text-sm font-semibold text-gray-800">
					{{ displayTime(todayTimesheet.end_at) }}
				</p>
			</div>
		</div>

		<div v-if="todayTimesheet" class="flex gap-4 mt-3 pt-3 border-t border-gray-200">
			<span class="text-xs text-gray-500">
				{{ trans("Working") }}:
				{{ formatDurationLocal(todayTimesheet.working_duration || 0) }}
			</span>
			<span class="text-xs text-gray-500">
				{{ trans("Breaks") }}:
				{{ formatDurationLocal(todayTimesheet.breaks_duration || 0) }}
			</span>
		</div>

		<div v-if="hasMultipleSessions" class="mt-3 pt-3 border-t border-gray-200">
			<p class="text-xs font-semibold text-gray-500 mb-2">
				{{ trans("Clocking Details") }} ({{ clockingSessions.length }})
			</p>
			<div class="max-h-72 space-y-2 overflow-y-auto pr-1">
				<div
					v-for="session in clockingSessions"
					:key="session.id"
					class="rounded-lg bg-white/70 border border-gray-200 p-2">
					<div class="grid grid-cols-2 gap-2">
						<div class="text-center p-1 rounded bg-gray-50">
							<p class="text-[10px] text-gray-400">{{ trans("Clock In") }}</p>
							<p class="text-xs font-semibold text-gray-800">
								{{ displayTime(session.clock_in?.clocked_at ?? session.starts_at ?? undefined) }}
							</p>
						</div>
						<div class="text-center p-1 rounded bg-gray-50">
							<p class="text-[10px] text-gray-400">{{ trans("Clock Out") }}</p>
							<p class="text-xs font-semibold text-gray-800">
								{{
									session.is_open
										? "—"
										: displayTime(session.clock_out?.clocked_at ?? session.ends_at ?? undefined)
								}}
							</p>
						</div>
					</div>
					<div class="mt-1 flex items-center justify-center gap-1.5 text-[10px]">
						<FontAwesomeIcon
							v-if="session.is_open"
							:icon="faClock"
							class="text-amber-500"
							:title="trans('Ongoing')" />
						<template v-else>
							<FontAwesomeIcon
								:icon="faCheck"
								class="text-green-600"
								:title="trans('Completed')" />
							<span class="text-gray-500">
								{{ trans("Duration") }}:
								{{ formatDurationLocal(sessionElapsedSeconds(session) || 0) }}
							</span>
						</template>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
