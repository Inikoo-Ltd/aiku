<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 28 Nov 2026 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUserShield } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Dialog from "primevue/dialog"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import { ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

library.add(faUserShield)

const props = defineProps<{
    conversationUlid: string
    helperName: string
    buttonClass?: string
}>()

const isOpen = ref(false)
const minutes = ref<"15" | "30" | "60">("15")
const isSaving = ref(false)
const error = ref<string | null>(null)

const minuteOptions = [
    { label: ctrans(":minutes min", { minutes: 15 }), value: "15" as const },
    { label: ctrans(":minutes min", { minutes: 30 }), value: "30" as const },
    { label: ctrans("1 hour"), value: "60" as const },
]

const grant = async () => {
    isSaving.value = true
    error.value = null
    try {
        await axios.post(route("grp.chat.staff.conversations.help.store", props.conversationUlid), { minutes: Number(minutes.value) })
        isOpen.value = false
        router.reload({ only: ["auth"] })
    } catch {
        error.value = ctrans("Help could not be given. Reload the page and try again.")
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <div class="contents">
    <button
        type="button"
        v-tooltip="ctrans('Let :name help me', { name: helperName })"
        :aria-label="ctrans('Let :name help me', { name: helperName })"
        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md transition duration-200"
        :class="buttonClass"
        @click="isOpen = true">
        <FontAwesomeIcon icon="fal fa-user-shield" fixed-width aria-hidden="true" />
    </button>

    <Dialog v-model:visible="isOpen" modal :header="ctrans('Let :name help me', { name: helperName })" class="w-[26rem] max-w-[92vw]">
        <div class="flex flex-col gap-3 text-sm text-gray-700">
            <p>{{ ctrans(":name can use aiku with all your access, as if you handed over your keyboard. Your password and your private chats stay yours.", { name: helperName }) }}</p>
            <p class="text-gray-500">{{ ctrans("Every change they make is recorded under both names. You can end it at any time.") }}</p>
            <SegmentedToggle v-model="minutes" :options="minuteOptions" :aria-label="ctrans('For how long')" />
            <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
            <div class="flex justify-end gap-2">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="isOpen = false" />
                <Button type="save" :label="ctrans('Let them help')" :loading="isSaving" :disabled="isSaving" @click="grant" />
            </div>
        </div>
    </Dialog>
    </div>
</template>
