<script setup lang="ts">
import { ref } from 'vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faRobot } from '@far'
import { faGlobe } from '@fal'
import { faWhatsapp } from '@fortawesome/free-brands-svg-icons'

const props = defineProps<{
	data: any[]
	loading: boolean
	loadingMore?: boolean
	hasMore?: boolean
	showAiSummary?: boolean
}>()

const emit = defineEmits<{
	(e: "click-session", session: any): void
	(e: "load-more"): void
}>()

const sentimentClass = (sentiment?: string): string => {
	if (sentiment === 'positive') return 'bg-green-100 text-green-700'
	if (sentiment === 'negative') return 'bg-red-100 text-red-600'
	return 'bg-gray-100 text-gray-500'
}

const activePopover = ref<string | null>(null)
const popStyle = ref<Record<string, string>>({})

const POP_WIDTH = 256

const openPopover = (event: MouseEvent, session: any) => {
	if (!session.ai_summary?.summary) return
	const rect = (event.currentTarget as HTMLElement).getBoundingClientRect()

	let left = rect.left
	if (left + POP_WIDTH > window.innerWidth - 8) left = window.innerWidth - POP_WIDTH - 8
	if (left < 8) left = 8

	const style: Record<string, string> = { left: `${left}px`, width: `${POP_WIDTH}px` }
	const spaceAbove = rect.top - 16
	const spaceBelow = window.innerHeight - rect.bottom - 16
	if (spaceAbove >= spaceBelow) {
		style.bottom = `${window.innerHeight - rect.top + 8}px`
		style.maxHeight = `${spaceAbove}px`
	} else {
		style.top = `${rect.bottom + 8}px`
		style.maxHeight = `${spaceBelow}px`
	}

	popStyle.value = style
	activePopover.value = session.ulid
}

const closePopover = () => { activePopover.value = null }

const onScroll = (event: Event) => {
	if (!props.hasMore || props.loadingMore) return
	const el = event.target as HTMLElement
	if (el.scrollTop + el.clientHeight >= el.scrollHeight - 60) {
		emit('load-more')
	}
}
</script>

<template>
	<div class="flex-1 min-h-0 overflow-y-auto" @scroll="onScroll">
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
				<div class="flex items-center gap-1.5 min-w-0">
					<span v-if="s.channel === 'whatsapp'" class="shrink-0 text-green-500" title="WhatsApp">
						<FontAwesomeIcon :icon="faWhatsapp" class="text-xs" />
					</span>
					<span v-else-if="s.channel === 'website'" class="shrink-0 text-blue-500" title="Website">
						<FontAwesomeIcon :icon="faGlobe" class="text-xs" />
					</span>
					<span class="truncate">{{ s.contact_name || s.guest_identifier }}</span>
				</div>
				<span class="text-xs text-gray-400 shrink-0 ml-2">
					   {{ useFormatTime(s.last_message?.created_at ) }}
				</span>
			</div>

			<div class="text-xs text-gray-500 truncate">
				{{ s.last_message?.message }}
			</div>

			<div v-if="showAiSummary && s.ai_summary?.summary"
				class="mt-1 flex items-center gap-1.5"
				@click.stop
				@mouseenter="openPopover($event, s)"
				@mouseleave="closePopover">
				<FontAwesomeIcon :icon="faRobot" class="text-indigo-400 text-[10px] shrink-0" />
				<span class="text-[11px] text-emerald-600 truncate">{{ s.ai_summary.summary }}</span>

				<Teleport to="body">
					<div v-if="activePopover === s.ulid"
						class="fixed z-[9999] bg-white border border-gray-200 rounded-lg shadow-xl p-3 text-left overflow-y-auto"
						:style="popStyle">
						<p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
							<FontAwesomeIcon :icon="faRobot" class="text-indigo-400" />
							AI Summary
							<span v-if="s.ai_summary.sentiment"
								class="ml-auto text-[10px] font-medium capitalize px-1.5 py-0.5 rounded-full"
								:class="sentimentClass(s.ai_summary.sentiment)">
								{{ s.ai_summary.sentiment }}
							</span>
						</p>
						<p class="text-xs text-gray-700 leading-relaxed">{{ s.ai_summary.summary }}</p>
						<template v-if="s.ai_summary.key_points?.length">
							<p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mt-2.5 mb-1.5">Key Points</p>
							<ul class="space-y-1">
								<li v-for="(point, i) in s.ai_summary.key_points" :key="i"
									class="flex items-start gap-1.5 text-xs text-gray-600">
									<span class="mt-1.5 w-1 h-1 rounded-full bg-indigo-400 shrink-0"></span>
									{{ point }}
								</li>
							</ul>
						</template>
					</div>
				</Teleport>
			</div>
		</div>

		<div v-if="loadingMore" class="p-3 text-center text-xs text-gray-400">
			Loading more...
		</div>
	</div>
</template>
