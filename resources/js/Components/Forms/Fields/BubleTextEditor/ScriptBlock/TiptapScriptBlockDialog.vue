<script setup lang="ts">
import { ref, watch } from "vue"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
	show: boolean
	html: string
	isNew: boolean
}>()

const emit = defineEmits<{
	(e: "close"): void
	(e: "update", value: string): void
}>()

const visible = ref(props.show)
const code = ref(props.html)
const codeError = ref("")

watch(
	() => props.show,
	(isShown) => (visible.value = isShown),
)

function closeDialog() {
	visible.value = false
	emit("close")
}

function save() {
	if (!code.value.trim()) {
		codeError.value = ctrans("Write the HTML or script to add.")
		return
	}
	emit("update", code.value)
	closeDialog()
}
</script>

<template>
	<Dialog
		v-model:visible="visible"
		:header="isNew ? ctrans('Add script') : ctrans('Edit script')"
		modal
		class="w-full max-w-2xl"
		@hide="closeDialog"
	>
		<form class="flex flex-col space-y-4" @submit.prevent="save">
			<div>
				<label for="input-script-block-code" class="mb-2 block select-none text-sm text-gray-600">
					{{ ctrans("HTML and scripts") }} <span class="text-red-500">*</span>
				</label>
				<Textarea
					id="input-script-block-code"
					v-model="code"
					rows="12"
					class="w-full font-mono text-xs"
					spellcheck="false"
					placeholder="<div id=&quot;calculator&quot;></div>&#10;<script>…</script>"
				/>
				<p v-if="codeError" class="mt-1 text-xs text-red-600">{{ codeError }}</p>
				<p class="mt-1 text-xs text-gray-500">
					{{ ctrans("Shown at this point of the article and run on the website. Keep the article text in the content: search engines may not read what a script draws.") }}
				</p>
			</div>

			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="closeDialog">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="primary" full nativeType="submit" :label="isNew ? ctrans('Add script') : ctrans('Apply')">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</form>
	</Dialog>
</template>
