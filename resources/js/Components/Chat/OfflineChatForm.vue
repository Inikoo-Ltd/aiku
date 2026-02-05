<script setup lang="ts">
import { inject, reactive, ref } from "vue"
import axios from "axios"
import { Textarea, InputText } from "primevue"
import Button from "../Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faMessage } from "@fortawesome/free-solid-svg-icons"

const layout: any = inject("layout")
const baseUrl = layout.appUrl

const hours = "08:00 – 17:00"

const loading = ref(false)
const success = ref(false)

const form = reactive({
    name: "",
    email: "",
    message: "",
})

const submitOffline = async () => {
    loading.value = true
    success.value = false

    try {
        await axios.post(`${baseUrl}/app/api/chats/offline-message`, {
            shop_id: layout.iris.shop.id,
            ...form,
        })

        success.value = true
        form.name = ""
        form.email = ""
        form.message = ""
    } catch (e) {
        console.error(e)
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
                Our working hours are <strong>{{ hours }}</strong>.
            </p>
            <p class="text-sm text-gray-500 mt-1">
                Leave a message and we’ll get back to you.
            </p>
        </div>

        <!-- Form -->
        <div class="flex-1 flex flex-col p-4 gap-3">
            <InputText v-model="form.name" type="text" placeholder="Your name" required />

            <InputText v-model="form.email" type="email" placeholder="Your email" required />

            <Textarea v-model="form.message" placeholder="Your message" rows="4" required />

            <Button type="save" :label="loading ? 'Sending...' : 'Send message'" :disabled="loading"
                @click="submitOffline" />

            <span v-if="success" class="text-green-600 text-sm text-center gap-2 flex items-center">
                <FontAwesomeIcon :icon="faMessage" class="text-base" />
                <span class="text-sm">Your message has been sent. We’ll contact you
                    soon.</span>
            </span>
        </div>
    </div>
</template>
