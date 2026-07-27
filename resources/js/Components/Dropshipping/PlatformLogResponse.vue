<!--
  - Author: Artha <artha@aw-advantage.com>
  - Created: Mon, 27 Jul 2026 13:02:44 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCode, faCopy, faLightbulb } from "@fal"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useCopyText } from "@/Composables/useCopyText"

library.add(faCode, faCopy, faLightbulb)

defineProps<{
    message?: string | null
    hint?: string | null
    code?: string | null
    detail?: string | null
    status?: string
    platformName?: string
    itemCode?: string
}>()

const isDetailOpen = ref(false)
</script>

<template>
    <div class="max-w-lg">
        <template v-if="message || detail">
            <div class="flex items-start gap-x-2">
                <div
                    v-tooltip="message"
                    class="line-clamp-3 whitespace-pre-line"
                    :class="status === 'fail' ? 'text-red-600' : 'text-gray-700'">
                    {{ message ?? trans("The platform refused the product without saying why") }}
                </div>

                <FontAwesomeIcon
                    v-if="detail"
                    @click="isDetailOpen = true"
                    v-tooltip="trans('See the answer of the platform')"
                    :icon="faCode"
                    class="mt-0.5 cursor-pointer text-gray-400 hover:text-gray-600"
                    fixed-width
                    aria-hidden="true" />
            </div>

            <div v-if="hint" class="mt-1 flex items-start gap-x-1 text-xs italic text-gray-500">
                <FontAwesomeIcon :icon="faLightbulb" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
                <span>{{ hint }}</span>
            </div>
        </template>

        <div v-else class="text-gray-400">-</div>

        <Modal :isOpen="isDetailOpen" @onClose="isDetailOpen = false" width="w-full max-w-2xl" closeButton>
            <div class="text-base font-normal">
                <div class="font-semibold">
                    {{ trans("Answer of :platform", { platform: platformName || trans("the platform") }) }}
                    <span v-if="itemCode" class="text-gray-500">({{ itemCode }})</span>
                </div>

                <div v-if="message" class="mt-2 whitespace-pre-line text-red-600">{{ message }}</div>
                <div v-if="hint" class="mt-1 text-sm italic text-gray-500">{{ hint }}</div>
                <div v-if="code" class="mt-2 text-xs text-gray-500">
                    {{ trans("Error code") }}: <span class="font-mono">{{ code }}</span>
                </div>

                <pre
                    class="mt-3 max-h-96 overflow-auto rounded bg-gray-100 p-3 text-xs whitespace-pre-wrap break-words text-gray-700"
                >{{ detail }}</pre>

                <div class="mt-3 flex justify-end">
                    <Button
                        @click="() => useCopyText(detail ?? '')"
                        :label="trans('Copy')"
                        :icon="faCopy"
                        type="tertiary"
                        size="xs" />
                </div>
            </div>
        </Modal>
    </div>
</template>
