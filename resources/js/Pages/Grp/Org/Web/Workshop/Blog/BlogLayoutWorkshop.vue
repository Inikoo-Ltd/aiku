<script setup lang="ts">
import { ref, computed, provide, watch } from "vue"
import { Head } from "@inertiajs/vue3"
import axios from "axios"
import { cloneDeep, get, set } from "lodash-es"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import Publish from "@/Components/Publish.vue"
import ScreenView from "@/Components/ScreenView.vue"
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue"
import BlogListWorkshop from "@/Components/CMS/Webpage/BlogList/BlogListWorkshop.vue"
import { layoutBlueprint } from "@/Components/CMS/Webpage/BlogList/Blueprint"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: any
	hasUnpublishedChanges: boolean
	autosaveRoute: routeType
	publishRoute: routeType
	blogIndexUrl: string
}>()

const layout = ref(cloneDeep(props.data ?? {}))
const comment = ref("")
const isPublishing = ref(false)
const isDirty = ref(props.hasUnpublishedChanges)
const statusSave = ref<null | "loading" | "success" | "error">(null)
const currentView = ref<"desktop" | "tablet" | "mobile">("desktop")

provide("currentView", currentView)

const fieldValue = computed(() => get(layout.value, ["data", "fieldValue"], {}))
const blueprint = layoutBlueprint as any[]

const previewFieldValue = computed(() => ({
	...fieldValue.value,
	show_view_all: false,
}))

let statusTimeout: ReturnType<typeof setTimeout> | null = null
const setStatus = (newStatus: null | "loading" | "success" | "error") => {
	statusSave.value = newStatus
	if (statusTimeout) clearTimeout(statusTimeout)
	if (newStatus === "success" || newStatus === "error") {
		statusTimeout = setTimeout(() => (statusSave.value = null), 3000)
	}
}

let controller: AbortController | null = null
let debounce: ReturnType<typeof setTimeout> | null = null

const autoSave = async () => {
	if (controller) {
		controller.abort()
	}
	controller = new AbortController()

	setStatus("loading")
	try {
		await axios.patch(
			route(props.autosaveRoute.name, props.autosaveRoute.parameters),
			{ layout: layout.value },
			{ signal: controller.signal }
		)
		setStatus("success")
		isDirty.value = true
	} catch (error: any) {
		if (axios.isCancel(error) || error.name === "CanceledError") {
			return
		}
		setStatus("error")
		notify({
			title: trans("Something went wrong."),
			text: error.response?.data?.message || error.message,
			type: "error",
		})
	}
}

const onFieldValueUpdate = (newFieldValue: any) => {
	set(layout.value, ["data", "fieldValue"], { ...newFieldValue })

	if (debounce) clearTimeout(debounce)
	debounce = setTimeout(autoSave, 600)
}

const onPublish = async (popover: { close: Function }) => {
	isPublishing.value = true
	try {
		await axios.post(route(props.publishRoute.name, props.publishRoute.parameters), {
			comment: comment.value,
			layout: layout.value,
		})
		isDirty.value = false
		comment.value = ""
		popover?.close?.()
		notify({ title: trans("Published"), type: "success" })
	} catch (error: any) {
		notify({
			title: trans("Something went wrong."),
			text: error.response?.data?.message || error.message,
			type: "error",
		})
	} finally {
		isPublishing.value = false
	}
}

const previewWidth = ref("w-full")
watch(currentView, (view) => {
	previewWidth.value =
		view === "mobile" ? "max-w-[420px]" : view === "tablet" ? "max-w-[820px]" : "w-full"
})
</script>

<template>
	<Head :title="title" />
	<PageHeading :data="pageHead">
		<template #button-publish>
			<Publish
				v-model="comment"
				:is_dirty="isDirty"
				:isLoading="isPublishing"
				@onPublish="onPublish" />
		</template>
	</PageHeading>

	<div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-[1fr_360px]">
		<div class="rounded-lg border border-gray-200 bg-gray-50">
			<div class="flex items-center justify-between border-b border-gray-200 px-4 py-2">
				<div class="text-xs text-gray-500">
					<span v-if="statusSave === 'loading'">{{ trans("Saving") }}…</span>
					<span v-else-if="statusSave === 'success'" class="text-green-600">
						{{ trans("Saved") }}
					</span>
					<span v-else-if="statusSave === 'error'" class="text-red-600">
						{{ trans("Not saved") }}
					</span>
					<a v-else :href="blogIndexUrl" target="_blank" class="hover:underline">
						{{ blogIndexUrl }}
					</a>
				</div>
				<ScreenView
					v-model="currentView"
					@screen-view="(e: any) => (currentView = e)" />
			</div>

			<div class="overflow-x-auto p-4">
				<div class="mx-auto bg-white" :class="previewWidth">
					<BlogListWorkshop
						:modelValue="previewFieldValue"
						:screenType="currentView"
						:indexBlock="0" />
				</div>
			</div>
		</div>

		<div class="space-y-4 rounded-lg border border-gray-200 p-4">
			<p class="text-sm text-gray-500">
				{{ trans("This layout is used by the blog index pages of this website.") }}
			</p>

			<SideEditor
				:blueprint="(blueprint as any)"
				:modelValue="fieldValue"
				:editable="true"
				@update:modelValue="onFieldValueUpdate" />
		</div>
	</div>
</template>
