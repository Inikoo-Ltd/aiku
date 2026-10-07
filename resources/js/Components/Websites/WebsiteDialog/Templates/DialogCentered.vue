<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import Image from "@common/Components/Image.vue"
import DialogButton from "@/Components/Websites/WebsiteDialog/Templates/DialogButton.vue"
import { hasText, useDialogTemplate } from "@/Components/Websites/WebsiteDialog/Templates/useDialogTemplate"
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
            <div
                v-if="hasText(fields.eyebrow?.text) || isEditable"
                class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wider"
                :style="{ background: accentSoft, color: accent }"
                v-bind="editable('eyebrow')"
                v-html="fields.eyebrow?.text || '&nbsp;'"
            />

            <div v-if="hasText(fields.title?.text)" class="w-full text-2xl leading-tight sm:text-3xl" v-bind="editable('title')" v-html="fields.title?.text" />

            <div v-if="hasText(fields.description?.text)" class="w-full text-base leading-relaxed opacity-80" v-bind="editable('description')" v-html="fields.description?.text" />

            <div v-if="fields.button?.text" class="mt-3" v-bind="editable('button')">
                <DialogButton :button="fields.button" :screenType="screenType" :isEditable="isEditable" />
            </div>

            <div v-if="hasText(fields.note?.text)" class="mt-1 w-full text-xs opacity-60" v-bind="editable('note')" v-html="fields.note?.text" />
        </div>
    </div>
</template>
