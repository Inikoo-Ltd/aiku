<script setup lang="ts">
import { computed } from "vue"
import { set } from "lodash-es"
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import RegisterDashboardIris from "@/Iris/Components/IrisBlocks/RegisterDashboardIris.vue"
import { sendMessageToParent } from "@/Composables/Workshop"

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Record<string, any>
	indexBlock?: number
	screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: any): void
	(e: "autoSave"): void
}>()

const uploadImageRoute = computed(() => ({
	name: props.webpageData?.images_upload_route?.name,
	parameters: { modelHasWebBlocks: props.blockData?.id },
}))

const textToggle = ["bold", "italic", "underline", "bulletList", "orderedList", "alignLeft", "alignCenter", "alignRight", "customLink", "color", "clear", "undo", "redo"]

const onEdit = (path: string | string[], value: string) => {
	set(props.modelValue, path, value)
	emits("update:modelValue", props.modelValue)
	emits("autoSave")
}

const onActive = () => sendMessageToParent("activeBlock", props.indexBlock)

/**
 * The preview renders the live block, links included. Following one would leave the workshop,
 * so every anchor is neutralised here instead of rendering the block without its links.
 */
const onPreviewClick = (event: MouseEvent) => {
	const anchor = (event.target as HTMLElement)?.closest?.("a[href]")

	if (anchor) {
		event.preventDefault()
		event.stopPropagation()
	}

	onActive()
}
</script>

<template>
	<div @click.capture="onPreviewClick">
		<RegisterDashboardIris
			:fieldValue="modelValue"
			:screenType="screenType"
			:indexBlock="indexBlock"
			:isWorkshop="true">
			<template #hero-intro>
				<EditorV2
					:modelValue="modelValue?.hero?.intro"
					:toggle="textToggle"
					:uploadImageRoute="uploadImageRoute"
					@focus="onActive"
					@update:modelValue="value => onEdit(['hero', 'intro'], value)" />
			</template>

			<template #login-note>
				<EditorV2
					:modelValue="modelValue?.signup?.login_note"
					:toggle="textToggle"
					:uploadImageRoute="uploadImageRoute"
					@focus="onActive"
					@update:modelValue="value => onEdit(['signup', 'login_note'], value)" />
			</template>

			<template #faq-answer="{ item, index }">
				<EditorV2
					:modelValue="item.answer"
					:toggle="textToggle"
					:uploadImageRoute="uploadImageRoute"
					@focus="onActive"
					@update:modelValue="value => onEdit(['faq', 'items', index, 'answer'], value)" />
			</template>

			<template #footer-text>
				<EditorV2
					:modelValue="modelValue?.footer?.text"
					:toggle="textToggle"
					:uploadImageRoute="uploadImageRoute"
					@focus="onActive"
					@update:modelValue="value => onEdit(['footer', 'text'], value)" />
			</template>
		</RegisterDashboardIris>
	</div>
</template>
