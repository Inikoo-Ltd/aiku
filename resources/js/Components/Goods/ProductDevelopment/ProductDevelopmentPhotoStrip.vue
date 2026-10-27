<!--
  - Author Louis Perez
  - Created on 27-10-2026-13h-00m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import TicketAttachmentPreview, { type TicketAttachment } from "@/Components/Tickets/TicketAttachmentPreview.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCameraAlt, faSpinner, faTimes } from "@fal"
library.add(faCameraAlt, faSpinner, faTimes)

export interface ProductDevelopmentPhoto {
    id: number
    name: string | null
    url: string | null
    preview_url: string | null
    remove_route: routeType | null
}

const props = defineProps<{
    photos: ProductDevelopmentPhoto[]
    uploadRoute?: routeType | null
    emptyText?: string
    only?: string[]
}>()

const input = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const removing = ref<number | null>(null)
const error = ref<string | null>(null)
const previewIndex = ref<number | null>(null)

const previewFiles = (): TicketAttachment[] => props.photos
    .filter((photo) => photo.url)
    .map((photo) => ({ name: photo.name ?? `${photo.id}.jpg`, url: photo.url!, mime: "image/jpeg" }))

function open(photo: ProductDevelopmentPhoto) {
    previewIndex.value = props.photos.filter((item) => item.url).findIndex((item) => item.id === photo.id)
}

function onPicked(event: Event) {
    const target = event.target as HTMLInputElement
    const photos = Array.from(target.files ?? [])
    target.value = ""
    if (!photos.length || !props.uploadRoute) {
        return
    }
    error.value = null
    router.post(route(props.uploadRoute.name, props.uploadRoute.parameters), { photos }, {
        forceFormData: true,
        only: props.only ?? [],
        preserveScroll: true,
        preserveState: true,
        onStart: () => uploading.value = true,
        onFinish: () => uploading.value = false,
        onError: (errors) => error.value = Object.values(errors)[0] ?? null,
    })
}

function remove(photo: ProductDevelopmentPhoto) {
    if (!photo.remove_route) {
        return
    }
    error.value = null
    router.delete(route(photo.remove_route.name, photo.remove_route.parameters), {
        only: props.only ?? [],
        preserveScroll: true,
        preserveState: true,
        onStart: () => removing.value = photo.id,
        onFinish: () => removing.value = null,
        onError: (errors) => error.value = Object.values(errors)[0] ?? null,
    })
}
</script>

<template>
    <div>
        <div class="flex flex-wrap gap-2">
            <div v-for="photo in photos" :key="photo.id" class="group relative h-16 w-16 overflow-hidden rounded-md border border-gray-200 bg-gray-50">
                <button type="button" class="h-full w-full" :aria-label="ctrans('View :name', { name: photo.name ?? ctrans('photo') })" @click="open(photo)">
                    <img v-if="photo.preview_url" :src="photo.preview_url" :alt="photo.name ?? ''" class="h-full w-full object-cover" loading="lazy" />
                </button>
                <button
                    v-if="photo.remove_route"
                    type="button"
                    class="absolute right-0.5 top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-white/90 text-xs text-gray-600 shadow-sm hover:text-red-600"
                    :aria-label="ctrans('Remove photo')"
                    v-tooltip="ctrans('Remove photo')"
                    @click="remove(photo)"
                >
                    <FontAwesomeIcon :icon="removing === photo.id ? 'fal fa-spinner' : 'fal fa-times'" :spin="removing === photo.id" fixed-width aria-hidden="true" />
                </button>
            </div>

            <button
                v-if="uploadRoute"
                type="button"
                class="flex h-16 w-16 flex-col items-center justify-center gap-1 rounded-md border border-dashed border-gray-300 text-[11px] text-gray-500 transition-colors hover:border-[--app-accent] hover:text-[--app-accent]"
                :disabled="uploading"
                :aria-label="ctrans('Add photos')"
                @click="input?.click()"
            >
                <FontAwesomeIcon :icon="uploading ? 'fal fa-spinner' : 'fal fa-camera-alt'" :spin="uploading" class="text-base" fixed-width aria-hidden="true" />
                {{ ctrans("Add") }}
            </button>

            <p v-if="!photos.length && !uploadRoute && emptyText" class="text-xs text-gray-400">{{ emptyText }}</p>
        </div>

        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>

        <TicketAttachmentPreview v-model:index="previewIndex" :files="previewFiles()" />
        <input ref="input" type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" @change="onPicked" />
    </div>
</template>
