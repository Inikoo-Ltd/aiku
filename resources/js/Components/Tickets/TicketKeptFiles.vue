<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Mon, 05 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ModalConfirmation from "@/Components/Utils/ModalConfirmation.vue"
import { attachmentIconFor } from "@/Components/Tickets/TicketAttachmentPreview.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes } from "@fal"

library.add(faTimes)

defineProps<{
    images: (Record<string, string> & { ulid?: string })[]
    attachments: { name: string; ulid?: string; mime?: string | null }[]
}>()

const emit = defineEmits<{
    (e: "remove", ulid: string): void
}>()

// Removing a file is only sent once the edit is saved, but it is still one-way enough (the undo
// is cancelling the whole edit) to make somebody confirm it first.
const remove = (ulid: string, closeModal: () => void) => {
    emit("remove", ulid)
    closeModal()
}
</script>

<template>
    <div v-if="images.length || attachments.length" class="flex flex-wrap gap-1.5">
        <span v-for="image in images" :key="image.ulid" class="relative">
            <img :src="image.original" :alt="image.name" class="h-12 w-12 rounded border border-gray-200 object-cover" />
            <ModalConfirmation v-if="image.ulid" :title="ctrans('Remove this image?')" :description="ctrans('It will be gone once you save. Cancelling the edit keeps it.')">
                <template #default="{ changeModel }">
                    <button v-tooltip="ctrans('Remove')" type="button" class="absolute -top-1.5 -right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-gray-500 text-white hover:bg-red-500" @click="changeModel">
                        <FontAwesomeIcon icon="fal fa-times" class="text-[9px]" fixed-width aria-hidden="true" />
                    </button>
                </template>
                <template #btn-yes="{ closeModal }">
                    <Button type="red" :label="ctrans('Remove')" @click="remove(image.ulid, closeModal)" />
                </template>
            </ModalConfirmation>
        </span>
        <span v-for="file in attachments" :key="file.ulid" class="relative">
            <span class="flex h-12 w-12 flex-col items-center justify-center rounded border border-gray-200 bg-gray-50" :class="attachmentIconFor(file).class">
                <FontAwesomeIcon :icon="attachmentIconFor(file).icon" class="text-lg" fixed-width aria-hidden="true" />
                <span class="w-full truncate px-0.5 text-center text-[9px] text-gray-500">{{ file.name }}</span>
            </span>
            <ModalConfirmation v-if="file.ulid" :title="ctrans('Remove this attachment?')" :description="ctrans('It will be gone once you save. Cancelling the edit keeps it.')">
                <template #default="{ changeModel }">
                    <button v-tooltip="ctrans('Remove')" type="button" class="absolute -top-1.5 -right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-gray-500 text-white hover:bg-red-500" @click="changeModel">
                        <FontAwesomeIcon icon="fal fa-times" class="text-[9px]" fixed-width aria-hidden="true" />
                    </button>
                </template>
                <template #btn-yes="{ closeModal }">
                    <Button type="red" :label="ctrans('Remove')" @click="remove(file.ulid, closeModal)" />
                </template>
            </ModalConfirmation>
        </span>
    </div>
</template>
