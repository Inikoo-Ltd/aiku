<script setup lang="ts">
import { ref, computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPaperclip } from "@fortawesome/free-solid-svg-icons"
import { ctrans } from "@/Composables/useTrans"

const files = defineModel<File[]>({ required: true })

const props = defineProps<{
    errors: Record<string, string>
}>()

const error = computed(() => Object.entries(props.errors).find(([field]) => field.startsWith("attachments"))?.[1])

const input = ref<HTMLInputElement | null>(null)

const add = (event: Event) => {
    const target = event.target as HTMLInputElement
    files.value = [...files.value, ...Array.from(target.files ?? [])].slice(0, 10)
    target.value = ""
}

const remove = (index: number) => {
    files.value = files.value.filter((_, i) => i !== index)
}
</script>

<template>
    <div class="flex flex-col gap-1">
        <ul v-if="files.length" class="flex flex-col gap-1">
            <li v-for="(file, index) in files" :key="index" class="flex items-center gap-2 text-sm text-gray-700">
                <FontAwesomeIcon :icon="faPaperclip" class="text-gray-400" fixed-width aria-hidden="true" />
                <span class="truncate">{{ file.name }}</span>
                <span class="text-xs text-gray-400">{{ (file.size / 1024).toFixed(0) }} KB</span>
                <button type="button" class="text-xs text-red-500 hover:underline" @click="remove(index)">
                    {{ ctrans("Remove") }}
                </button>
            </li>
        </ul>
        <input ref="input" type="file" multiple class="hidden"
            accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.pptx" @change="add" />
        <button v-if="files.length < 10" type="button" class="self-start text-xs text-indigo-600 hover:underline"
            @click="input?.click()">
            <FontAwesomeIcon :icon="faPaperclip" fixed-width aria-hidden="true" />
            {{ ctrans("Attach files") }}
        </button>
        <p v-if="error" class="text-xs text-red-500 leading-snug">{{ error }}</p>
    </div>
</template>
