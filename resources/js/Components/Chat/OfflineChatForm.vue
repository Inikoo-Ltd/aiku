<script setup lang="ts">
import { inject, reactive, ref } from "vue"
import axios from "axios"
import { Textarea, InputText } from "primevue"
import Button from "../Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faMessage } from "@fortawesome/free-solid-svg-icons"

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

const errors = reactive({
    name: "",
    email: "",
    message: "",
})

const isValidEmail = (email: string) => {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)
}

const validateForm = () => {
    let ok = true

    errors.name = ""
    errors.email = ""
    errors.message = ""

    if (!form.name.trim()) {
        errors.name = "Name is required"
        ok = false
    }

    if (!form.email.trim()) {
        errors.email = "Email is required"
        ok = false
    } else if (!isValidEmail(form.email)) {
        errors.email = "Enter a valid email address"
        ok = false
    }

    if (!form.message.trim()) {
        errors.message = "Message cannot be empty"
        ok = false
    }

    return ok
}


const submitOffline = async () => {
    if (!validateForm()) return
    loading.value = true
    success.value = false

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
    } catch (e) {
        console.error("Offline message failed", e)
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
                Sorry, we aren’t online at the moment.
            </p>
            <p class="text-sm text-gray-600">
                Our working hours are <strong>{{ props.hours?.start }} - {{ props.hours?.end }}</strong>.
            </p>
            <p class="text-sm text-gray-500 mt-1">
                Leave a message and we’ll get back to you.
            </p>
        </div>

        <!-- Form -->
        <div class="flex-1 flex flex-col p-4 gap-3">
            <InputText v-model="form.name" type="text" placeholder="Your name" required />
            <small v-if="errors.name" class="text-red-500 text-xs">{{ errors.name }}</small>
            <InputText v-model="form.email" type="email" placeholder="Your email" required />
            <small v-if="errors.email" class="text-red-500 text-xs">{{ errors.email }}</small>
            <Textarea v-model="form.message" placeholder="Your message" rows="4" required />
            <small v-if="errors.message" class="text-red-500 text-xs">{{ errors.message }}</small>
            <Button type="save" :label="loading ? 'Sending...' : 'Send message'"
                :disabled="loading || !form.name || !form.email || !form.message" class="justify-center"
                @click="submitOffline" />

            <span v-if="success" class="text-green-600 text-sm text-center gap-2 flex items-center">
                <FontAwesomeIcon :icon="faMessage" class="text-base" />
                <span class="text-sm">Your message has been sent. We’ll contact you
                    soon.</span>
            </span>
        </div>
    </div>
</template>
