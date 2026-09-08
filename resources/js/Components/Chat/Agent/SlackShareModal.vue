<script setup lang="ts">
import { ref, computed, watch, inject } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSlack } from "@fortawesome/free-brands-svg-icons"
import { faSpinner, faCircleInfo } from "@fortawesome/free-solid-svg-icons"
import { notify } from "@kyvg/vue3-notification"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

interface SlackDestination {
    type: "channel" | "user"
    id: string
    name: string
}

const props = defineProps<{
    isOpen: boolean
    organisation: string
    mode: "session" | "message"
    sessionUlid?: string | null
    messageId?: number | null
}>()

const emit = defineEmits(["close", "shared", "open-settings"])

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""

const isLoading = ref(false)
const isSending = ref(false)
const hasToken = ref(false)
const destinations = ref<SlackDestination[]>([])
const selectedKeys = ref<Set<string>>(new Set())

const destinationKey = (d: SlackDestination) => `${d.type}:${d.id}`

const allSelected = computed(() =>
    destinations.value.length > 0 && selectedKeys.value.size === destinations.value.length
)

const toggleAll = () => {
    if (allSelected.value) {
        selectedKeys.value = new Set()
    } else {
        selectedKeys.value = new Set(destinations.value.map(destinationKey))
    }
}

const toggleOne = (d: SlackDestination) => {
    const key = destinationKey(d)
    const next = new Set(selectedKeys.value)
    if (next.has(key)) {
        next.delete(key)
    } else {
        next.add(key)
    }
    selectedKeys.value = next
}

const isConfigured = computed(() => hasToken.value && destinations.value.length > 0)

const fetchDestinations = async () => {
    isLoading.value = true
    try {
        const url = props.mode === "session"
            ? route("grp.org.chat.agents.sessions.slack.show", [props.organisation, props.sessionUlid])
            : route("grp.org.chat.agents.messages.slack_settings", [props.organisation, props.messageId])

        const { data } = await axios.get(url)
        hasToken.value = data?.data?.has_token ?? false
        destinations.value = data?.data?.destinations ?? []
        selectedKeys.value = new Set(destinations.value.map(destinationKey))
    } catch (e) {
        console.error("Failed to fetch Slack destinations", e)
    } finally {
        isLoading.value = false
    }
}

const close = () => {
    emit("close")
}

const openSettings = () => {
    emit("open-settings")
    close()
}

const confirmShare = async () => {
    if (selectedKeys.value.size === 0 || isSending.value) return

    isSending.value = true
    try {
        const destinationKeys = Array.from(selectedKeys.value)

        const url = props.mode === "session"
            ? `${baseUrl}/app/api/chats/sessions/${props.sessionUlid}/share-to-slack`
            : route("grp.org.chat.agents.messages.forward_slack", [props.organisation, props.messageId])

        const { data } = await axios.post(url, { destination_keys: destinationKeys })

        notify({
            title: data?.success ? trans("Shared to Slack") : trans("Slack"),
            text: data?.message ?? trans("Done"),
            type: data?.success ? "success" : "error",
        })

        if (data?.success) {
            emit("shared", data)
            close()
        }
    } catch (e: any) {
        notify({
            title: trans("Error"),
            text: e?.response?.data?.message ?? trans("Failed to share to Slack"),
            type: "error",
        })
    } finally {
        isSending.value = false
    }
}

watch(
    () => props.isOpen,
    (open) => {
        if (open) fetchDestinations()
    }
)
</script>

<template>
    <Modal :isOpen="isOpen" @onClose="close" width="w-full max-w-md">
        <div class="p-5 flex flex-col gap-4">
            <div class="flex items-center gap-2">
                <FontAwesomeIcon :icon="faSlack" class="text-purple-600" />
                <h3 class="text-base font-semibold text-gray-800">
                    {{ mode === "session" ? trans("Share session to Slack") : trans("Forward message to Slack") }}
                </h3>
            </div>

            <div v-if="isLoading" class="flex items-center justify-center py-8 text-gray-400">
                <FontAwesomeIcon :icon="faSpinner" class="animate-spin text-lg" />
            </div>

            <div v-else-if="!isConfigured" class="flex flex-col items-center gap-3 py-6 text-center">
                <FontAwesomeIcon :icon="faCircleInfo" class="text-2xl text-amber-500" />
                <p class="text-sm text-gray-600">
                    {{ trans("Slack is not configured yet. Add a bot token and at least one channel or person first.") }}
                </p>
                <Button :label="trans('Open Slack Settings')" type="primary" @click="openSettings" />
            </div>

            <template v-else>
                <button type="button" class="text-xs text-blue-600 hover:underline self-start" @click="toggleAll">
                    {{ allSelected ? trans("Deselect all") : trans("Select all") }}
                </button>

                <div class="flex flex-col gap-1.5 max-h-64 overflow-y-auto border rounded-lg p-2">
                    <label
                        v-for="d in destinations"
                        :key="destinationKey(d)"
                        class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer"
                    >
                        <input
                            type="checkbox"
                            :checked="selectedKeys.has(destinationKey(d))"
                            @change="toggleOne(d)"
                        />
                        <span class="text-xs text-gray-400 w-14 shrink-0 uppercase">
                            {{ d.type === "channel" ? trans("Channel") : trans("Person") }}
                        </span>
                        <span class="text-sm text-gray-700 truncate">{{ d.name }}</span>
                    </label>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t">
                    <Button :label="trans('Cancel')" type="cancel" @click="close" />
                    <Button
                        :label="isSending ? trans('Sending…') : trans('Send')"
                        type="primary"
                        :disabled="selectedKeys.size === 0 || isSending"
                        @click="confirmShare"
                    />
                </div>
            </template>
        </div>
    </Modal>
</template>
