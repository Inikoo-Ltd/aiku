<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Feb 2024 07:58:14 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { useLayoutStore } from "@/Stores/layout"
import { useLiveUsers } from "@/Stores/active-users"
import { defineAsyncComponent, onMounted, ref } from "vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faPencil, faKey } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { useTruncate } from "@/Composables/useTruncate"
import { Link, router } from "@inertiajs/vue3"
import { useFormatTime, useIsFutureIsAPast } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { coveringRoute, openWorkSummary, openWorkTotal } from "@/Composables/useLeaveCovers"

const ContactList = defineAsyncComponent(() => import("@/Components/Chat/Agent/ContactList.vue"))

library.add(faTimes, faPencil, faKey)

const layout = useLayoutStore()
const loadingUserId = ref<number | null>(null)

onMounted(() => {
	if (typeof window !== "undefined") {
		if (localStorage.getItem("rightSidebar")) {
			// Read from local storage then store to Pinia
			layout.rightSidebar = {
				...JSON.parse(localStorage.getItem("rightSidebar") || ""),
				leaveCovers: layout.rightSidebar.leaveCovers,
			}
		}
	}

	router.on('navigate', () => {
		loadingUserId.value = null
	})
})

// Remove the active bar on Right Sidebar
const onClickRemoveBar = (tabName: "activeUsers") => {
	layout.rightSidebar[tabName].show = false
	if (typeof window !== "undefined") {
		localStorage.setItem("rightSidebar", JSON.stringify(layout.rightSidebar))
	}
}

const dismissLeaveCovers = () => {
	layout.rightSidebar.leaveCovers.show = false
	if (typeof window !== "undefined") {
		localStorage.setItem("leaveCoversDismissed", layout.rightSidebar.leaveCovers.coveredLeaveIds)
	}
}
</script>

<template>
	<div class="text-xs h-full border-l border-gray-200 bg-text-xs bg-white fixed top-16 transition-all duration-200 ease-in-out right-0 lg:w-[30%] xl:w-[20%]">
		<TransitionGroup name="list" tag="ul">
			<li v-if="layout.rightSidebar.leaveCovers?.show && layout.leave_covers.length" key="leaveCovers" class="mb-2">
				<div
					class="pl-2 pr-1.5 bg-amber-200/80 text-amber-800 text-xs font-semibold rounded flex justify-between leading-none">
					<span class="py-1">{{ ctrans("Covering for") }} ({{ layout.leave_covers.length }})</span>
					<div
						@click="dismissLeaveCovers"
						class="flex justify-center items-center cursor-pointer px-1.5 text-amber-500 hover:text-amber-700">
						<FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
					</div>
				</div>

				<div class="overflow-y-auto max-h-[calc(50vh-4rem)] divide-y divide-gray-100">
					<Link v-for="cover in layout.leave_covers" :key="cover.id" :href="coveringRoute(cover)" class="block px-2.5 py-2 hover:bg-slate-50 transition-colors">
						<div class="flex items-center justify-between gap-x-2">
							<span class="font-semibold text-slate-700 truncate">{{ cover.employee_name }}</span>
							<span class="flex flex-shrink-0 items-center gap-x-1">
								<span
									v-tooltip="openWorkSummary(cover)"
									class="text-[9px] rounded px-1 py-0.5 font-medium tabular-nums"
									:class="openWorkTotal(cover) ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-400'">
									{{ ctrans(":count to handle", { count: String(openWorkTotal(cover)) }) }}
								</span>
								<span
									class="text-[9px] rounded px-1 py-0.5 font-medium"
									:class="cover.is_ongoing ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500'">
									{{ cover.is_ongoing ? ctrans("now") : ctrans("upcoming") }}
								</span>
							</span>
						</div>

						<div class="text-[10px] text-gray-400 leading-tight">
							{{ cover.type_label }} ·
							{{ useFormatTime(cover.start_date, { formatTime: "d MMM" }) }} – {{ useFormatTime(cover.end_date, { formatTime: "d MMM" }) }}
						</div>

						<div class="mt-1 flex flex-wrap gap-1">
							<span
								v-for="jobPosition in cover.job_positions"
								:key="jobPosition"
								class="text-[10px] bg-slate-100 text-slate-600 rounded px-1.5 py-0.5">
								{{ jobPosition }}
							</span>
							<span v-if="!cover.job_positions.length" class="text-[10px] text-gray-400 italic">
								{{ ctrans("No job positions") }}
							</span>
						</div>

						<div v-if="cover.has_permissions" class="mt-1 flex items-center gap-x-1 text-[10px] text-amber-600">
							<FontAwesomeIcon icon="fal fa-key" fixed-width aria-hidden="true" />
							{{ ctrans("You can use their permissions") }}
						</div>
					</Link>
				</div>
			</li>

			<!-- Online Users -->
			<li v-if="layout.rightSidebar.activeUsers.show" class="" key="1">
				<div
					class="pl-2 pr-1.5 bg-slate-300/80 text-slate-700 text-xs font-semibold rounded flex justify-between leading-none">
					<span class="py-1">{{ ctrans("Active Users") }}</span>
					<div
						@click="onClickRemoveBar('activeUsers')"
						class="flex justify-center items-center cursor-pointer px-1.5 text-slate-400 hover:text-slate-600">
						<FontAwesomeIcon icon="fal fa-times" class="" fixed-width aria-hidden="true" />
					</div>
				</div>

			<div class="overflow-y-auto max-h-[calc(100vh-8rem)]">
				<template
					v-for="(user, index) in useLiveUsers().liveUsers"
					:key="`${user?.id}` + user?.action + index">
					<template
						v-if="
							!(
								(user.action === 'leave' &&
									useIsFutureIsAPast(user?.last_active, 300)) ||
								(user.action === 'logout' &&
									useIsFutureIsAPast(user?.last_active, 300))
							)
						">
						<Link
							:href="user.current_page?.url || '#'"
							@start="() => loadingUserId = user.id"
							@finish="() => loadingUserId = null"
							class="pl-2.5 pr-2 flex items-center py-1.5 gap-x-2 hover:bg-slate-50 transition-colors"
							:class="{
								'opacity-75':
									(user.action === 'navigate' &&
										useIsFutureIsAPast(user?.last_active, 300)) ||
									user.action === 'leave',
								'opacity-50': user.action === 'logout',
							}">
							<!-- Status dot / spinner -->
							<span class="flex-shrink-0 w-4 h-4 flex items-center justify-center">
								<LoadingIcon
									v-if="loadingUserId === user.id"
									class="text-slate-400 text-xs"
								/>
								<span
									v-else
									class="w-2 h-2 rounded-full"
									:class="{
										'bg-green-400':
											user.action === 'navigate' &&
											!useIsFutureIsAPast(user?.last_active, 300),
										'bg-yellow-400 animate-pulse':
											user.action === 'navigate' &&
											useIsFutureIsAPast(user?.last_active, 300),
										'bg-gray-300': user.action === 'leave',
										'bg-red-400': user.action === 'logout',
									}" />
							</span>

							<div class="flex flex-col min-w-0 flex-1">
								<!-- Name -->
								<span
									class="font-semibold leading-none mb-0.5 truncate"
									:class="{
										'text-slate-700':
											user.action !== 'logout' && user.action !== 'leave',
										'text-gray-400': user.action === 'leave',
										'text-red-500': user.action === 'logout',
									}">
									{{ useTruncate(user?.contact_name || user?.username, 16) }}
								</span>

								<!-- Current page -->
								<div
									class="flex items-center gap-x-0.5 text-[10px] text-gray-400 leading-none">
									<FontAwesomeIcon
										v-if="user.current_page?.icon_left?.icon"
										:icon="user.current_page?.icon_left.icon"
										fixed-width
										:class="user.current_page?.icon_left.class"
										aria-hidden="true" />
									<span class="truncate">{{
										user?.current_page?.label || ctrans("Unknown")
									}}</span>
									<FontAwesomeIcon
										v-if="user.current_page?.icon_right?.icon"
										:icon="user.current_page?.icon_right.icon"
										fixed-width
										:class="user.current_page?.icon_right.class"
										aria-hidden="true" />
								</div>
							</div>

							<!-- Status badge -->
							<span
								v-if="user.action === 'logout'"
								class="flex-shrink-0 text-[9px] bg-red-100 text-red-500 rounded px-1 py-0.5 font-medium">
								{{ ctrans("logout") }}
							</span>
							<span
								v-else-if="user.action === 'leave'"
								class="flex-shrink-0 text-[9px] bg-gray-100 text-gray-400 rounded px-1 py-0.5 font-medium">
								{{ ctrans("away") }}
							</span>
							<span
								v-else-if="
									user.action === 'navigate' &&
									useIsFutureIsAPast(user?.last_active, 300)
								"
								class="flex-shrink-0 text-[9px] bg-yellow-50 text-yellow-500 rounded px-1 py-0.5 font-medium">
								{{ ctrans("idle") }}
							</span>
						</Link>
					</template>
				</template>
				</div>
			</li>
		</TransitionGroup>

		<div v-if="layout?.rightSidebar?.message?.show">
			<ContactList />
		</div>
	</div>
</template>
