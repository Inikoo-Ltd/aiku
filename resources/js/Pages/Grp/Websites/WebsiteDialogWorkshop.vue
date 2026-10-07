<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { computed, onBeforeUnmount, provide, reactive, ref, watch } from "vue"
import axios from "axios"
import { debounce, pick } from "lodash-es"
import { notify } from "@kyvg/vue3-notification"
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from "@headlessui/vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle } from "@far"
import { faChevronLeft, faChevronRight, faCircle, faCog, faExternalLink, faHandPointer, faImage, faLayerGroup, faRectangleWide, faText, faThLarge, faUndoAlt, faCheck, faExclamationTriangle } from "@fal"
import { faTimes } from "@fas"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Publish from "@/Components/Publish.vue"
import ScreenView from "@/Components/ScreenView.vue"
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue"
import WebsiteDialogTemplateList from "@/Components/Websites/WebsiteDialog/WebsiteDialogTemplateList.vue"
import WebsiteDialogSettings from "@/Components/Websites/WebsiteDialog/WebsiteDialogSettings.vue"
import { blueprint } from "@/Components/Websites/WebsiteDialog/Templates/Blueprint"
import { getWebsiteDialogComponent } from "@/Composables/useWebsiteDialog"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import type { PageHeadingTypes } from "@/types/PageHeading"
import type { routeType } from "@/types/route"
import type { WebsiteDialogData } from "@/types/WebsiteDialog"

library.add(faCheckCircle, faChevronLeft, faChevronRight, faCircle, faCog, faExternalLink, faHandPointer, faImage, faLayerGroup, faRectangleWide, faText, faThLarge, faUndoAlt, faCheck, faExclamationTriangle, faTimes)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    website_dialog: WebsiteDialogData
    website: {
        name: string
        url: string
    }
    display_frequencies: { label: string, value: string }[]
    triggers: { label: string, value: string }[]
    routes_list: {
        update_route: routeType
        publish_route: routeType
        reset_route: routeType
        toggle_route: routeType
        upload_image_route: routeType
        fetch_clashing_dialogs_route: routeType
    }
}>()

const dialogData = reactive<WebsiteDialogData>(JSON.parse(JSON.stringify(props.website_dialog)))
provide("websiteDialogData", dialogData)

const schedule = reactive({
    schedule_at: (props.website_dialog.schedule_at ?? null) as Date | string | null,
    schedule_finish_at: (props.website_dialog.schedule_finish_at ?? null) as Date | string | null,
})
provide("websiteDialogSchedule", schedule)

const openFieldWorkshop = ref<string | number | null>(null)
provide("openFieldWorkshop", openFieldWorkshop)

const currentView = ref<"mobile" | "tablet" | "desktop">("desktop")
provide("screenType", currentView)

const tabs = [
    { key: "template", label: ctrans("Template"), icon: "fal fa-th-large" },
    { key: "content", label: ctrans("Content"), icon: "fal fa-layer-group" },
    { key: "settings", label: ctrans("Settings"), icon: "fal fa-cog" },
]
const selectedTab = ref(dialogData.component ? 1 : 0)
const isSidebarCollapsed = ref(false)

watch(openFieldWorkshop, (fieldKey) => {
    if (fieldKey !== null) {
        selectedTab.value = 1
        isSidebarCollapsed.value = false
    }
})

const applyServerState = (fresh: WebsiteDialogData) => {
    Object.assign(dialogData, pick(fresh, [
        "state", "status", "status_icon", "is_dirty", "is_published", "schedule_at", "schedule_finish_at",
        "live_at", "closed_at", "ready_at", "paused_until", "paused_by", "published_message", "publisher",
    ]))
}

const routeUrl = (routeData: routeType) => route(routeData.name, routeData.parameters)

const notifyRequestError = (error: any, fallback: string) => {
    const errors = error?.response?.data?.errors
    notify({
        title: ctrans("Something went wrong"),
        text: errors ? Object.values(errors).flat().join(" ") : (error?.response?.data?.message || fallback),
        type: "error"
    })
}

// Section: autosave of the draft
const isLoadingSave = ref(false)
let saveController: AbortController | null = null

const draftPayload = () => pick(dialogData, ["template_code", "component", "fields", "container_properties", "settings"])
let lastSavedDraft = JSON.stringify(draftPayload())

const onSave = async () => {
    if (!dialogData.component) {
        return
    }

    saveController?.abort()
    saveController = new AbortController()
    isLoadingSave.value = true

    const payload = draftPayload()

    try {
        const response = await axios.patch(routeUrl(props.routes_list.update_route), payload, { signal: saveController.signal })
        lastSavedDraft = JSON.stringify(payload)
        applyServerState(response.data.data)
    } catch (error: any) {
        if (!axios.isCancel(error)) {
            notifyRequestError(error, ctrans("Failed to save the dialog"))
        }
    } finally {
        isLoadingSave.value = false
    }
}

const debouncedSave = debounce(onSave, 1000)

watch(
    () => JSON.stringify(draftPayload()),
    (draft) => draft === lastSavedDraft ? debouncedSave.cancel() : debouncedSave()
)

onBeforeUnmount(() => {
    debouncedSave.flush()
})

// Section: publish, checking first whether another dialog is live at the same time
const publishComment = ref("")
const isLoadingPublish = ref(false)
const clashingDialogs = ref<{ ulid: string, name: string }[]>([])
const isClashModalOpen = ref(false)

const sendPublish = async (supersede: boolean) => {
    isLoadingPublish.value = true

    try {
        const response = await axios.patch(routeUrl(props.routes_list.publish_route), {
            schedule_at: schedule.schedule_at || null,
            schedule_finish_at: schedule.schedule_finish_at || null,
            published_message: publishComment.value || null,
            supersede,
        })
        applyServerState(response.data.data)
        publishComment.value = ""
        isClashModalOpen.value = false
        notify({ title: ctrans("Success"), text: ctrans("The dialog is published"), type: "success" })
    } catch (error: any) {
        notifyRequestError(error, ctrans("Failed to publish the dialog"))
    } finally {
        isLoadingPublish.value = false
    }
}

const onPublish = async (popover?: { close: Function }) => {
    debouncedSave.cancel()
    isLoadingPublish.value = true

    try {
        await onSave()

        const response = await axios.get(routeUrl(props.routes_list.fetch_clashing_dialogs_route), {
            params: {
                schedule_at: schedule.schedule_at || undefined,
                schedule_finish_at: schedule.schedule_finish_at || undefined,
            }
        })
        clashingDialogs.value = response.data.data || []
    } catch (error: any) {
        notifyRequestError(error, ctrans("Failed to publish the dialog"))
        isLoadingPublish.value = false
        return
    }

    popover?.close?.()

    if (clashingDialogs.value.length) {
        isLoadingPublish.value = false
        isClashModalOpen.value = true
        return
    }

    await sendPublish(false)
}

const dialogShowRoute = (ulid: string) => route("grp.org.shops.show.web.website_dialogs.show", {
    ...route().params,
    websiteDialog: ulid,
})

// Section: reset draft to the published version
const isLoadingReset = ref(false)
const onReset = async () => {
    debouncedSave.cancel()
    isLoadingReset.value = true

    try {
        const response = await axios.patch(routeUrl(props.routes_list.reset_route))
        const fresh = response.data.data as WebsiteDialogData
        Object.assign(dialogData, pick(fresh, ["template_code", "component", "fields", "container_properties", "settings"]))
        lastSavedDraft = JSON.stringify(draftPayload())
        applyServerState(fresh)
    } catch (error: any) {
        notifyRequestError(error, ctrans("Failed to reset the dialog"))
    } finally {
        isLoadingReset.value = false
    }
}

// Section: turn off (turning on goes through publish)
const isLoadingToggle = ref(false)
const onClickInactive = async () => {
    if (dialogData.status === "inactive") {
        return
    }

    isLoadingToggle.value = true
    try {
        const response = await axios.patch(routeUrl(props.routes_list.toggle_route))
        applyServerState(response.data.data)
        notify({ title: ctrans("Gotcha!"), text: ctrans("The dialog is turned off"), type: "success" })
    } catch (error: any) {
        notifyRequestError(error, ctrans("Failed to update the status"))
    } finally {
        isLoadingToggle.value = false
    }
}

const dialogComponent = computed(() => getWebsiteDialogComponent(dialogData.component))
const hasUnpublishedChanges = computed(() => dialogData.is_dirty || !dialogData.is_published)

const previewFrameClass = computed(() => ({
    mobile: "w-[375px]",
    tablet: "w-[768px]",
    desktop: "w-full",
}[currentView.value]))

const openWebsite = () => window.open(props.website.url, "_blank", "noopener")

const openTemplateTab = () => {
    selectedTab.value = 0
    isSidebarCollapsed.value = false
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle v-if="isLoadingSave">
            <LoadingIcon v-tooltip="ctrans('Saving..')" />
        </template>

        <template #other>
            <div class="flex flex-wrap items-center justify-end gap-x-2 gap-y-1.5">
                <Button
                    :label="ctrans('Reset')"
                    v-tooltip="dialogData.ready_at ? ctrans('Go back to the last publish') + ` (${useFormatTime(dialogData.ready_at, { formatTime: 'hm' })})` : ctrans('Not published yet')"
                    :loading="isLoadingReset"
                    :style="'negative'"
                    :disabled="!dialogData.is_dirty || !dialogData.is_published"
                    icon="fal fa-undo-alt"
                    @click="onReset"
                />

                <div class="grid grid-cols-2 select-none overflow-hidden rounded text-sm ring-1 ring-gray-300">
                    <button
                        type="button"
                        class="flex items-center justify-center gap-x-1 px-3 py-1.5 transition-all"
                        :class="dialogData.status === 'inactive' ? 'bg-red-600 text-white' : 'bg-white text-red-400 hover:bg-red-50'"
                        @click="onClickInactive"
                    >
                        {{ ctrans("Inactive") }}
                        <LoadingIcon v-if="isLoadingToggle" size="sm" />
                        <FontAwesomeIcon v-else :icon="dialogData.status === 'inactive' ? 'far fa-check-circle' : 'fal fa-circle'" size="sm" fixed-width aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        v-tooltip="dialogData.status === 'active' ? '' : ctrans('Publish to turn it on')"
                        class="flex items-center justify-center gap-x-1 px-3 py-1.5 transition-all"
                        :class="dialogData.status === 'active' ? 'bg-green-600 text-white' : 'bg-white text-slate-400 hover:bg-green-50'"
                        @click="selectedTab = 2"
                    >
                        {{ ctrans("Active") }}
                        <FontAwesomeIcon :icon="dialogData.status === 'active' ? 'far fa-check-circle' : 'fal fa-circle'" size="sm" fixed-width aria-hidden="true" />
                    </button>
                </div>

                <button type="button" class="px-2" v-tooltip="ctrans('Go to website')" :aria-label="ctrans('Go to website')" @click="openWebsite">
                    <FontAwesomeIcon icon="fal fa-external-link" size="xl" fixed-width aria-hidden="true" />
                </button>

                <Publish
                    v-model="publishComment"
                    :isLoading="isLoadingPublish"
                    :is_dirty="hasUnpublishedChanges"
                    @onPublish="(popover) => onPublish(popover)"
                >
                    <template #form-extend>
                        <p class="mb-2 text-xs text-slate-500">
                            {{ schedule.schedule_at ? ctrans('Starts :date', { date: useFormatTime(schedule.schedule_at, { formatTime: 'hm' }) }) : ctrans('Starts now') }}
                            ·
                            {{ schedule.schedule_finish_at ? ctrans('ends :date', { date: useFormatTime(schedule.schedule_finish_at, { formatTime: 'hm' }) }) : ctrans('no end date') }}
                        </p>
                    </template>
                </Publish>
            </div>
        </template>
    </PageHeading>

    <div class="flex h-[calc(100vh-16vh)] bg-slate-100">
        <div class="relative z-[20] hidden border-r border-slate-200 bg-white shadow-sm lg:flex lg:flex-col">
            <div v-show="!isSidebarCollapsed" class="flex min-h-0 flex-1 flex-col">
                <TabGroup :selectedIndex="selectedTab" @change="(index: number) => selectedTab = index">
                    <TabList class="flex shrink-0 border-b border-slate-200 bg-slate-50">
                        <Tab
                            v-for="(tab, index) in tabs"
                            :key="tab.key"
                            class="relative flex flex-1 items-center justify-center gap-1.5 px-2 py-2 text-xs font-medium transition-colors focus:outline-none"
                            :class="selectedTab === index ? 'bg-white text-[var(--theme-color-0)]' : 'text-slate-500 hover:bg-white/60 hover:text-slate-700'"
                        >
                            <FontAwesomeIcon :icon="tab.icon" class="text-xs" fixed-width aria-hidden="true" />
                            {{ tab.label }}
                            <span v-if="selectedTab === index" class="absolute inset-x-0 bottom-0 h-0.5 bg-[var(--theme-color-0)]" aria-hidden="true" />
                        </Tab>
                    </TabList>

                    <TabPanels class="min-h-0 flex-1">
                        <TabPanel class="flex h-full w-[340px] flex-col p-1.5">
                            <WebsiteDialogTemplateList @afterSubmit="selectedTab = 1" />
                        </TabPanel>

                        <TabPanel class="flex h-full w-[340px] flex-col p-1.5">
                            <div v-if="dialogData.component" class="min-h-0 flex-1 overflow-y-auto">
                                <SideEditor
                                    :modelValue="dialogData"
                                    @update:modelValue="(value: Partial<WebsiteDialogData>) => value !== dialogData && Object.assign(dialogData, value)"
                                    :blueprint="blueprint"
                                    :panelOpen="openFieldWorkshop"
                                    :uploadImageRoute="routes_list.upload_image_route"
                                />
                            </div>
                            <div v-else class="flex flex-1 flex-col items-center justify-center gap-y-2 p-4 text-center text-xs text-slate-500">
                                {{ ctrans("Pick a template first") }}
                                <Button :style="'tertiary'" size="xs" :label="ctrans('Choose template')" @click="selectedTab = 0" />
                            </div>
                        </TabPanel>

                        <TabPanel class="flex h-full w-[340px] flex-col p-1.5">
                            <div class="min-h-0 flex-1 overflow-y-auto">
                                <WebsiteDialogSettings :displayFrequencies="display_frequencies" :triggers="triggers" />
                            </div>
                        </TabPanel>
                    </TabPanels>
                </TabGroup>
            </div>

            <button
                v-show="isSidebarCollapsed"
                type="button"
                v-tooltip.right="ctrans('Show editor panel')"
                :aria-label="ctrans('Show editor panel')"
                class="flex w-8 flex-1 flex-col items-center justify-center text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-700"
                @click="isSidebarCollapsed = false"
            >
                <FontAwesomeIcon icon="fal fa-layer-group" fixed-width aria-hidden="true" />
            </button>

            <button
                type="button"
                v-tooltip.right="isSidebarCollapsed ? ctrans('Show editor panel') : ctrans('Hide editor panel')"
                :aria-label="isSidebarCollapsed ? ctrans('Show editor panel') : ctrans('Hide editor panel')"
                class="absolute right-[-12px] top-1/2 z-10 flex h-7 w-6 -translate-y-1/2 items-center justify-center rounded-r-md border border-l-0 border-slate-200 bg-white text-slate-500 shadow-md transition-all hover:text-slate-900"
                @click="isSidebarCollapsed = !isSidebarCollapsed"
            >
                <FontAwesomeIcon :icon="isSidebarCollapsed ? 'fal fa-chevron-right' : 'fal fa-chevron-left'" class="text-xs" fixed-width aria-hidden="true" />
            </button>
        </div>

        <div class="flex w-full min-w-0 flex-col">
            <div class="flex shrink-0 flex-wrap items-center justify-between gap-1.5 border-b border-slate-200 bg-white px-2 py-1 text-xs">
                <div class="flex items-center gap-1.5">
                    <div class="flex items-center overflow-hidden rounded border border-slate-200" v-tooltip.bottom="ctrans('Preview device size')">
                        <ScreenView v-model="currentView" />
                    </div>
                    <span v-if="dialogData.template_code" class="text-slate-500">
                        {{ ctrans("Template") }}: <span class="font-medium text-slate-700">{{ dialogData.template_code }}</span>
                    </span>
                </div>

                <div class="flex items-center gap-1.5">
                    <span v-if="isLoadingSave" class="flex items-center gap-1 text-slate-400">
                        <LoadingIcon /> {{ ctrans("Saving..") }}
                    </span>
                    <span
                        v-else-if="hasUnpublishedChanges && dialogData.component"
                        class="flex items-center gap-1 rounded border border-amber-300 bg-amber-50 px-2 py-0.5 font-medium text-amber-800"
                    >
                        <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
                        {{ dialogData.is_published ? ctrans("Unpublished changes") : ctrans("Not published yet") }}
                    </span>
                    <span v-else-if="dialogData.component" class="flex items-center gap-1 text-green-600">
                        <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
                        {{ ctrans("Published") }}
                    </span>
                </div>
            </div>

            <div class="relative min-h-0 flex-1 overflow-auto p-4">
                <div class="mx-auto h-full min-h-[480px] transition-all duration-300" :class="previewFrameClass">
                    <div class="relative h-full overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                        <div class="space-y-3 p-6" aria-hidden="true">
                            <div class="h-8 w-1/3 rounded bg-slate-200" />
                            <div class="h-40 rounded bg-slate-100" />
                            <div class="grid grid-cols-3 gap-3">
                                <div class="h-24 rounded bg-slate-100" />
                                <div class="h-24 rounded bg-slate-100" />
                                <div class="h-24 rounded bg-slate-100" />
                            </div>
                        </div>

                        <div class="absolute inset-0 flex items-center justify-center overflow-auto bg-black/50 p-4">
                            <div v-if="dialogComponent" class="relative w-fit max-w-full">
                                <component :is="dialogComponent" :key="dialogData.component" :dialogData="dialogData" isEditable />
                                <span class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-gray-600 shadow" aria-hidden="true">
                                    <FontAwesomeIcon icon="fas fa-times" fixed-width />
                                </span>
                            </div>

                            <div v-else class="rounded-lg bg-white px-8 py-10 text-center shadow-lg">
                                <div class="text-base font-semibold text-slate-800">{{ ctrans("No template selected") }}</div>
                                <p class="mt-1 text-sm text-slate-500">{{ ctrans("Pick a template in the left panel to start") }}</p>
                                <Button class="mt-4" :style="'tertiary'" :label="ctrans('Choose template')" @click="openTemplateTab" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <Modal :isOpen="isClashModalOpen" width="w-full max-w-lg" @onClose="isClashModalOpen = false">
        <div class="space-y-3">
            <div class="flex items-center gap-x-2 text-lg font-semibold text-amber-700">
                <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
                {{ ctrans("Another dialog is live at that time") }}
            </div>
            <ul class="list-inside list-disc text-sm">
                <li v-for="clash in clashingDialogs" :key="clash.ulid">
                    <Link :href="dialogShowRoute(clash.ulid)" class="underline">{{ clash.name }}</Link>
                </li>
            </ul>
            <p class="text-sm text-slate-600">
                {{ schedule.schedule_finish_at
                    ? ctrans("It will be paused and come back by itself on :date.", { date: useFormatTime(schedule.schedule_finish_at, { formatTime: 'hm' }) })
                    : ctrans("Your dialog has no finish date, so you will have to turn the other one back on yourself.") }}
            </p>
            <div class="flex justify-end gap-x-2 pt-2">
                <Button :style="'tertiary'" :label="ctrans('Cancel')" @click="isClashModalOpen = false" />
                <Button :label="ctrans('Pause it and publish mine')" icon="far fa-rocket-launch" :loading="isLoadingPublish" @click="sendPublish(true)" />
            </div>
        </div>
    </Modal>
</template>

<style lang="scss" scoped>
:deep(.website-dialog-editable) {
    @apply cursor-pointer border border-dashed border-transparent hover:border-[var(--theme-color-0)];
}
</style>
