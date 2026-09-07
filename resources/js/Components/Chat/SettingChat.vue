<script setup lang="ts">
import { ref, onMounted, inject, watch } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Dropdown from "primevue/dropdown"
import { notify } from "@kyvg/vue3-notification"
import InputSwitch from "primevue/inputswitch"
import InputNumber from "primevue/inputnumber"
import { InputText } from "primevue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faEye, faEyeSlash, faCircleInfo, faChevronDown, faArrowUpRightFromSquare, faCircleCheck, faXmark, faPlus, faHashtag, faUser } from "@fortawesome/free-solid-svg-icons"
import { faJira, faSlack } from "@fortawesome/free-brands-svg-icons"
import { playNotificationSoundFile, buildStorageUrl } from "@/Composables/useNotificationSound"
import { useChatLanguages } from "@/Composables/useLanguages"

const props = defineProps<{
    contact?: any
    initialTab?: "general" | "jira" | "slack"
    sessionUlid?: string | null
}>()

const emit = defineEmits(["close"])

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""
const soundUrl = buildStorageUrl("sound/notification.mp3", baseUrl)
const userId = layout?.user?.id
const isEditMode = ref(false)
const agent = ref<any>(null)

const activeTab = ref<"general" | "jira" | "slack">(props.initialTab ?? "general")

const form = ref({
    max_concurrent_chats: 100,
    is_available: true,
    specialization: [] as string[],
    language_id: null as number | null,
})

const { languages, fetchLanguages } = useChatLanguages(baseUrl)

const specializations = ref<
    { value: string; label: string }[]
>([])

const specializationValue = ref<string | null>(null)

const fetchSpecializations = async () => {
    try {
        const response = await axios.get(
            `${baseUrl}/app/api/chats/agents/specializations`
        )
        specializations.value = response.data.data
    } catch (e) {
        console.error("Failed to fetch specializations", e)
    }
}

const fetchAgentSetting = async () => {
    try {
        const { data } = await axios.get(
            `${baseUrl}/app/api/chats/agents/${userId}`
        )
        const agentData = data.data
        agent.value = agentData

        form.value = {
            max_concurrent_chats: agentData.max_concurrent_chats,
            is_available: agentData.is_available,
            specialization: agentData.specialization ?? [],
            language_id: agentData.language?.id ?? null,
        }

        specializationValue.value = form.value.specialization[0] ?? null
    } catch (e) {
        console.error("Failed to fetch agent setting", e)
    }
}

const saveSettings = async () => {
    try {
        if (form.value.max_concurrent_chats > 100) {
            notify({
                title: "Failed",
                text: "Max concurrent chats must not be greater than 100",
                type: "error"
            })
            return
        }

        await axios.put(
            `${baseUrl}/app/api/chats/agents/${userId}/update`,
            {
                max_concurrent_chats: form.value.max_concurrent_chats,
                is_available: form.value.is_available,
                specialization: form.value.specialization,
                language_id: form.value.language_id,
            }
        )
        isEditMode.value = false
        await fetchAgentSetting()
    } catch (e) {
        console.error("Failed to fetch agent setting", e)
    }
}

const notificationPermission = ref(Notification.permission)

const enableBrowserNotification = async () => {
    if (!("Notification" in window)) {
        notify({
            title: "Not Supported",
            text: "Browser does not support notifications",
            type: "error"
        })
        return
    }

    try {
        const permission = await Notification.requestPermission()
        notificationPermission.value = permission

        if (permission === "granted") {
            new Notification("Notification Enabled", {
                body: "You will now receive chat notifications."
            })

            playNotificationSoundFile(soundUrl)

            notify({
                title: "Success",
                text: "Browser notification enabled",
                type: "success"
            })
        } else {
            notify({
                title: "Permission Denied",
                text: "Please allow notifications in browser settings",
                type: "error"
            })
        }
    } catch (e) {
        console.error(e)
    }
}

const jiraForm = ref({
    base_url: "",
    email: "",
    api_token: "",
})
const jiraConfigured = ref(false)
const jiraHasToken = ref(false)
const showToken = ref(false)
const isSavingJira = ref(false)
const showTutorial = ref(false)

const currentOrganisation = String((route().params as Record<string, any>)?.organisation ?? "aw")

const fetchJiraSettings = async () => {
    try {
        const { data } = await axios.get(
            route("grp.org.chat.agents.jira.settings.show", [currentOrganisation]),
            { withCredentials: true }
        )
        const jira = data.data
        jiraConfigured.value = jira.configured
        jiraHasToken.value = jira.has_token
        jiraForm.value.base_url = jira.base_url ?? ""
        jiraForm.value.email = jira.email ?? ""
        jiraForm.value.api_token = ""
    } catch (e) {
        console.error("Failed to fetch Jira settings", e)
    }
}

const saveJiraSettings = async () => {
    if (!jiraForm.value.base_url.trim() || !jiraForm.value.email.trim()) {
        notify({ title: trans("Error"), text: trans("Base URL and email are required"), type: "error" })
        return
    }
    if (!jiraHasToken.value && !jiraForm.value.api_token.trim()) {
        notify({ title: trans("Error"), text: trans("Jira API token is required"), type: "error" })
        return
    }

    isSavingJira.value = true
    try {
        const { data } = await axios.put(
            route("grp.org.chat.agents.jira.settings.update", [currentOrganisation]),
            {
                base_url: jiraForm.value.base_url.trim(),
                email: jiraForm.value.email.trim(),
                api_token: jiraForm.value.api_token.trim() || undefined,
            },
            { withCredentials: true }
        )
        jiraConfigured.value = data.data.configured
        jiraHasToken.value = data.data.has_token
        jiraForm.value.api_token = ""
        showToken.value = false
        notify({ title: trans("Success"), text: trans("Jira settings saved"), type: "success" })
    } catch (e: any) {
        notify({
            title: trans("Error"),
            text: e?.response?.data?.message ?? trans("Failed to save Jira settings"),
            type: "error",
        })
    } finally {
        isSavingJira.value = false
    }
}

interface SlackDestination {
    type: "channel" | "user"
    id: string
    name: string
}

const slackToken = ref("")
const slackHasToken = ref(false)
const slackDestinations = ref<SlackDestination[]>([])
const showSlackToken = ref(false)
const isSavingSlack = ref(false)
const isLoadingSlack = ref(false)
const newChannelName = ref("")
const newUserId = ref("")
const newUserName = ref("")
const showGuide = ref(false)

const fetchSlackSettings = async () => {
    if (!props.sessionUlid) return

    isLoadingSlack.value = true
    try {
        const { data } = await axios.get(
            route("grp.org.chat.agents.sessions.slack.show", [currentOrganisation, props.sessionUlid]),
            { withCredentials: true }
        )
        slackHasToken.value = data?.data?.has_token ?? false
        slackDestinations.value = data?.data?.destinations ?? []
    } catch (e) {
        console.error("Failed to fetch Slack settings", e)
    } finally {
        isLoadingSlack.value = false
    }
}

const addChannel = () => {
    const name = newChannelName.value.trim()
    if (!name) return
    slackDestinations.value.push({ type: "channel", id: name, name })
    newChannelName.value = ""
}

const addUser = () => {
    const id = newUserId.value.trim()
    const name = newUserName.value.trim()
    if (!id || !name) {
        notify({ title: trans("Error"), text: trans("Slack User ID and name are both required"), type: "error" })
        return
    }
    slackDestinations.value.push({ type: "user", id, name })
    newUserId.value = ""
    newUserName.value = ""
}

const removeDestination = (index: number) => {
    slackDestinations.value.splice(index, 1)
}

const saveSlackSettings = async () => {
    if (!props.sessionUlid) return

    if (!slackHasToken.value && !slackToken.value.trim()) {
        notify({ title: trans("Error"), text: trans("Slack bot token is required"), type: "error" })
        return
    }

    isSavingSlack.value = true
    try {
        const { data } = await axios.put(
            route("grp.org.chat.agents.sessions.slack.update", [currentOrganisation, props.sessionUlid]),
            {
                token: slackToken.value.trim() || undefined,
                destinations: slackDestinations.value,
            },
            { withCredentials: true }
        )
        slackHasToken.value = data?.data?.has_token ?? slackHasToken.value
        slackDestinations.value = data?.data?.destinations ?? slackDestinations.value
        slackToken.value = ""
        showSlackToken.value = false
        notify({ title: trans("Success"), text: trans("Slack settings saved"), type: "success" })
    } catch (e: any) {
        notify({
            title: trans("Error"),
            text: e?.response?.data?.message ?? trans("Failed to save Slack settings"),
            type: "error",
        })
    } finally {
        isSavingSlack.value = false
    }
}

watch(specializationValue, (val) => {
    form.value.specialization = val ? [val] : []
})

watch(
    () => specializations.value,
    (opts) => {
        if (!opts.length) return

        form.value.specialization = [...form.value.specialization]
    },
    { immediate: true }
)

watch(
    () => props.initialTab,
    (tab) => {
        if (tab) activeTab.value = tab
    }
)

watch(
    () => props.sessionUlid,
    () => fetchSlackSettings()
)

onMounted(async () => {
    await fetchSpecializations()
    await fetchLanguages()
    await fetchAgentSetting()
    await fetchJiraSettings()
    await fetchSlackSettings()
})
</script>

<template>
    <div class="flex flex-col gap-4 text-sm w-full min-w-0 overflow-x-hidden">
        <div class="border-b pb-3">
            <div class="font-semibold text-gray-800">
                {{ agent?.user?.name }}
            </div>
            <div class="text-xs text-gray-500">
                {{ trans("Agent Settings") }}
            </div>
        </div>

        <div class="flex gap-1 border-b">
            <button
                class="px-3 py-2 text-sm font-medium -mb-px border-b-2 transition-colors"
                :class="activeTab === 'general' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                @click="activeTab = 'general'"
            >
                {{ trans("General") }}
            </button>
            <button
                class="px-3 py-2 text-sm font-medium -mb-px border-b-2 transition-colors flex items-center gap-1.5"
                :class="activeTab === 'jira' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                @click="activeTab = 'jira'"
            >
                <FontAwesomeIcon :icon="faJira" />
                {{ trans("Jira Setting") }}
                <FontAwesomeIcon v-if="jiraConfigured" :icon="faCircleCheck" class="text-emerald-500 text-xs" />
            </button>
            <button
                class="px-3 py-2 text-sm font-medium -mb-px border-b-2 transition-colors flex items-center gap-1.5"
                :class="activeTab === 'slack' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                @click="activeTab = 'slack'"
            >
                <FontAwesomeIcon :icon="faSlack" />
                {{ trans("Slack Setting") }}
                <FontAwesomeIcon v-if="slackHasToken && slackDestinations.length" :icon="faCircleCheck" class="text-emerald-500 text-xs" />
            </button>
        </div>

        <div v-show="activeTab === 'general'" class="flex flex-col gap-5">
            <div class="flex flex-col gap-1">
                <label class="text-xs text-gray-500">{{ trans("Max Concurrent Chats") }}</label>
                <InputNumber v-model="form.max_concurrent_chats" :disabled="!isEditMode" :min="1" class="w-full" />
            </div>

            <div class="flex items-center justify-between">
                <span class="text-gray-700">{{ trans("Available for chats") }}</span>
                <InputSwitch v-model="form.is_available" :disabled="!isEditMode" />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs text-gray-500">{{ trans("Specialization") }}</label>
                <Dropdown v-model="specializationValue" :options="specializations" optionLabel="label" optionValue="value"
                    placeholder="Select specialization" :disabled="!isEditMode" class="w-full" />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs text-gray-500">{{ trans("Default Language") }}</label>
                <Dropdown v-model="form.language_id" :options="languages" optionLabel="name" optionValue="id"
                    placeholder="Select language" :disabled="!isEditMode" class="w-full" />
            </div>

            <div class="flex flex-col gap-2 pt-3 border-t">
                <div class="flex items-center justify-between">
                    <span class="text-gray-700">{{ trans("Browser Notifications") }}</span>
                    <Button label="Enable" type="primary" @click="enableBrowserNotification"
                        v-if="notificationPermission !== 'granted'" />
                    <span v-else class="text-green-600 text-xs font-medium">{{ trans("Enabled") }}</span>
                </div>
                <div class="text-xs text-gray-400">
                    {{ trans("Allow browser notification and sound when new chat message arrives.") }}
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t">
                <Button v-if="!isEditMode" type="edit" label="Edit" @click="isEditMode = true" />
                <template v-else>
                    <Button label="Cancel" type="cancel" @click="isEditMode = false" />
                    <Button label="Save" type="save" @click="saveSettings" />
                </template>
            </div>
        </div>

        <div v-show="activeTab === 'jira'" class="flex flex-col gap-4">
            <p class="text-xs text-gray-500">
                {{ trans("Use your own Jira account so that tickets you create are reported under your name.") }}
            </p>

            <div class="flex flex-col gap-1">
                <label class="text-xs text-gray-500">{{ trans("Jira Base URL") }}</label>
                <InputText v-model="jiraForm.base_url" placeholder="https://your-domain.atlassian.net/" class="w-full" />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs text-gray-500">{{ trans("Jira Email") }}</label>
                <InputText v-model="jiraForm.email" type="email" placeholder="you@example.com" class="w-full" />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs text-gray-500">{{ trans("Jira API Token") }}</label>
                <div class="relative">
                    <InputText
                        v-model="jiraForm.api_token"
                        :type="showToken ? 'text' : 'password'"
                        :placeholder="jiraHasToken ? '••••••••••••••••' : trans('Paste your API token')"
                        class="w-full pr-9"
                    />
                    <button
                        type="button"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        @click="showToken = !showToken"
                    >
                        <FontAwesomeIcon :icon="showToken ? faEyeSlash : faEye" />
                    </button>
                </div>
                <span v-if="jiraHasToken" class="text-[11px] text-gray-400">
                    {{ trans("Leave blank to keep your current token.") }}
                </span>
            </div>

            <div class="flex justify-end">
                <Button :label="trans('Save Jira Settings')" :loading="isSavingJira" type="save" @click="saveJiraSettings" />
            </div>

            <div class="rounded-lg border border-gray-200 overflow-hidden">
                <button
                    type="button"
                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 text-gray-700"
                    @click="showTutorial = !showTutorial"
                >
                    <span class="flex items-center gap-2 text-sm font-medium">
                        <FontAwesomeIcon :icon="faCircleInfo" class="text-blue-500" />
                        {{ trans("How to get a Jira API token") }}
                    </span>
                    <FontAwesomeIcon :icon="faChevronDown" class="text-xs transition-transform" :class="showTutorial ? 'rotate-180' : ''" />
                </button>

                <ol v-show="showTutorial" class="px-4 py-3 text-xs text-gray-600 space-y-2 list-decimal list-inside">
                    <li>{{ trans("Log in to your Jira account.") }}</li>
                    <li>
                        {{ trans("Open your profile and choose Account settings") }}
                        <a href="https://id.atlassian.com/manage-profile/profile-and-visibility" target="_blank" rel="noopener"
                            class="text-blue-600 hover:underline inline-flex items-center gap-1">
                            {{ trans("Profile & visibility") }}
                            <FontAwesomeIcon :icon="faArrowUpRightFromSquare" class="text-[9px]" />
                        </a>
                    </li>
                    <li>
                        {{ trans("Open the Security tab") }}
                        <a href="https://id.atlassian.com/manage-profile/security" target="_blank" rel="noopener"
                            class="text-blue-600 hover:underline inline-flex items-center gap-1">
                            {{ trans("Security") }}
                            <FontAwesomeIcon :icon="faArrowUpRightFromSquare" class="text-[9px]" />
                        </a>
                    </li>
                    <li>
                        {{ trans("Choose Create and manage API tokens") }}
                        <a href="https://id.atlassian.com/manage-profile/security/api-tokens" target="_blank" rel="noopener"
                            class="text-blue-600 hover:underline inline-flex items-center gap-1">
                            {{ trans("API tokens") }}
                            <FontAwesomeIcon :icon="faArrowUpRightFromSquare" class="text-[9px]" />
                        </a>
                        {{ trans("— a verification code may be emailed to you, enter it to continue.") }}
                    </li>
                    <li>{{ trans("Click Create API token, give it a name (e.g. APITokenJira), set an expiry (max 1 year), then create.") }}</li>
                    <li>{{ trans("Copy the generated token and paste it above, together with your Jira Base URL (e.g. https://inikoo.atlassian.net/) and the email registered on Jira.") }}</li>
                    <li>{{ trans("Done — you can now create Jira tickets from Aiku chat.") }}</li>
                </ol>
            </div>
        </div>

        <div v-show="activeTab === 'slack'" class="flex flex-col gap-4">
            <div v-if="!sessionUlid" class="flex flex-col items-center gap-2 py-8 text-center">
                <div class="w-10 h-10 rounded-full bg-amber-50 flex items-center justify-center">
                    <FontAwesomeIcon :icon="faCircleInfo" class="text-amber-500" />
                </div>
                <p class="text-xs text-gray-500 max-w-[220px]">
                    {{ trans("Open an active chat first to manage this shop's Slack settings.") }}
                </p>
            </div>

            <template v-else>
                <div class="flex items-start gap-2.5 rounded-lg bg-purple-50/70 border border-purple-100 px-3 py-2.5">
                    <FontAwesomeIcon :icon="faSlack" class="text-purple-600 mt-0.5 shrink-0" />
                    <p class="text-xs text-purple-900/80 leading-relaxed">
                        {{ trans("Shared with every agent of this shop. Add the channels and people you want to be able to forward chats to.") }}
                    </p>
                </div>

                <div v-if="isLoadingSlack" class="flex items-center justify-center gap-2 py-8 text-xs text-gray-400">
                    <FontAwesomeIcon :icon="faChevronDown" class="animate-bounce" />
                    {{ trans("Loading…") }}
                </div>

                <template v-else>
                    <!-- Bot token -->
                    <div class="flex flex-col gap-1.5 rounded-xl border border-gray-200 p-3.5">
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-700">
                            {{ trans("Slack Bot Token") }}
                            <FontAwesomeIcon v-if="slackHasToken" :icon="faCircleCheck" class="text-emerald-500 text-[11px]" />
                        </label>
                        <div class="relative">
                            <InputText
                                v-model="slackToken"
                                :type="showSlackToken ? 'text' : 'password'"
                                :placeholder="slackHasToken ? '••••••••••••••••' : trans('Paste your bot token (starts with xoxb-)')"
                                class="w-full pr-9 font-mono text-xs"
                            />
                            <button
                                type="button"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                @click="showSlackToken = !showSlackToken"
                            >
                                <FontAwesomeIcon :icon="showSlackToken ? faEyeSlash : faEye" />
                            </button>
                        </div>
                        <span v-if="slackHasToken" class="text-[11px] text-gray-400">
                            {{ trans("Leave blank to keep the current token.") }}
                        </span>
                    </div>

                    <!-- Destination list -->
                    <div class="flex flex-col gap-2 rounded-xl border border-gray-200 p-3.5">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-semibold text-gray-700">{{ trans("Channels & People") }}</label>
                            <span class="text-[11px] text-gray-400">{{ slackDestinations.length }}</span>
                        </div>

                        <div class="flex flex-col gap-1.5 max-h-40 overflow-y-auto">
                            <TransitionGroup name="dest-list">
                                <div
                                    v-for="(destination, index) in slackDestinations"
                                    :key="`${destination.type}-${destination.id}-${index}`"
                                    class="group flex items-center gap-2.5 px-2.5 py-2 rounded-lg border border-gray-100 bg-gray-50/60 hover:bg-gray-50 transition-colors"
                                >
                                    <span
                                        class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 text-[10px]"
                                        :class="destination.type === 'channel'
                                            ? 'bg-indigo-100 text-indigo-600'
                                            : 'bg-purple-100 text-purple-600'"
                                    >
                                        <FontAwesomeIcon :icon="destination.type === 'channel' ? faHashtag : faUser" />
                                    </span>
                                    <div class="flex flex-col min-w-0 flex-1">
                                        <span class="text-sm text-gray-700 truncate leading-tight">{{ destination.name }}</span>
                                        <span class="text-[10px] text-gray-400 truncate leading-tight">{{ destination.id }}</span>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-gray-300 group-hover:text-gray-400 hover:!text-red-500 transition-colors shrink-0"
                                        @click="removeDestination(index)"
                                    >
                                        <FontAwesomeIcon :icon="faXmark" />
                                    </button>
                                </div>
                            </TransitionGroup>

                            <div v-if="!slackDestinations.length" class="flex flex-col items-center gap-1.5 py-5 text-center">
                                <FontAwesomeIcon :icon="faSlack" class="text-gray-300 text-lg" />
                                <p class="text-xs text-gray-400">{{ trans("No channels or people added yet.") }}</p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 pt-2 mt-1 border-t border-gray-100">
                            <div class="flex items-end gap-2">
                                <div class="flex flex-col gap-1 flex-1 min-w-0">
                                    <label class="flex items-center gap-1 text-[11px] font-medium text-gray-500">
                                        <FontAwesomeIcon :icon="faHashtag" class="text-indigo-400 text-[10px]" />
                                        {{ trans("Channel name") }}
                                    </label>
                                    <InputText v-model="newChannelName" placeholder="general" class="w-full min-w-0 text-sm" @keydown.enter.prevent="addChannel" />
                                </div>
                                <Button type="secondary" :icon="faPlus" class="shrink-0" @click="addChannel" />
                            </div>

                            <div class="flex items-end gap-2">
                                <div class="flex flex-col gap-1 flex-1 min-w-0">
                                    <label class="flex items-center gap-1 text-[11px] font-medium text-gray-500">
                                        <FontAwesomeIcon :icon="faUser" class="text-purple-400 text-[10px]" />
                                        {{ trans("Slack User ID") }}
                                    </label>
                                    <InputText v-model="newUserId" placeholder="U0123ABCD" class="w-full min-w-0 text-sm" />
                                </div>
                                <div class="flex flex-col gap-1 flex-1 min-w-0">
                                    <label class="text-[11px] font-medium text-gray-500">{{ trans("Name") }}</label>
                                    <InputText v-model="newUserName" placeholder="Jane Doe" class="w-full min-w-0 text-sm" @keydown.enter.prevent="addUser" />
                                </div>
                                <Button type="secondary" :icon="faPlus" class="shrink-0" @click="addUser" />
                            </div>
                        </div>
                    </div>

                    <div class="flex">
                        <Button
                            :label="trans('Save Slack Settings')"
                            :icon="faSlack"
                            :loading="isSavingSlack"
                            type="save"
                            full
                            @click="saveSlackSettings"
                        />
                    </div>

                    <div class="rounded-xl border border-gray-200 overflow-hidden">
                        <button
                            type="button"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 bg-gray-50 text-gray-700 hover:bg-gray-100 transition-colors"
                            @click="showGuide = !showGuide"
                        >
                            <span class="flex items-center gap-2 text-sm font-medium">
                                <FontAwesomeIcon :icon="faCircleInfo" class="text-blue-500" />
                                {{ trans("How to get a Bot Token & Slack User ID") }}
                            </span>
                            <FontAwesomeIcon :icon="faChevronDown" class="text-xs transition-transform" :class="showGuide ? 'rotate-180' : ''" />
                        </button>

                        <ol v-show="showGuide" class="px-4 py-3 text-xs text-gray-600 space-y-2.5 list-none">
                            <li v-for="(step, i) in [
                                trans('Go to api.slack.com/apps → Create New App → From scratch.'),
                                trans('Under OAuth & Permissions, add Bot Token Scopes: chat:write and chat:write.public.'),
                                trans('Click Install to Workspace and approve the permissions.'),
                                trans('Copy the Bot User OAuth Token (starts with xoxb-) and paste it above.'),
                                trans('For a channel, just type its name. For a person, open their Slack profile → More → Copy member ID to get their Slack User ID.'),
                                trans('In Slack, type /invite @YourBotName in each target channel.'),
                            ]" :key="i" class="flex gap-2">
                                <span class="shrink-0 w-4 h-4 mt-0.5 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-[10px]">{{ i + 1 }}</span>
                                <span>{{ step }}</span>
                            </li>
                        </ol>
                    </div>
                </template>
            </template>
        </div>
    </div>
</template>

<style scoped>
.dest-list-enter-active,
.dest-list-leave-active {
    transition: all 0.15s ease;
}
.dest-list-enter-from,
.dest-list-leave-to {
    opacity: 0;
    transform: translateX(-4px);
}
</style>
