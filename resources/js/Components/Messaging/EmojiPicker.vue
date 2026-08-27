<script setup lang="ts">
import { ref, onMounted, onUnmounted } from "vue"

const emit = defineEmits<{
    pick: [emoji: string]
}>()

const activeTab = ref("smileys")
const recentEmojis = ref<string[]>([])

const tabs = {
    smileys: ["😀", "😃", "😄", "😁", "😆", "😅", "🤣", "😂", "🙂", "😉", "😊", "😇", "🥰", "😍", "😘", "😋", "😜", "🤪", "🤨", "🧐", "😎", "🥳", "😏", "😒", "😞", "😔", "😢", "😭", "😤", "😠", "🤯", "😳", "🥶", "😱", "🤗", "🤔", "🤫", "😴", "🤤", "🙄"],
    gestures: ["👍", "👎", "👏", "🙌", "🙏", "👀", "💪", "🤝", "✌️", "🤞", "👌", "🤙", "👋", "🫡", "✋", "🤷", "🤦", "🫶"],
    work: ["✅", "❌", "⚠️", "🔥", "💯", "🎉", "📦", "🚚", "🏷️", "📋", "🔧", "🧹", "⏰", "📞", "💬", "🛒", "📦", "✍️", "🔍", "🚀", "⭐", "💡", "🧠", "😮‍💨", "☕"],
    hearts: ["❤️", "🧡", "💛", "💚", "💙", "💜", "🖤", "🤍", "💔", "💕"],
}

const pickEmoji = (emoji: string) => {
    emit("pick", emoji)
    updateRecent(emoji)
}

const updateRecent = (emoji: string) => {
    const recent = recentEmojis.value.filter((e) => e !== emoji)
    recent.unshift(emoji)
    recentEmojis.value = recent.slice(0, 16)
    localStorage.setItem("staff-chat-recent-emojis", JSON.stringify(recentEmojis.value))
}

const loadRecent = () => {
    const stored = localStorage.getItem("staff-chat-recent-emojis")
    if (stored) {
        try {
            recentEmojis.value = JSON.parse(stored)
        } catch {
            recentEmojis.value = []
        }
    }
}

onMounted(loadRecent)
</script>

<template>
    <div class="w-72 bg-white border border-gray-200 shadow-lg rounded p-2">
        <div class="flex gap-x-1 mb-2 border-b pb-2">
            <button
                v-for="tab in Object.keys(tabs)"
                :key="tab"
                @click="activeTab = tab"
                class="flex-1 px-2 py-1 text-xs font-medium rounded transition"
                :class="activeTab === tab ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100'"
            >
                {{ tab }}
            </button>
        </div>

        <div v-if="recentEmojis.length" class="mb-2 pb-2 border-b">
            <div class="text-xs font-medium text-gray-500 mb-1">Recent</div>
            <div class="grid grid-cols-8 gap-0.5">
                <button
                    v-for="emoji in recentEmojis"
                    :key="emoji"
                    @click="pickEmoji(emoji)"
                    class="h-8 w-8 text-xl rounded hover:bg-gray-100 transition flex items-center justify-center"
                >
                    {{ emoji }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-8 gap-0.5">
            <button
                v-for="emoji in tabs[activeTab as keyof typeof tabs]"
                :key="emoji"
                @click="pickEmoji(emoji)"
                class="h-8 w-8 text-xl rounded hover:bg-gray-100 transition flex items-center justify-center"
            >
                {{ emoji }}
            </button>
        </div>
    </div>
</template>
