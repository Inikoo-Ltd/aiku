<script setup lang="ts">
import { computed, inject, reactive, ref } from "vue"
import axios from "axios"
import { Textarea, InputText } from "primevue"
import Button from "../Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faMessage, faPaperPlane, faPaperclip, faXmark, faWandMagicSparkles } from "@fortawesome/free-solid-svg-icons"
import { ctrans } from "@/Composables/useTrans"
import { useShoppingAssistant } from "@/Iris/Composables/useShoppingAssistant"

const props = defineProps({
    isOnline: Boolean,
    hours: Object,
    offlineInfo: Object,
    session: {
        type: Object as () => any,
        default: null,
    },
    isLoggedIn: Boolean,
    /* Supplied when the widget runs outside Iris, where there is no website layout to read. */
    shopId: {
        type: Number,
        default: null,
    },
})

const emit = defineEmits(["session-created", "ask-merlin"])

const layout: any = inject("layout")
const baseUrl = layout?.appUrl ?? ""

const { assistant } = useShoppingAssistant()
const assistantName = computed(() => assistant.value?.name || "Merlin")

const loading = ref(false)
const success = ref(false)

const form = reactive({
    name: "",
    email: "",
    message: "",
})

const error = ref<string | null>(null)

const MAX_ATTACHMENTS = 10
const MAX_FILE_BYTES = 10 * 1024 * 1024
const fileInput = ref<HTMLInputElement>()
const attachments = ref<File[]>([])

const addAttachments = (e: Event) => {
    error.value = null
    for (const file of Array.from((e.target as HTMLInputElement).files ?? [])) {
        if (attachments.value.length >= MAX_ATTACHMENTS) {
            error.value = ctrans("Maximum :count attachments", { count: MAX_ATTACHMENTS })
            break
        }
        if (file.size > MAX_FILE_BYTES) {
            error.value = ctrans("Maximum file size 10MB")
            continue
        }
        attachments.value.push(file)
    }
    if (fileInput.value) fileInput.value.value = ""
}

const removeAttachment = (index: number) => attachments.value.splice(index, 1)
const hasWorkingHours = computed(() => Boolean(props.hours?.start && props.hours?.end))
const nextOpening = computed(() => props.offlineInfo?.next_opening ?? null)
const showNextOpening = computed(() =>
    props.offlineInfo?.reason === "non_working_day"
    && Boolean(nextOpening.value?.day_name)
    && Boolean(nextOpening.value?.start)
    && Boolean(nextOpening.value?.end)
)

const submitOffline = async () => {
    loading.value = true
    success.value = false
    error.value = null
    try {
        const payload: Record<string, any> = {
            shop_id: props.shopId ?? layout?.iris?.shop?.id,
            session_ulid: props.session?.ulid,
            web_user_id: layout.user?.id,
            sender_type: props.isLoggedIn ? "user" : "guest",
            language_id: 68,
            message: form.message,
            page_url: window.location.href,
        }

        if (!props.isLoggedIn) {
            payload.name = form.name
            payload.email = form.email
        }

        const formData = new FormData()
        Object.entries(payload).forEach(([key, value]) => {
            if (value !== undefined && value !== null) formData.append(key, value)
        })
        attachments.value.forEach((file) => formData.append("attachments[]", file))

        const res = await axios.post(`${baseUrl}/app/api/chats/offline-message`, formData)

        success.value = true

        if (res.data?.data?.ulid) {
            emit("session-created", res.data.data)
        }

        form.name = ""
        form.email = ""
        form.message = ""
        attachments.value = []
    } catch (e: any) {
        error.value = e?.response?.data?.message || "Failed to submit"
    } finally {
        loading.value = false
    }
}
</script>
<template>
    <div class="flex flex-col h-full min-h-0 bg-white">

        <div v-if="assistant" class="px-4 py-5 text-center border-b shrink-0">
            <p class="text-base font-semibold text-gray-800">
                {{ ctrans("We are closed now, but :name never sleeps.", { name: assistantName }) }}
            </p>
            <p class="text-sm text-gray-600 mt-1 mb-3">
                {{ ctrans(":name, our AI assistant, can help you right now with products, delivery, how things work and more.", { name: assistantName }) }}
            </p>
            <Button type="primary" full :label="ctrans('Ask :name now', { name: assistantName })" :icon="faWandMagicSparkles"
                class="justify-center" @click="emit('ask-merlin')" />
        </div>

        <!-- Info -->
        <div class="px-4 py-6 text-center border-b bg-gray-50 shrink-0">
            <p v-if="!assistant" class="text-sm text-gray-600">
                {{ ctrans("Sorry, we aren't online at the moment.") }}
            </p>
            <p v-if="showNextOpening" class="text-sm text-gray-600">
                {{ ctrans("Today is not a working day.") }}
                {{ ctrans("You can chat with us again on") }}
                <strong>{{ nextOpening?.day_name }}</strong>
                {{ ctrans("at") }}
                <strong>{{ nextOpening?.start }} - {{ nextOpening?.end }}</strong>.
            </p>
            <p v-else-if="hasWorkingHours" class="text-sm text-gray-600">
                {{ ctrans("Our working hours are") }}
                <strong>{{ props.hours?.start }} - {{ props.hours?.end }}</strong>.
            </p>
            <p v-else class="text-sm text-gray-600">
                {{ ctrans("Our team is currently offline.") }}
            </p>
            <p class="text-sm text-gray-500 mt-1">
                {{ ctrans("Leave a message and we'll get back to you") }}
            </p>
        </div>

        <!-- Form -->
        <div class="flex-1 min-h-0 overflow-y-auto p-4 flex flex-col gap-3">

            <template v-if="!isLoggedIn">
                <InputText v-model="form.name" type="text" class="text-base" :placeholder="ctrans('Your name')"
                    required />

                <InputText v-model="form.email" type="email" class="text-base" :placeholder="ctrans('Your email')"
                    required />
            </template>

            <Textarea v-model="form.message" class="text-base" :placeholder="ctrans('Your message')" rows="4"
                required />

            <div v-if="attachments.length" class="flex flex-col gap-1">
                <div v-for="(file, index) in attachments" :key="index"
                    class="flex items-center justify-between gap-2 rounded border px-2 py-1 text-xs text-gray-600">
                    <span class="truncate">{{ file.name }}</span>
                    <button type="button" class="text-gray-400 hover:text-red-500 shrink-0"
                        :aria-label="ctrans('Remove')" @click="removeAttachment(index)">
                        <FontAwesomeIcon :icon="faXmark" fixed-width />
                    </button>
                </div>
            </div>

            <input ref="fileInput" type="file" multiple class="hidden"
                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.pptx,.zip,.mp4,.mov"
                @change="addAttachments" />
            <Button type="tertiary" :label="ctrans('Attach photos or files')" :icon="faPaperclip"
                :disabled="loading || attachments.length >= MAX_ATTACHMENTS" class="justify-center"
                @click="fileInput?.click()" />

            <div v-if="error" class="text-xs text-red-600">
                {{ error }}
            </div>

            <!-- <div class="flex-1"></div> -->

            <Button type="save" :label="loading
                ? ctrans('Sending')
                : ctrans('Send offline message')" :icon="faPaperPlane"
                :disabled="loading || !form.message || (!isLoggedIn && (!form.name || !form.email))" class="justify-center"
                @click="submitOffline" />

            <span v-if="success" class="text-green-600 text-sm text-center gap-2 flex items-center">
                <FontAwesomeIcon :icon="faMessage" class="text-base" fixed-width />
                <span>
                    {{ ctrans("Your message has been sent. We'll contact you soon.") }}
                </span>
            </span>

        </div>
    </div>
</template>
