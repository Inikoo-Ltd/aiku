<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'

const props = defineProps<{
	data: any[]
	loading: boolean
}>()

const emit = defineEmits<{
	(e: "click-session", session: any): void
}>()
</script>

<template>
	<div>
		<div v-if="loading" class="p-3 text-sm text-gray-400">
			Loading...
		</div>

		<div
			v-for="s in props.data"
			:key="s.ulid"
			@click="emit('click-session', s)"
			class="px-3 py-2 border-b cursor-pointer hover:bg-gray-50"
		>
			<div class="flex justify-between text-sm">
				<span>{{ s.contact_name || s.guest_identifier }}</span>
				<span class="text-xs text-gray-400">
					   {{ useFormatTime(s.last_message?.created_at ) }}
				</span>
			</div>

			<div class="text-xs text-gray-500 truncate">
				{{ s.last_message?.message }}
			</div>
		</div>
	</div>
</template>
