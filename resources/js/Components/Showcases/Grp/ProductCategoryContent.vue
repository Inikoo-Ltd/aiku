<script setup lang="ts">
import ReviewContent from '@/Components/ReviewContent.vue'

const props = defineProps<{
	data: {
		productCategory: {
		}
	}
}>()

</script>

<template>
	<div class="p-4">
		<div class="grid grid-cols-2">
			<ReviewContent :data="data.productCategory" />
		</div>
	</div>
</template>