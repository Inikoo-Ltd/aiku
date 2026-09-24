<script setup lang="ts">
import { computed } from "vue"
import { set } from "lodash-es"
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import RegisterDashboardIris from "@/Iris/Components/IrisBlocks/RegisterDashboardIris.vue"
import { sendMessageToParent } from "@/Composables/Workshop"
import { panelKeys } from "@/Components/CMS/Webpage/RegisterDashboard/Blueprint"

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
 * Opens the side editor panel of the part that was clicked. Every part of the block names its panel
 * in data-rd-panel, and a benefit or FAQ question adds its position so its row opens as well.
 */
const openPanelOf = (target: HTMLElement | null) => {
	const part = target?.closest?.("[data-rd-panel]") as HTMLElement | null
	const panelKey = part?.dataset.rdPanel ? panelKeys[part.dataset.rdPanel] : null

	if (!panelKey) {
		return
	}

	sendMessageToParent("activeChildBlock", panelKey)

	if (part?.dataset.rdIndex !== undefined) {
		sendMessageToParent("activeChildBlockArray", Number(part.dataset.rdIndex))
	}
}

/**
 * The preview renders the live block, links included. Following one would leave the workshop,
 * so every anchor is neutralised here instead of rendering the block without its links.
 */
const onPreviewClick = (event: MouseEvent) => {
	const target = event.target as HTMLElement | null
	const anchor = target?.closest?.("a[href]")

	if (anchor) {
		event.preventDefault()
		event.stopPropagation()
	}

	onActive()
	openPanelOf(target)
}
</script>

<template>
	<div @click.capture="onPreviewClick">
		<RegisterDashboardIris
			:fieldValue="modelValue"
			:screenType="screenType"
			:indexBlock="indexBlock"
			:isWorkshop="true">
			<template #editable="{ path, value, placeholder }">
				<div class="rd-editable">
					<EditorV2
						:modelValue="value"
						:toggle="textToggle"
						:placeholder="placeholder"
						:uploadImageRoute="uploadImageRoute"
						@focus="onActive"
						@update:modelValue="newValue => onEdit(path, newValue)" />
				</div>
			</template>
		</RegisterDashboardIris>
	</div>
</template>
