<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import SelectButton from "primevue/selectbutton"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import { ctrans } from "@/Composables/useTrans"
import { useColorTheme } from "@/Composables/useStockList"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCamera, faCheck, faBuilding } from "@fal"

library.add(faCamera, faCheck, faBuilding)

export interface PortalProfile {
	name: string | null
	phone: string | null
	theme: number
	avatar: object | null
	display_name: string
	company_logo: object | null
}

const props = defineProps<{
	profile: PortalProfile
	updateRoute: string
	theme: number
}>()

const emit = defineEmits<{
	(e: "update:theme", value: number): void
	(e: "companyDetails"): void
}>()

const isOpen = ref(false)
const name = ref(props.profile.name ?? "")
const phone = ref(props.profile.phone ?? "")
const isSaving = ref(false)
const isSaved = ref(false)
const errors = ref<Record<string, string>>({})
const photoInput = ref<HTMLInputElement | null>(null)

const themes = useColorTheme.slice(0, 10).map((colours, index) => ({ index, background: colours[0], active: colours[2], accent: colours[3] }))

const picture = computed(() => props.profile.avatar ?? props.profile.company_logo)
const themeColour = computed(() => (useColorTheme[props.theme] ?? useColorTheme[0])[0])

const initials = computed(() =>
	(props.profile.display_name ?? "")
		.split(/\s+/)
		.filter(Boolean)
		.slice(0, 2)
		.map((word) => word[0]?.toUpperCase())
		.join("")
)

const save = (data: Record<string, unknown>, onSuccess?: () => void) => {
	isSaving.value = true
	errors.value = {}
	router.post(props.updateRoute, data, {
		preserveScroll: true,
		preserveState: true,
		forceFormData: data.photo !== undefined,
		only: ["profile"],
		onSuccess,
		onError: (response) => (errors.value = response),
		onFinish: () => (isSaving.value = false),
	})
}

const saveDetails = () =>
	save({ name: name.value || null, phone: phone.value || null }, () => {
		isSaved.value = true
		setTimeout(() => (isSaved.value = false), 2000)
	})

const chooseTheme = (index: number) => {
	emit("update:theme", index)
	save({ theme: index })
}

const choosePhoto = (event: Event) => {
	const file = (event.target as HTMLInputElement).files?.[0]
	if (file) {
		save({ photo: file })
	}
}

const openCompanyDetails = () => {
	isOpen.value = false
	emit("companyDetails")
}
</script>

<template>
	<div>
		<button
			type="button"
			class="flex items-center gap-2 rounded-full border border-gray-200 bg-white py-1 pl-3 pr-1 text-sm text-gray-700 shadow-sm transition hover:border-gray-300 hover:bg-gray-50"
			:aria-label="ctrans('Your profile')"
			@click="isOpen = true">
			<span class="hidden max-w-[10rem] truncate font-medium sm:inline">{{ profile.display_name }}</span>
			<span class="relative flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full text-xs font-semibold text-white" :style="{ backgroundColor: themeColour }">
				<span>{{ initials || "?" }}</span>
				<Image v-if="picture" :src="picture" class="absolute inset-0 h-full w-full object-cover" :alt="profile.display_name" />
			</span>
		</button>

		<Dialog v-model:visible="isOpen" modal dismissableMask :header="ctrans('Your profile')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }">
			<div class="space-y-5 text-sm text-gray-700">
				<p v-if="errors.preview" class="rounded border border-red-200 bg-red-50 p-2 text-xs text-red-700">{{ errors.preview }}</p>
				<div class="flex items-center gap-4">
					<span class="relative flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full text-lg font-semibold text-white" :style="{ backgroundColor: themeColour }">
						<span>{{ initials || "?" }}</span>
						<Image v-if="picture" :src="picture" class="absolute inset-0 h-full w-full object-cover" :alt="profile.display_name" />
					</span>
					<div class="space-y-1">
						<Button type="tertiary" size="xs" :label="profile.avatar ? ctrans('Change photo') : ctrans('Add a photo')" :loading="isSaving" @click="photoInput?.click()">
							<template #icon><FontAwesomeIcon icon="fal fa-camera" fixed-width aria-hidden="true" /></template>
						</Button>
						<input ref="photoInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="choosePhoto" />
						<p v-if="errors.photo" class="text-xs text-red-600">{{ errors.photo }}</p>
					</div>
				</div>

				<form class="space-y-3" @submit.prevent="saveDetails">
					<label class="block space-y-1">
						<span class="text-xs font-medium text-gray-500">{{ ctrans("Your name") }}</span>
						<InputText v-model="name" class="w-full" autocomplete="name" :invalid="!!errors.name" />
						<span v-if="errors.name" class="text-xs text-red-600">{{ errors.name }}</span>
					</label>
					<label class="block space-y-1">
						<span class="text-xs font-medium text-gray-500">{{ ctrans("Your phone") }}</span>
						<InputText v-model="phone" type="tel" class="w-full" autocomplete="tel" :invalid="!!errors.phone" />
						<span v-if="errors.phone" class="text-xs text-red-600">{{ errors.phone }}</span>
					</label>
					<div class="flex justify-end">
						<Button type="tertiary" size="xs" :label="isSaved ? ctrans('Saved') : ctrans('Save')" :loading="isSaving" @click="saveDetails">
							<template v-if="isSaved" #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
						</Button>
					</div>
				</form>

				<div class="space-y-2">
					<div class="text-xs font-medium text-gray-500">{{ ctrans("Colours") }}</div>
					<SelectButton
						:modelValue="theme"
						:options="themes"
						optionValue="index"
						dataKey="index"
						:allowEmpty="false"
						:aria-label="ctrans('Colours')"
						:pt="{ root: { class: 'flex flex-wrap gap-2 !bg-transparent !p-0 !border-0' }, pcToggleButton: { root: { class: '!rounded-full !p-0 !border-0 !bg-transparent !shadow-none' }, content: { class: '!p-0 !bg-transparent !shadow-none' } } }"
						@update:modelValue="(index: number) => chooseTheme(index)">
						<template #option="{ option }">
							<span
								class="flex h-9 w-9 items-center justify-center rounded-full ring-offset-2 transition"
								:class="theme === option.index ? 'ring-2 ring-gray-800' : 'hover:ring-2 hover:ring-gray-300'"
								:style="{ background: `linear-gradient(135deg, ${option.background} 55%, ${option.active} 55%)` }">
								<span class="h-2 w-2 rounded-full" :style="{ backgroundColor: option.accent }" />
							</span>
						</template>
					</SelectButton>
					<p v-if="errors.theme" class="text-xs text-red-600">{{ errors.theme }}</p>
				</div>

				<div class="space-y-1 border-t border-gray-100 pt-4">
					<p class="text-xs text-gray-500">{{ ctrans("Company name, phone, website or address changed? Send it to us: we check it and update it.") }}</p>
					<Button type="tertiary" size="xs" :label="ctrans('Change company details')" @click="openCompanyDetails">
						<template #icon><FontAwesomeIcon icon="fal fa-building" fixed-width aria-hidden="true" /></template>
					</Button>
				</div>
			</div>
		</Dialog>
	</div>
</template>
