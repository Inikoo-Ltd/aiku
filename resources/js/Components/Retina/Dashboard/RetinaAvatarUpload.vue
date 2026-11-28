<script setup lang="ts">
import { computed, ref } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUser, faCamera } from "@fas"
import Image from "@common/Components/Image.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"

const avatar = computed(() => (usePage().props as any).auth?.user?.avatar_thumbnail)
const fileInput = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)
const isUploading = ref(false)
const errorMessage = ref<string | null>(null)

const upload = (file?: File | null) => {
    isDragging.value = false
    if (!file) {
        return
    }
    if (!file.type.startsWith("image/")) {
        errorMessage.value = ctrans("Please choose an image")
        return
    }
    errorMessage.value = null
    router.post(route("retina.models.profile.update"), { _method: "patch", image: file }, {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => (isUploading.value = true),
        onFinish: () => (isUploading.value = false),
        onError: (errors) => (errorMessage.value = errors.image || ctrans("The photo could not be saved")),
    })
}
</script>

<template>
    <span class="relative inline-flex shrink-0 flex-col items-start">
        <Image v-if="avatar" :src="avatar" imageCover class="size-8 rounded-full object-cover" />

        <button v-else type="button"
            class="group relative flex size-8 items-center justify-center rounded-full border border-dashed transition-colors"
            :class="isDragging ? 'border-[var(--app-accent)] bg-[var(--app-accent-soft)]' : 'border-gray-300 bg-gray-100 hover:border-gray-500'"
            v-tooltip="ctrans('Add photo')" :disabled="isUploading"
            @click="fileInput?.click()"
            @dragover.prevent="isDragging = true" @dragleave="isDragging = false"
            @drop.prevent="upload($event.dataTransfer?.files?.[0])">
            <LoadingIcon v-if="isUploading" class="text-gray-500" />
            <template v-else>
                <FontAwesomeIcon :icon="faUser" class="size-4 text-gray-500" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faCamera"
                    class="absolute -bottom-1 -right-1 rounded-full bg-white p-0.5 text-[10px] text-gray-500 opacity-0 shadow transition-opacity group-hover:opacity-100"
                    fixed-width aria-hidden="true" />
            </template>
        </button>

        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="upload(($event.target as HTMLInputElement).files?.[0])" />
        <span v-if="errorMessage" class="absolute top-full mt-1 whitespace-nowrap text-xs text-red-600">{{ errorMessage }}</span>
    </span>
</template>
