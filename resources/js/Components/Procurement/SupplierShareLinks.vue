<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import Dialog from "primevue/dialog"
import Checkbox from "primevue/checkbox"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faShareAlt, faCopy, faBan, faCheck } from "@fal"

library.add(faShareAlt, faCopy, faBan, faCheck)

interface RouteTarget {
	name: string
	parameters: Record<string, number>
}

export interface Share {
	can_create: boolean
	email: string | null
	months: number[]
	store_route: RouteTarget
	login_url: string
	contacts: {
		store_route: RouteTarget
		rows: {
			id: number
			email: string
			name: string | null
			phone: string | null
			authorised_by: string | null
			revoked_at: string | null
			last_link_sent_at: string | null
			number_links_sent: number
			revoke_route: RouteTarget
		}[]
	}
	links: {
		id: number
		live: boolean
		created_at: string
		created_by: string | null
		expires_at: string
		revoked_at: string | null
		email: string | null
		requested: boolean
		number_views: number
		last_viewed_at: string | null
		views: { ip: string | null; at: string }[]
		revoke_route: RouteTarget
	}[]
}

const props = defineProps<{
	share: Share
}>()

const isOpen = ref(false)
const months = ref(6)
const askCode = ref(!!props.share.email)
const email = ref(props.share.email ?? "")
const isSaving = ref(false)
const createdUrl = ref<string | null>(null)

const monthOptions = computed(() => props.share.months.map((count) => ({ label: count === 1 ? ctrans("1 month") : ctrans(":count months", { count: String(count) }), value: count })))
const liveCount = computed(() => props.share.links.filter((link) => link.live).length)

const create = async () => {
	isSaving.value = true
	try {
		const response = await axios.post(route(props.share.store_route.name, props.share.store_route.parameters), {
			months: months.value,
			email: askCode.value ? email.value : null,
		})
		createdUrl.value = response.data.url
		router.reload({ only: ["share"] })
	} catch (error: any) {
		notify({ title: ctrans("Something went wrong"), text: error.response?.data?.message, type: "error" })
	} finally {
		isSaving.value = false
	}
}

const copy = async () => {
	await navigator.clipboard.writeText(createdUrl.value ?? "")
	notify({ title: ctrans("Link copied"), type: "success" })
}

const revoke = (link: Share["links"][number]) => {
	if (!confirm(ctrans("Stop this link now? The supplier will see nothing with it."))) {
		return
	}
	router.post(route(link.revoke_route.name, link.revoke_route.parameters), {}, { preserveScroll: true, only: ["share"] })
}

const contactEmail = ref(props.share.email ?? "")
const contactName = ref("")
const contactPhone = ref("")
const isAddingContact = ref(false)
const liveContacts = computed(() => props.share.contacts.rows.filter((contact) => !contact.revoked_at).length)

const addContact = () => {
	isAddingContact.value = true
	router.post(
		route(props.share.contacts.store_route.name, props.share.contacts.store_route.parameters),
		{ email: contactEmail.value, name: contactName.value || null, phone: contactPhone.value || null },
		{
			preserveScroll: true,
			only: ["share"],
			onSuccess: () => {
				contactEmail.value = ""
				contactName.value = ""
				contactPhone.value = ""
			},
			onError: (errors) => notify({ title: ctrans("Something went wrong"), text: Object.values(errors).join(" "), type: "error" }),
			onFinish: () => (isAddingContact.value = false),
		}
	)
}

const revokeContact = (contact: Share["contacts"]["rows"][number]) => {
	if (!confirm(ctrans("Stop :email from signing in? The links it was sent stop working now.", { email: contact.email }))) {
		return
	}
	router.post(route(contact.revoke_route.name, contact.revoke_route.parameters), {}, { preserveScroll: true, only: ["share"] })
}

const copyLogin = async () => {
	await navigator.clipboard.writeText(props.share.login_url)
	notify({ title: ctrans("Link copied"), type: "success" })
}

const open = () => {
	createdUrl.value = null
	isOpen.value = true
}
</script>

<template>
	<div>
		<Button type="tertiary" size="xs" :label="liveCount || liveContacts ? ctrans('Shared with supplier (:count)', { count: String(liveCount + liveContacts) }) : ctrans('Share with supplier')" @click="open">
			<template #icon><FontAwesomeIcon icon="fal fa-share-alt" fixed-width aria-hidden="true" /></template>
		</Button>

		<Dialog v-model:visible="isOpen" modal dismissableMask :header="ctrans('Share with supplier')" :style="{ width: '44rem', maxWidth: 'calc(100vw - 2rem)' }">
			<div class="space-y-4 text-sm text-gray-700">
				<p class="text-xs text-gray-500">
					{{ ctrans("A private link shows the supplier, without an account, how its products sell at its own prices in its currency, its ranking in its country, the notes shared with it and its purchase orders. Nothing else. Anyone with the link sees that page, so ask for a code sent to the supplier's email when in doubt.") }}
				</p>

				<div class="space-y-2 rounded border border-gray-200 p-3">
					<div class="flex flex-wrap items-center justify-between gap-2">
						<div class="text-xs font-semibold text-gray-600">{{ ctrans("Contacts who can sign in") }}</div>
						<Button type="tertiary" size="xxs" :label="ctrans('Copy sign in page')" @click="copyLogin">
							<template #icon><FontAwesomeIcon icon="fal fa-copy" fixed-width aria-hidden="true" /></template>
						</Button>
					</div>
					<p class="text-xs text-gray-500">
						{{ ctrans("These emails can get a link by themselves on :url: they type their email and we email them a link for 7 days. Add only an email you have checked belongs to the supplier.", { url: share.login_url }) }}
					</p>
					<form v-if="share.can_create" class="flex flex-wrap items-center gap-2" @submit.prevent="addContact">
						<InputText v-model="contactEmail" type="email" class="min-w-0 flex-1 text-sm" :placeholder="ctrans('Email')" :aria-label="ctrans('Email')" />
						<InputText v-model="contactName" class="min-w-0 flex-1 text-sm" :placeholder="ctrans('Name (optional)')" :aria-label="ctrans('Name')" />
						<InputText v-model="contactPhone" class="min-w-0 flex-1 text-sm" :placeholder="ctrans('Phone (optional)')" :aria-label="ctrans('Phone')" />
						<Button type="tertiary" size="xs" :label="ctrans('Allow')" :loading="isAddingContact" :disabled="!contactEmail" @click="addContact">
							<template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
						</Button>
					</form>
					<table v-if="share.contacts.rows.length" class="w-full text-xs [&_td]:pr-3 [&_th]:pr-3">
						<thead class="text-gray-400">
							<tr>
								<th class="whitespace-nowrap py-1 text-left font-normal">{{ ctrans("Email") }}</th>
								<th class="whitespace-nowrap py-1 text-left font-normal">{{ ctrans("Allowed by") }}</th>
								<th class="whitespace-nowrap py-1 text-left font-normal">{{ ctrans("Last link sent") }}</th>
								<th class="whitespace-nowrap py-1 text-right font-normal">{{ ctrans("Links") }}</th>
								<th />
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100">
							<tr v-for="contact in share.contacts.rows" :key="contact.id" :class="contact.revoked_at ? 'text-gray-400' : 'text-gray-700'">
								<td class="py-1.5">
									{{ contact.email }}
									<span v-if="contact.name" class="text-gray-400"> · {{ contact.name }}</span>
									<span v-if="contact.phone" class="text-gray-400"> · {{ contact.phone }}</span>
									<span v-if="contact.revoked_at" class="text-red-600"> · {{ ctrans("Stopped") }}</span>
								</td>
								<td class="py-1.5">{{ contact.authorised_by ?? "—" }}</td>
								<td class="py-1.5">{{ contact.last_link_sent_at ? useFormatTime(contact.last_link_sent_at) : "—" }}</td>
								<td class="py-1.5 text-right tabular-nums">{{ contact.number_links_sent }}</td>
								<td class="py-1.5 text-right">
									<Button v-if="!contact.revoked_at && share.can_create" type="tertiary" size="xxs" :label="ctrans('Stop')" @click="revokeContact(contact)">
										<template #icon><FontAwesomeIcon icon="fal fa-ban" class="text-red-500" fixed-width aria-hidden="true" /></template>
									</Button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div v-if="createdUrl" class="space-y-2 rounded border border-green-200 bg-green-50 p-3">
					<p class="text-xs font-semibold text-green-800">{{ ctrans("Send this link to the supplier. It is shown only now.") }}</p>
					<div class="flex items-center gap-2">
						<InputText :model-value="createdUrl" readonly class="w-full font-mono text-xs" @focus="($event.target as HTMLInputElement).select()" />
						<Button type="tertiary" size="xs" :label="ctrans('Copy')" @click="copy">
							<template #icon><FontAwesomeIcon icon="fal fa-copy" fixed-width aria-hidden="true" /></template>
						</Button>
					</div>
				</div>

				<div v-else-if="share.can_create" class="space-y-3">
					<div class="flex flex-wrap items-center gap-3">
						<span class="text-xs font-semibold text-gray-600">{{ ctrans("Works for") }}</span>
						<SegmentedToggle v-model="months" :options="monthOptions" :aria-label="ctrans('Works for')" />
					</div>
					<label class="flex cursor-pointer items-center gap-2">
						<Checkbox v-model="askCode" binary size="small" />
						{{ ctrans("Ask for a code sent to this email before showing anything") }}
					</label>
					<InputText v-if="askCode" v-model="email" type="email" class="w-full text-sm" :placeholder="ctrans('Supplier email')" />
					<div class="flex justify-end">
						<Button type="tertiary" size="xs" :label="ctrans('Create link')" :loading="isSaving" :disabled="askCode && !email" @click="create" />
					</div>
				</div>
				<p v-else class="text-xs text-gray-500">{{ ctrans("Only supply chain editors can create links.") }}</p>

				<div v-if="share.links.length">
					<div class="mb-1 text-xs font-semibold text-gray-600">{{ ctrans("Links given") }}</div>
					<table class="w-full text-xs [&_td]:pr-3 [&_th]:pr-3">
						<thead class="text-gray-400">
							<tr>
								<th class="whitespace-nowrap py-1 text-left font-normal">{{ ctrans("Created") }}</th>
								<th class="whitespace-nowrap py-1 text-left font-normal">{{ ctrans("Status") }}</th>
								<th class="whitespace-nowrap py-1 text-left font-normal">{{ ctrans("Sent to") }}</th>
								<th class="whitespace-nowrap py-1 text-right font-normal">{{ ctrans("Views") }}</th>
								<th class="whitespace-nowrap py-1 text-left font-normal">{{ ctrans("Last opened") }}</th>
								<th />
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100">
							<tr v-for="link in share.links" :key="link.id" :class="link.live ? 'text-gray-700' : 'text-gray-400'">
								<td class="py-1.5">
									{{ useFormatTime(link.created_at) }}
									<span v-if="link.created_by" class="text-gray-400"> · {{ link.created_by }}</span>
								</td>
								<td class="py-1.5">
									<span v-if="link.revoked_at" class="text-red-600">{{ ctrans("Stopped") }}</span>
									<span v-else-if="!link.live">{{ ctrans("Expired") }}</span>
									<span v-else class="text-green-700">{{ ctrans("Until :date", { date: useFormatTime(link.expires_at) }) }}</span>
								</td>
								<td class="py-1.5">
									{{ link.email ?? "—" }}
									<span v-if="link.email" class="text-gray-400"> · {{ link.requested ? ctrans("asked by email") : ctrans("code") }}</span>
								</td>
								<td class="py-1.5 text-right tabular-nums">{{ link.number_views }}</td>
								<td class="py-1.5">
									<span v-if="link.views.length" v-tooltip="link.views.map((view) => `${useFormatTime(view.at)} · ${view.ip ?? ''}`).join('\n')" class="cursor-help">
										{{ useFormatTime(link.last_viewed_at ?? link.views[0].at) }} · {{ link.views[0].ip }}
									</span>
									<span v-else>—</span>
								</td>
								<td class="py-1.5 text-right">
									<Button v-if="link.live && share.can_create" type="tertiary" size="xxs" :label="ctrans('Stop')" @click="revoke(link)">
										<template #icon><FontAwesomeIcon icon="fal fa-ban" class="text-red-500" fixed-width aria-hidden="true" /></template>
									</Button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</Dialog>
	</div>
</template>
