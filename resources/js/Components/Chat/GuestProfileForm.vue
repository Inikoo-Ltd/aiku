<script setup lang="ts">
import { ref, inject } from "vue"
import axios from "axios"
import Button from "../Elements/Buttons/Button.vue"

const props = defineProps<{ sessionUlid: string }>()
const emit = defineEmits<{
	(e: "submitted", payload: { name: string; email: string; phone: string }): void
}>()
const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""

const name = ref("")
const email = ref("")
const phone = ref("")
const loading = ref(false)
const error = ref<string | null>(null)

const submit = async () => {
	if (!props.sessionUlid || loading.value) return
	error.value = null
	loading.value = true
	try {
		await axios.post(`${baseUrl}/app/api/chats/sessions/${props.sessionUlid}/guest-profile`, {
			name: name.value,
			email: email.value,
			phone: phone.value,
		})
		const raw = localStorage.getItem("chat")
		let data: any = {}
		if (raw) {
			try {
				data = JSON.parse(raw)
			} catch {}
		}
		data.guest_profile_submitted = true
		data.contact_name = name.value
		data.saved_at = new Date().toISOString()
		localStorage.setItem("chat", JSON.stringify(data))
		emit("submitted", { name: name.value, email: email.value, phone: phone.value })
	} catch (e: any) {
		error.value = e?.response?.data?.message || "Failed to submit profile"
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<div class="p-4 space-y-3">
		<div class="text-sm text-gray-700 font-medium">Please fill your details to start chat</div>
		<div class="space-y-2">
			<input
				v-model="name"
				type="text"
				placeholder="Name"
				class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-200 focus:border-blue-500 outline-none" />

			<input
				v-model="email"
				type="email"
				placeholder="Email"
				class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-200 focus:border-blue-500 outline-none" />

			<input
				v-model="phone"
				type="tel"
				placeholder="Phone"
				class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-200 focus:border-blue-500 outline-none" />
		</div>
		<div class="flex flex-col items-center justify-between gap-2">
			<div v-if="error" class="text-xs text-red-600">{{ error }}</div>
			<Button
				class="px-4 w-full items-center justify-center"
				:loading="loading"
				@click="submit"
				>Submit</Button
			>
		</div>
	</div>
</template>
