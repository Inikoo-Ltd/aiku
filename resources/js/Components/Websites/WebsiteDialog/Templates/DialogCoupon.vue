<script setup lang="ts">
import { ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCopy, faCheck } from "@fal"
import { getStyles } from "@/Composables/styles"
import { ctrans } from "@/Composables/useTrans"
import DialogButton from "@/Components/Websites/WebsiteDialog/Templates/DialogButton.vue"
import DialogText from "@/Components/Websites/WebsiteDialog/Templates/DialogText.vue"
import { useDialogTemplate } from "@/Components/Websites/WebsiteDialog/Templates/useDialogTemplate"
import type { WebsiteDialogTemplateData } from "@/types/WebsiteDialog"

library.add(faCopy, faCheck)

const props = defineProps<{
    dialogData?: WebsiteDialogTemplateData
    isEditable?: boolean
}>()

const { screenType, fields, accent, accentSoft, editable } = useDialogTemplate(props)

const isCopied = ref(false)

const copyCode = async () => {
    const code = fields.value.coupon?.code

    if (props.isEditable || !code) {
        return
    }

    try {
        await navigator.clipboard.writeText(code)
        isCopied.value = true
        setTimeout(() => isCopied.value = false, 2000)
    } catch {
        isCopied.value = false
    }
}
</script>

<template>
    <div
        class="relative max-w-[calc(100vw-2rem)] overflow-hidden shadow-2xl"
        :style="getStyles(dialogData?.container_properties, screenType)"
        v-bind="editable('container')"
    >
        <div class="h-1.5 w-full" :style="{ background: accent }" />

        <div class="flex flex-col items-center gap-y-3 px-8 pb-9 pt-8 text-center sm:px-10">
            <DialogText
                fieldKey="eyebrow"
                :dialogData="dialogData"
                :isEditable="isEditable"
                class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wider"
                :style="{ background: accentSoft, color: accent }"
            />

            <DialogText fieldKey="title" :dialogData="dialogData" :isEditable="isEditable" class="w-full text-2xl leading-tight sm:text-3xl" />

            <DialogText fieldKey="description" :dialogData="dialogData" :isEditable="isEditable" class="w-full text-base leading-relaxed opacity-80" />

            <div
                v-if="fields.coupon?.code || isEditable"
                class="mt-2 w-full rounded-xl border-2 border-dashed px-4 py-3"
                :style="{ borderColor: accent, background: accentSoft }"
                v-bind="editable('coupon')"
            >
                <div v-if="fields.coupon?.label" class="text-[11px] font-semibold uppercase tracking-widest opacity-70">
                    {{ fields.coupon.label }}
                </div>
                <div class="mt-1 flex flex-wrap items-center justify-center gap-x-3 gap-y-2">
                    <span class="break-all font-mono text-xl font-bold tracking-[0.12em] sm:text-2xl sm:tracking-[0.2em]" :style="{ color: accent }">
                        {{ fields.coupon?.code || 'CODE' }}
                    </span>
                    <button
                        type="button"
                        class="flex items-center gap-x-1 rounded-md bg-white px-2 py-1 text-xs font-medium shadow-sm ring-1 ring-black/10 hover:bg-gray-50"
                        :style="{ color: accent }"
                        :aria-label="ctrans('Copy code')"
                        @click="copyCode"
                    >
                        <FontAwesomeIcon :icon="isCopied ? 'fal fa-check' : 'fal fa-copy'" fixed-width aria-hidden="true" />
                        {{ isCopied ? ctrans("Copied") : ctrans("Copy") }}
                    </button>
                </div>
            </div>

            <div v-if="fields.button?.text" class="mt-3" v-bind="editable('button')">
                <DialogButton :button="fields.button" :screenType="screenType" :isEditable="isEditable" />
            </div>

            <DialogText fieldKey="note" :dialogData="dialogData" :isEditable="isEditable" class="mt-1 w-full text-xs opacity-60" />
        </div>
    </div>
</template>
