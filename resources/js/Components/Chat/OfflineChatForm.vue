<script setup lang="ts">
import { inject, reactive, ref } from "vue"
import axios from "axios"
import { Textarea, InputText } from "primevue"
import Button from "../Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faMessage } from "@fortawesome/free-solid-svg-icons"
import { trans } from "laravel-vue-i18n"

const props = defineProps({
    isOnline: Boolean,
    hours: Object,
    session: {
        type: Object as () => any,
        default: null,
    },
    isLoggedIn: Boolean,
})

const emit = defineEmits(["session-created"])

const layout: any = inject("layout")
const baseUrl = layout?.appUrl ?? ""

const loading = ref(false)
const success = ref(false)

const form = reactive({
    name: "",
    email: "",
    message: "",
})

const error = ref<string | null>(null)

const submitOffline = async () => {
    loading.value = true
    success.value = false
    error.value = null
    try {
        const payload: any = {
            shop_id: layout?.iris?.shop?.id,
            session_ulid: props.session?.ulid,
            web_user_id: layout.user?.id ?? null,
            sender_id: layout.user?.id ?? null,
            sender_type: props.isLoggedIn ? "user" : "guest",
            language_id: 68,

            name: form.name,
            email: form.email,
            message: form.message,
        }
        const res = await axios.post(`${baseUrl}/app/api/chats/offline-message`, payload)

        success.value = true

        if (res.data?.data?.ulid) {
            emit("session-created", res.data.data)
        }

        form.name = ""
        form.email = ""
        form.message = ""
    } catch (e: any) {
        error.value = e?.response?.data?.message || "Failed to submit"
    } finally {
        loading.value = false
    }
}
</script>
<template>
    <div class="flex flex-col h-full bg-white">
        <!-- Info -->
        <div class="px-4 py-6 text-center border-b bg-gray-50">
            <p class="text-sm text-gray-600">
                {{ trans("Sorry, we aren’t online at the moment.") }}
            </p>
            <p class="text-sm text-gray-600">
                {{ trans("Our working hours are") }} <strong>{{ props.hours?.start }} - {{ props.hours?.end }}</strong>.
            </p>
            <p class="text-sm text-gray-500 mt-1">
                {{ trans("Leave a message and we’ll get back to you.") }}
            </p>
        </div>

        <!-- Form -->
        <div class="flex-1 flex flex-col p-4 gap-3">
            <InputText v-model="form.name" type="text" :placeholder="trans('Your name')" required />

            <InputText v-model="form.email" type="email" :placeholder="trans('Your email')" required />

            <Textarea v-model="form.message" :placeholder="trans('Your message')" rows="4" required />

            <div v-if="error" class="text-xs text-red-600">{{ error }}</div>
            <Button type="save" :label="loading ? 'Sending...' : 'Send message'"
                :disabled="loading || !form.name || !form.email || !form.message" class="justify-center"
                @click="submitOffline" />

            <span v-if="success" class="text-green-600 text-sm text-center gap-2 flex items-center">
                <FontAwesomeIcon :icon="faMessage" class="text-base" />
                <span class="text-sm">{{ trans("Your message has been sent. We'll contact you soon.") }}</span>
            </span>
        </div>
    </div>
</template>
