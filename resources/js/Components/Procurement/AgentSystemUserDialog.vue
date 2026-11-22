<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Password from "primevue/password"
import Select from "primevue/select"
import ToggleSwitch from "primevue/toggleswitch"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes } from "@fal"
import { ctrans } from "@/Composables/useTrans"

type Management = {
	store_route: { name: string; parameters: number[] }
	update_route: { name: string; parameters: number[] }
	positions: { value: string; label: string }[]
}

type AgentUser = {
	id: number
	username: string
	contact_name: string | null
	email: string | null
	status: { icon: string }
}

const props = defineProps<{
	management: Management
}>()

const visible = ref(false)
const editing = ref<AgentUser | null>(null)
const isSaving = ref(false)
const errors = ref<Record<string, string>>({})
const form = ref({ username: "", contact_name: "", email: "", password: "", position: "agt-c", status: true })
const original = ref({ ...form.value })

const isDirty = (field: keyof typeof form.value) => !!editing.value && form.value[field] !== original.value[field]
const dirtyClass = (field: keyof typeof form.value) => (isDirty(field) ? "!bg-[var(--app-accent-soft)] !border-[var(--app-accent-muted)]" : "")
const hasChanges = computed(() => (["username", "contact_name", "email", "password", "status"] as const).some((field) => isDirty(field)))

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

const header = computed(() => (editing.value ? ctrans("Edit :username", { username: editing.value.username }) : ctrans("New agent user")))

function openCreate() {
	editing.value = null
	errors.value = {}
	form.value = { username: "", contact_name: "", email: "", password: "", position: "agt-c", status: true }
	visible.value = true
}

function openEdit(user: AgentUser) {
	editing.value = user
	errors.value = {}
	form.value = { username: user.username, contact_name: user.contact_name ?? "", email: user.email ?? "", password: "", position: "", status: user.status?.icon?.includes("check") ?? true }
	original.value = { ...form.value }
	visible.value = true
}

function submit() {
	const options = {
		preserveScroll: true,
		onStart: () => {
			isSaving.value = true
			errors.value = {}
		},
		onFinish: () => (isSaving.value = false),
		onSuccess: () => (visible.value = false),
		onError: (formErrors: Record<string, string>) => (errors.value = formErrors),
	}

	if (editing.value) {
		const payload: Record<string, unknown> = { username: form.value.username, contact_name: form.value.contact_name, email: form.value.email || null, status: form.value.status }
		if (form.value.password) {
			payload.password = form.value.password
		}
		router.patch(route(props.management.update_route.name, [...props.management.update_route.parameters, editing.value.id]), payload, options)
		return
	}

	router.post(
		route(props.management.store_route.name, props.management.store_route.parameters),
		{ username: form.value.username, contact_name: form.value.contact_name, email: form.value.email || null, password: form.value.password, position: form.value.position },
		options
	)
}

defineExpose({ openCreate, openEdit })
</script>

<template>
	<Dialog v-model:visible="visible" modal :header="header" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
		<div class="space-y-3 text-sm">
			<label class="block space-y-1">
				<span class="text-gray-700">{{ ctrans("Username") }} <span class="text-red-500">*</span></span>
				<InputText v-model="form.username" :class="[fieldFocusClass, dirtyClass('username')]" fluid autocomplete="off" />
				<span v-if="errors.username" class="block text-xs text-red-500">{{ errors.username }}</span>
			</label>

			<label class="block space-y-1">
				<span class="text-gray-700">
					{{ editing ? ctrans("New password") : ctrans("Password") }}
					<span v-if="!editing" class="text-red-500">*</span>
					<span v-else class="text-gray-400">({{ ctrans("leave empty to keep it") }})</span>
				</span>
				<Password v-model="form.password" :feedback="false" toggleMask :inputClass="[fieldFocusClass, dirtyClass('password')]" fluid :inputProps="{ autocomplete: 'new-password' }" />
				<span v-if="errors.password" class="block text-xs text-red-500">{{ errors.password }}</span>
			</label>

			<label class="block space-y-1">
				<span class="text-gray-700">{{ ctrans("Name") }} <span class="text-red-500">*</span></span>
				<InputText v-model="form.contact_name" :class="[fieldFocusClass, dirtyClass('contact_name')]" fluid />
				<span v-if="errors.contact_name" class="block text-xs text-red-500">{{ errors.contact_name }}</span>
			</label>

			<label class="block space-y-1">
				<span class="text-gray-700">{{ ctrans("Email") }} <span class="text-gray-400">({{ ctrans("optional") }})</span></span>
				<InputText v-model="form.email" type="email" :class="[fieldFocusClass, dirtyClass('email')]" fluid />
				<span v-if="errors.email || errors.work_email" class="block text-xs text-red-500">{{ errors.email || errors.work_email }}</span>
			</label>

			<label v-if="!editing" class="block space-y-1">
				<span class="text-gray-700">{{ ctrans("Position") }} <span class="text-red-500">*</span></span>
				<Select v-model="form.position" :options="management.positions" optionLabel="label" optionValue="value" :class="fieldFocusClass" fluid />
				<span v-if="errors.position" class="block text-xs text-red-500">{{ errors.position }}</span>
			</label>

			<div v-if="editing" class="flex items-center gap-2">
				<ToggleSwitch
					v-model="form.status"
					inputId="agent-user-status"
					:pt="{ slider: { class: form.status ? '!bg-green-500 !border-green-500' : '!bg-red-500 !border-red-500' }, handle: { class: '!bg-white flex items-center justify-center' } }"
				>
					<template #handle="{ checked }">
						<FontAwesomeIcon :icon="checked ? faCheck : faTimes" :class="checked ? 'text-green-600' : 'text-red-500'" class="text-[10px]" fixed-width aria-hidden="true" />
					</template>
				</ToggleSwitch>
				<label for="agent-user-status" class="rounded px-1 text-gray-700" :class="isDirty('status') ? 'bg-[var(--app-accent-soft)] ring-1 ring-[var(--app-accent-muted)]' : ''">{{ form.status ? ctrans("Active, can log in") : ctrans("Suspended, cannot log in") }}</label>
			</div>
			<span v-if="errors.status || errors.reason" class="block text-xs text-red-500">{{ errors.status || errors.reason }}</span>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="primary" full :label="editing ? ctrans('Save') : ctrans('Create user')" :loading="isSaving" :disabled="!!editing && !hasChanges" @click="submit">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
