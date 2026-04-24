<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Feb 2024 07:58:14 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { useLayoutStore } from "@/Stores/layout"
import { useLiveUsers } from "@/Stores/active-users"
import { onMounted, ref } from "vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faPencil } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { useTruncate } from "@/Composables/useTruncate"
import { Link, router } from "@inertiajs/vue3"
import { useIsFutureIsAPast } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import ContactList from "@/Components/Chat/Agent/ContactList.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
library.add(faTimes, faPencil)

const layout = useLayoutStore()
const loadingUserId = ref<number | null>(null)

onMounted(() => {
	if (typeof window !== "undefined") {
		if (localStorage.getItem("rightSidebar")) {
			// Read from local storage then store to Pinia
			layout.rightSidebar = JSON.parse(localStorage.getItem("rightSidebar") || "")
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
</script>

<template>
	<div class="text-xs h-full border-l border-gray-200 bg-text-xs bg-white fixed top-16 transition-all duration-200 ease-in-out right-0 lg:w-[30%] xl:w-[20%]">
		<TransitionGroup name="list" tag="ul">
			<!-- Online Users -->
			<li v-if="layout.rightSidebar.activeUsers.show" class="" key="1">
				<div
					class="pl-2 pr-1.5 bg-slate-300/80 text-slate-700 text-xs font-semibold rounded flex justify-between leading-none">
					<span class="py-1">{{ trans("Active Users") }}</span>
					<div
						@click="onClickRemoveBar('activeUsers')"
						class="flex justify-center items-center cursor-pointer px-1.5 text-slate-400 hover:text-slate-600">
						<FontAwesomeIcon icon="fal fa-times" class="" aria-hidden="true" />
					</div>
				</div>

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
										user?.current_page?.label || trans("Unknown")
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
								{{ trans("logout") }}
							</span>
							<span
								v-else-if="user.action === 'leave'"
								class="flex-shrink-0 text-[9px] bg-gray-100 text-gray-400 rounded px-1 py-0.5 font-medium">
								{{ trans("away") }}
							</span>
							<span
								v-else-if="
									user.action === 'navigate' &&
									useIsFutureIsAPast(user?.last_active, 300)
								"
								class="flex-shrink-0 text-[9px] bg-yellow-50 text-yellow-500 rounded px-1 py-0.5 font-medium">
								{{ trans("idle") }}
							</span>
						</Link>
					</template>
				</template>
			</li>
		</TransitionGroup>

		<div v-if="layout?.rightSidebar?.message?.show">
			<ContactList />
		</div>
	</div>
</template>
