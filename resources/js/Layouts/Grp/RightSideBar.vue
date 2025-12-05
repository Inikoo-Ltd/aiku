<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Feb 2024 07:58:14 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { useLayoutStore } from "@/Stores/layout"
import { useLiveUsers } from "@/Stores/active-users"
import { onMounted } from "vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faPencil } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { useTruncate } from "@/Composables/useTruncate"
import { Link } from "@inertiajs/vue3"
import { useIsFutureIsAPast } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import ContactList from "@/Components/Chat/ContactList.vue"
library.add(faTimes, faPencil)

const layout = useLayoutStore()

onMounted(() => {
	if (typeof window !== "undefined") {
		if (localStorage.getItem("rightSidebar")) {
			// Read from local storage then store to Pinia
			layout.rightSidebar = JSON.parse(localStorage.getItem("rightSidebar") || "")
		}
	}
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
	<div class="text-xs h-full border-l border-gray-200 bg-white space-y-4">
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
									useIsFutureIsAPast(user?.last_active, 3))
							)
						">
						<Link
							:href="user.current_page?.url || '#'"
							class="text-slate-700 pl-2.5 pr-1.5 flex justify-start items-center py-1 gap-x-2.5">
							<div
								class="flex items-center gap-y-0.5 gap-x-1 truncate"
								:class="[
									{ 'text-red-500': user.action === 'logout' },
									{ 'text-gray-400': user.action === 'leave' },
								]">
								<span class="leading-none font-semibold">{{
									useTruncate(user?.username, 10)
								}}</span>
								<span class="leading-none">-</span>
								<div class="flex items-center gap-x-0.5">
									<FontAwesomeIcon
										v-if="user.current_page?.icon_left?.icon"
										:icon="user.current_page?.icon_left.icon"
										fixed-width
										:class="user.current_page?.icon_left.class"
										aria-hidden="true" />
									<span
										class="opacity-80 whitespace-nowrap leading-3 text-[10px] truncate"
										>{{ user?.current_page?.label || "Unknown" }}</span
									>
									<FontAwesomeIcon
										v-if="user.current_page?.icon_right?.icon"
										:icon="user.current_page?.icon_right.icon"
										fixed-width
										:class="user.current_page?.icon_right.class"
										aria-hidden="true" />
								</div>
							</div>
						</Link>
					</template>
				</template>
			</li>
		</TransitionGroup>

		<div v-if="layout?.rightSidebar?.message?.show">
			<ContactList />
		</div>

		<!-- Add new here -->
	</div>
</template>
