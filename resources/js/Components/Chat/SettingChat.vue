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
import { faEye, faEyeSlash, faCircleInfo, faChevronDown, faArrowUpRightFromSquare, faCircleCheck } from "@fortawesome/free-solid-svg-icons"
import { faJira } from "@fortawesome/free-brands-svg-icons"
import { playNotificationSoundFile, buildStorageUrl } from "@/Composables/useNotificationSound"
import { useChatLanguages } from "@/Composables/useLanguages"

const props = defineProps<{
    contact?: any
    initialTab?: "general" | "jira"
}>()

const emit = defineEmits(["close"])

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""
const soundUrl = buildStorageUrl("sound/notification.mp3", baseUrl)
const userId = layout?.user?.id
const isEditMode = ref(false)
const agent = ref<any>(null)

const activeTab = ref<"general" | "jira">(props.initialTab ?? "general")

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

onMounted(async () => {
    await fetchSpecializations()
    await fetchLanguages()
    await fetchAgentSetting()
    await fetchJiraSettings()
})
</script>

<template>
    <div class="flex flex-col gap-4 text-sm w-full">
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
    </div>
</template>
