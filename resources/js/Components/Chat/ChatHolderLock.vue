<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 24 Nov 2026 15:20:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faHeadset as fasHeadset, faLock as fasLock } from "@fas"
import { faTimes, faUnlock } from "@fal"
import { ctrans } from "@/Composables/useTrans"

/**
 * HELP-4896: who holds the conversation, in the header. A colleague's lock opens a confirmation to
 * take it over, for when they are away or on holiday and the customer is still waiting. Your own
 * lock gives it back to the queue in one click, no questions: it only undoes your own take.
 */
const props = defineProps<{
    name: string | null | undefined
    isMine: boolean
    canTakeOver: boolean
    canRelease?: boolean
    isTakingOver?: boolean
    isReleasing?: boolean
}>()

const emit = defineEmits<{ "take-over": [], release: [] }>()

const isConfirming = ref(false)

watch(() => props.isMine, (isMine) => {
    if (isMine) isConfirming.value = false
})
</script>

<template>
    <button v-if="isMine && canRelease" type="button" :disabled="isReleasing"
        class="inline-flex shrink-0 items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-800 ring-1 ring-inset ring-green-300 hover:bg-green-200 disabled:opacity-60"
        v-tooltip="ctrans('Yours. Click to give it back to the queue for a colleague to answer.')"
        @click.stop="emit('release')">
        <FontAwesomeIcon :icon="fasHeadset" class="text-[10px]" fixed-width aria-hidden="true" />
        {{ ctrans("You") }}
        <FontAwesomeIcon :icon="fasLock" class="text-[9px]" fixed-width aria-hidden="true" />
    </button>
    <span v-else-if="isMine" class="inline-flex shrink-0 items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-800 ring-1 ring-inset ring-green-300"
        v-tooltip="ctrans('You have this conversation. Colleagues see it is yours and need Take over to answer it.')">
        <FontAwesomeIcon :icon="fasHeadset" class="text-[10px]" fixed-width aria-hidden="true" />
        {{ ctrans("You") }}
        <FontAwesomeIcon :icon="fasLock" class="text-[9px]" fixed-width aria-hidden="true" />
    </span>
    <span v-else-if="name && !canTakeOver" class="inline-flex shrink-0 items-center gap-1 rounded-full bg-yellow-300 px-2 py-0.5 text-[11px] font-semibold text-gray-900"
        v-tooltip="ctrans(':agent has this conversation', { agent: name })">
        <FontAwesomeIcon :icon="fasHeadset" class="text-[10px]" fixed-width aria-hidden="true" />
        {{ name }}
        <FontAwesomeIcon :icon="fasLock" class="text-[9px]" fixed-width aria-hidden="true" />
    </span>
    <button v-else-if="name" type="button"
        class="inline-flex shrink-0 items-center gap-1 rounded-full bg-yellow-300 px-2 py-0.5 text-[11px] font-semibold text-gray-900 hover:bg-yellow-400"
        v-tooltip="ctrans(':agent has this conversation. Click to take it over.', { agent: name })"
        @click.stop="isConfirming = true">
        <FontAwesomeIcon :icon="fasHeadset" class="text-[10px]" fixed-width aria-hidden="true" />
        {{ name }}
        <FontAwesomeIcon :icon="fasLock" class="text-[9px]" fixed-width aria-hidden="true" />
    </button>

    <Dialog v-model:visible="isConfirming" modal :header="ctrans('Take over from :agent?', { agent: name ?? '' })"
        :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <p class="text-sm text-gray-700">
            {{ ctrans(":agent has this conversation. Take it over when they are away or cannot answer it, and check with them first if they are working today.", { agent: name ?? '' }) }}
        </p>
        <p class="mt-2 text-sm text-gray-700">
            {{ ctrans("It becomes yours: you can answer, end, ignore or spam it, and :agent can no longer write in it without taking it back.", { agent: name ?? '' }) }}
        </p>
        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isConfirming = false">
                    <template #icon><FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="primary" full :label="ctrans('Take over')" :loading="isTakingOver" @click="emit('take-over')">
                    <template #icon><FontAwesomeIcon :icon="faUnlock" fixed-width aria-hidden="true" /></template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
