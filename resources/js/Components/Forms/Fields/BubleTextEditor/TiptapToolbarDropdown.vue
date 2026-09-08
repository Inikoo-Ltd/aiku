<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

const props = withDefaults(
	defineProps<{
		label: string
		alignMenu?: 'left' | 'right'
		menuHeight?: number
		isActive?: boolean
		disabled?: boolean
	}>(),
	{
		alignMenu: 'left',
		menuHeight: 240,
		isActive: false,
		disabled: false,
	}
)

const isOpen = ref(false)
const openUpwards = ref(false)
const root = ref<HTMLElement | null>(null)

const close = () => {
	isOpen.value = false
}

const toggle = () => {
	if (props.disabled) return
	if (isOpen.value) {
		close()
		return
	}

	const rect = root.value?.getBoundingClientRect()
	if (rect) {
		const spaceBelow = window.innerHeight - rect.bottom
		openUpwards.value = spaceBelow < props.menuHeight && rect.top > spaceBelow
	}

	isOpen.value = true
}

const onPointerDownOutside = (event: Event) => {
	if (!isOpen.value) return
	if (root.value?.contains(event.target as Node)) return
	close()
}

const onKeydown = (event: KeyboardEvent) => {
	if (event.key === 'Escape') close()
}

onMounted(() => {
	document.addEventListener('pointerdown', onPointerDownOutside, true)
	document.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
	document.removeEventListener('pointerdown', onPointerDownOutside, true)
	document.removeEventListener('keydown', onKeydown)
})

defineExpose({ close })
</script>

<template>
	<div ref="root" class="relative shrink-0">
		<button
			type="button"
			v-tooltip="label"
			:aria-label="label"
			:aria-expanded="isOpen"
			:disabled="disabled"
			aria-haspopup="true"
			@mousedown.prevent
			@click="toggle"
			:class="[
				'inline-flex h-7 shrink-0 items-center justify-center gap-1 rounded px-1.5 text-gray-600 transition-colors',
				'disabled:bg-transparent disabled:text-gray-300 disabled:cursor-not-allowed',
				isActive || isOpen ? 'bg-blue-100 text-blue-800' : 'hover:bg-blue-50',
			]">
			<slot name="trigger" :isOpen="isOpen" />
		</button>

		<div
			v-if="isOpen"
			@mousedown.prevent
			class="absolute z-50 rounded-md border border-gray-200 bg-white py-1 shadow-lg"
			:class="[
				openUpwards ? 'bottom-full mb-1' : 'top-full mt-1',
				alignMenu === 'right' ? 'right-0' : 'left-0',
			]">
			<slot name="menu" :close="close" />
		</div>
	</div>
</template>
