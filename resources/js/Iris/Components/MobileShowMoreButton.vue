<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown } from "@fal"
import { ctrans } from "@/Composables/useTrans"

defineProps<{
	count: number
}>()

const emit = defineEmits<{
	(e: "show"): void
}>()

const button = ref<HTMLElement | null>(null)

onMounted(() => {
	if (!button.value || !window.IntersectionObserver) return

	const observer = new IntersectionObserver(([entry]) => {
		if (entry.isIntersecting) {
			button.value?.classList.add("is-pulsing")
			observer.disconnect()
		}
	}, { threshold: 0.6 })

	observer.observe(button.value)
	onBeforeUnmount(() => observer.disconnect())
})
</script>

<template>
	<button
		ref="button"
		type="button"
		class="mobile-show-more-btn relative w-full rounded py-4 px-6 text-lg font-bold text-white flex items-center justify-center gap-x-3 active:scale-[0.98] transition"
		@click="emit('show')">
		{{ ctrans("Show :count more", { count: String(count) }) }}
		<FontAwesomeIcon :icon="faChevronDown" aria-hidden="true" />
	</button>
</template>

<style scoped>
.mobile-show-more-btn {
	background: linear-gradient(100deg, var(--theme-color-0, #1d2d44), color-mix(in srgb, var(--theme-color-4, #4b5058) 80%, var(--theme-color-0, #1d2d44)));
	box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25);
	text-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
}

.mobile-show-more-btn::after {
	content: '';
	position: absolute;
	inset: 0;
	border-radius: inherit;
	z-index: -1;
	box-shadow:
		0 0 18px color-mix(in srgb, var(--theme-color-0, #1d2d44) 55%, transparent),
		0 0 42px color-mix(in srgb, var(--theme-color-4, #4b5058) 35%, transparent);
	opacity: 0.6;
}

.mobile-show-more-btn.is-pulsing::after {
	animation: mobile-show-more-glow 1.6s ease-in-out 3;
}

@keyframes mobile-show-more-glow {
	0%, 100% {
		opacity: 0.45;
		transform: scale(1);
	}
	50% {
		opacity: 1;
		transform: scale(1.03);
	}
}

@media (prefers-reduced-motion: reduce) {
	.mobile-show-more-btn.is-pulsing::after {
		animation: none;
	}
}
</style>
