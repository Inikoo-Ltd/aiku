<script setup>
import { ref, computed, watch } from "vue"
import InputNumber from "primevue/inputnumber"
import InputGroup from "primevue/inputgroup"
import InputGroupAddon from "primevue/inputgroupaddon"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

const props = defineProps({
	data: {
		type: Object,
		required: true,
	},
	action: {
		type: Object,
		required: true,
	},
})

const emits = defineEmits(["update", "submit", "undo"])
const originalQuantityOrdered = ref(props.data.quantity_ordered)
const data = computed(() => props.data)
const loading = ref(false)

const onSubmit = async (type) => {
	if (loading.value) return
	loading.value = true
	try {
		await new Promise((resolve) => setTimeout(resolve, 1000))
		emits("submit", { type, data: props.data })
	} finally {
		loading.value = false
	}
}

const triggerInput = () => {
	props.data.inputTriggered = true
	emits("update", props.data)
}
const onUndo = () => {
	props.data.quantity_ordered = originalQuantityOrdered.value
	props.data.inputTriggered = false
	emits("undo", props.data.id)
	emits("update", props.data)
}

const onValueChange = () => {
	emits("update", props.data)
}

const onManualInputChange = (value) => {
	props.data.quantity_ordered = value
	emits("update", props.data)
}

watch(
	() => props.data.quantity_ordered,
	(newVal, oldVal) => {
		if (!props.data.inputTriggered) {
			originalQuantityOrdered.value = newVal
		}
	}
)
</script>

<template>
	<div>
		<div v-if="!data.inputTriggered" class="custom-input-number">
			<InputNumber
				v-model="data.quantity_ordered"
				inputClass="w-14 text-center"
				showButtons
				buttonLayout="horizontal"
				:min="0"
				:incrementButtonClass="loading ? 'p-disabled' : ''"
				:decrementButtonClass="loading ? 'p-disabled' : ''"
				@change="onValueChange"
				@focus="triggerInput"
				>
				<!-- Increment Button -->
				<template #incrementicon>
					<div
						class="flex items-center justify-center w-full h-full"
						@click="!loading && onSubmit('increment')"
						:class="{ 'cursor-not-allowed': loading }">
						<template v-if="loading">
							<svg
								class="loading-circle"
								viewBox="0 0 50 50"
								xmlns="http://www.w3.org/2000/svg">
								<circle
									class="circle-path"
									cx="25"
									cy="25"
									r="20"
									fill="none"
									stroke-width="4" />
							</svg>
						</template>
						<template v-else>
							<FontAwesomeIcon
								size="sm"
								icon="fas fa-plus"
								class="text-black"
								fixed-width />
						</template>
					</div>
				</template>

				<!-- Decrement Button -->
				<template #decrementicon>
					<div
						class="flex items-center justify-center w-full h-full"
						@click="onSubmit('decrement')"
						:class="{ 'cursor-not-allowed': loading }">
						<FontAwesomeIcon
							size="sm"
							icon="fas fa-minus"
							class="text-black"
							fixed-width />
					</div>
				</template>
			</InputNumber>
		</div>
		<div v-else class="custom-input-number">
			<InputGroup>
				<InputGroupAddon @click="onUndo">
					<FontAwesomeIcon
						size="sm"
						icon="fas fa-undo"
						class="text-black"
						fixed-width
						aria-hidden="true" />
				</InputGroupAddon>
				<InputNumber
					v-model="data.quantity_ordered"
					buttonLayout="horizontal"
					:style="{ width: '49px' }"
					:min="0"
					@update:modelValue="onManualInputChange" />
				<InputGroupAddon @click="onSubmit('save')">
					<FontAwesomeIcon
						icon="fas fa-save"
						size="sm"
						class="text-black"
						fixed-width
						aria-hidden="true" />
				</InputGroupAddon>
			</InputGroup>
		</div>
	</div>
</template>

<style scoped>
.custom-input-number :deep(.p-inputnumber) {
	--p-inputnumber-button-width: 35px;
	height: 35px;
}

.cursor-not-allowed {
	opacity: 0.5;
	cursor: not-allowed;
}

.loading-circle {
	width: 1rem;
	height: 1rem;
	animation: spin 1s linear infinite;
}

.circle-path {
	stroke: #000;
	stroke-linecap: round;
	stroke-dasharray: 125.6;
	stroke-dashoffset: 0;
	animation: dash 1.5s ease-in-out infinite;
}

@keyframes spin {
	from {
		transform: rotate(0deg);
	}
	to {
		transform: rotate(360deg);
	}
}

@keyframes dash {
	0% {
		stroke-dashoffset: 125.6;
	}
	50% {
		stroke-dashoffset: 62.8;
		transform: rotate(45deg);
	}
	100% {
		stroke-dashoffset: 125.6;
		transform: rotate(360deg);
	}
}
</style>
