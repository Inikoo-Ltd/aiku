<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWandMagicSparkles, faRotateLeft, faSpinner } from "@fortawesome/free-solid-svg-icons"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    modelValue?: string | null
    channel: "chat" | "email" | "whatsapp"
}>()

const emit = defineEmits<{
    (e: "update:modelValue", value: string): void
}>()

const isPolishing = ref(false)
const original = ref<string | null>(null)
const polished = ref<string | null>(null)
const notice = ref<string | null>(null)
const error = ref<string | null>(null)

watch(() => props.modelValue, (value) => {
    if (polished.value !== null && value !== polished.value) {
        original.value = null
        polished.value = null
    }
    if (!isPolishing.value) {
        notice.value = null
        error.value = null
    }
})

const polish = async () => {
    const text = props.modelValue ?? ""
    if (!text.trim() || isPolishing.value) {
        return
    }

    isPolishing.value = true
    notice.value = null
    error.value = null
    try {
        const { data } = await axios.post(route("grp.json.writing_quality.polish"), { text, channel: props.channel })
        if ((props.modelValue ?? "") !== text) {
            return
        }
        if (data.status === "polished") {
            original.value = text
            polished.value = data.text
            emit("update:modelValue", data.text)
        } else {
            notice.value = data.status === "unchanged"
                ? ctrans("Nothing to polish: it already reads well.")
                : ctrans("Could not polish this without changing what it says, so it stays as you wrote it.")
        }
    } catch (exception: any) {
        const errors = exception?.response?.data?.errors
        error.value = errors ? Object.values(errors).flat()[0] as string : (exception?.response?.data?.message ?? ctrans("Polish failed. Try again."))
    } finally {
        isPolishing.value = false
    }
}

const undo = () => {
    if (original.value === null) {
        return
    }
    const text = original.value
    original.value = null
    polished.value = null
    emit("update:modelValue", text)
}
</script>

<template>
    <div class="flex min-w-0 items-center gap-1">
        <button v-if="original === null" type="button" @mousedown.prevent @click="polish" :disabled="isPolishing || !(modelValue ?? '').trim()"
            class="flex h-8 items-center gap-1.5 rounded-lg px-2 text-xs text-gray-500 transition-colors hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50"
            v-tooltip="ctrans('Correct the grammar, spelling and flow of what you wrote, keeping your words, language and meaning')">
            <FontAwesomeIcon :icon="isPolishing ? faSpinner : faWandMagicSparkles" :spin="isPolishing" class="text-sm" fixed-width aria-hidden="true" />
            {{ isPolishing ? ctrans("Polishing…") : ctrans("Polish") }}
        </button>
        <template v-else>
            <span class="flex items-center gap-1.5 px-1 text-xs text-gray-500">
                <FontAwesomeIcon :icon="faWandMagicSparkles" class="text-sm" fixed-width aria-hidden="true" />
                {{ ctrans("Polished: read it before sending") }}
            </span>
            <button type="button" @mousedown.prevent @click="undo"
                class="flex h-8 items-center gap-1.5 rounded-lg px-2 text-xs text-gray-500 transition-colors hover:bg-gray-100">
                <FontAwesomeIcon :icon="faRotateLeft" class="text-sm" fixed-width aria-hidden="true" />
                {{ ctrans("Undo") }}
            </button>
        </template>
        <span v-if="notice" class="truncate text-xs text-gray-500" :title="notice">{{ notice }}</span>
        <span v-if="error" class="truncate text-xs text-red-600" :title="error">{{ error }}</span>
    </div>
</template>
