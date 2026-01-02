<script setup lang="ts">
import { inject, computed } from "vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faCheckDouble } from "@far"

type SenderType = "guest" | "user" | "agent" | "system"
type MessageStatus = "sending" | "sent" | "failed"
type ViewerType = "user" | "agent"

interface Message {
    sender_type: SenderType
    message_text: string
    created_at: string
    is_read?: boolean
    _status?: MessageStatus
}

const props = defineProps<{ 
    message: Message 
    viewerType: ViewerType
}>()

const layout = inject<any>("layout")

const isUser = computed(() =>
    props.message.sender_type === "guest" ||
    props.message.sender_type === "user"
)

const isFromViewer = computed(() => {
	if (props.viewerType === "agent") {
		return props.message.sender_type === "agent"
	}

	return ["user", "guest"].includes(props.message.sender_type)
})

const isSending = computed(() => props.message._status === "sending")

const bubbleClass = computed(() => ({
	"bubble-primary": isFromViewer.value,
	"bubble-secondary": !isFromViewer.value,
	"bubble-system": props.message.sender_type === "system",
}))


const time = computed(() =>
    new Date(props.message.created_at).toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
    })
)

const readIcon = computed(() =>
    props.message.is_read ? faCheckDouble : faCheck
)
</script>

<template>
	<div
		class="flex flex-col gap-0.5 text-sm leading-snug shadow-sm max-w-[78%] px-2.5 py-1.5 rounded-xl"
		:class="bubbleClass"
	>
		<p class="whitespace-pre-wrap break-words">
			{{ message.message_text }}
		</p>

		<div class="flex items-center justify-end gap-1 text-[10px] opacity-70 min-h-[14px]">
			<span v-if="!isSending" class="leading-none">
				{{ time }}
			</span>

			<span v-else class="flex items-center animate-pulse">
				<LoadingIcon />
			</span>

			<span v-if="isUser && !isSending" class="leading-none">
				<FontAwesomeIcon :icon="readIcon" />
			</span>
		</div>
	</div>
</template>


<style scoped>
.bubble-primary {
	background-color: v-bind("layout.app.theme[4]");
	color: v-bind("layout.app.theme[5]");
	border-bottom-right-radius: 4px;
}

.bubble-secondary {
	@apply bg-gray-200 text-gray-800;
	border-bottom-left-radius: 4px;
}

.bubble-system {
	@apply bg-amber-100 text-amber-800 italic text-xs;
}
</style>
