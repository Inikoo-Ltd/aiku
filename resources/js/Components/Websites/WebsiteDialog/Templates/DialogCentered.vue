<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import Image from "@common/Components/Image.vue"
import DialogButton from "@/Components/Websites/WebsiteDialog/Templates/DialogButton.vue"
import DialogText from "@/Components/Websites/WebsiteDialog/Templates/DialogText.vue"
import { useDialogTemplate } from "@/Components/Websites/WebsiteDialog/Templates/useDialogTemplate"
import type { WebsiteDialogTemplateData } from "@/types/WebsiteDialog"

const props = defineProps<{
    dialogData?: WebsiteDialogTemplateData
    isEditable?: boolean
}>()

const { screenType, fields, accent, accentSoft, editable } = useDialogTemplate(props)
</script>

<template>
    <div
        class="relative max-w-[calc(100vw-2rem)] overflow-hidden shadow-2xl"
        :style="getStyles(dialogData?.container_properties, screenType)"
        v-bind="editable('container')"
    >
        <div class="h-1.5 w-full" :style="{ background: accent }" />

        <div v-if="fields.image?.source" class="px-6 pt-6" v-bind="editable('image')">
            <Image
                :src="fields.image.source"
                :alt="fields.image.alt"
                class="w-full"
                :imgAttributes="{ class: 'aspect-[16/9] w-full rounded-xl object-cover' }"
            />
        </div>

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

            <div v-if="fields.button?.text" class="mt-3" v-bind="editable('button')">
                <DialogButton :button="fields.button" :screenType="screenType" :isEditable="isEditable" />
            </div>

            <DialogText fieldKey="note" :dialogData="dialogData" :isEditable="isEditable" class="mt-1 w-full text-xs opacity-60" />
        </div>
    </div>
</template>
