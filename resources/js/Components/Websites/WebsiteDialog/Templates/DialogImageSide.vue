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

const { screenType, fields, accent, accentSoft, accentGradient, editable } = useDialogTemplate(props)
</script>

<template>
    <div
        class="relative grid max-w-[calc(100vw-2rem)] grid-cols-1 overflow-hidden shadow-2xl sm:grid-cols-5"
        :style="getStyles(dialogData?.container_properties, screenType)"
        v-bind="editable('container')"
    >
        <div class="relative min-h-44 overflow-hidden sm:col-span-2 sm:min-h-[360px]" :style="{ background: accentGradient }" v-bind="editable('image')">
            <Image
                v-if="fields.image?.source"
                :src="fields.image.source"
                :alt="fields.image.alt"
                class="absolute inset-0 h-full w-full"
                :imgAttributes="{ class: 'h-full w-full object-cover' }"
            />
            <template v-else>
                <div class="absolute -left-10 -top-10 h-40 w-40 rounded-full bg-white/20" />
                <div class="absolute -bottom-12 -right-6 h-48 w-48 rounded-full bg-white/15" />
                <div class="absolute bottom-10 left-8 h-16 w-16 rounded-full bg-white/25" />
            </template>
        </div>

        <div class="flex flex-col justify-center gap-y-3 p-8 sm:col-span-3 sm:p-10">
            <div
                v-if="hasText(fields.eyebrow?.text) || isEditable"
                class="w-fit rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wider"
                :style="{ background: accentSoft, color: accent }"
                v-bind="editable('eyebrow')"
                v-html="fields.eyebrow?.text || '&nbsp;'"
            />

            <div v-if="hasText(fields.title?.text)" class="text-2xl leading-tight sm:text-3xl" v-bind="editable('title')" v-html="fields.title?.text" />

            <div v-if="hasText(fields.description?.text)" class="text-base leading-relaxed opacity-80" v-bind="editable('description')" v-html="fields.description?.text" />

            <div v-if="fields.button?.text" class="mt-3" v-bind="editable('button')">
                <DialogButton :button="fields.button" :screenType="screenType" :isEditable="isEditable" />
            </div>

            <div v-if="hasText(fields.note?.text)" class="mt-1 text-xs opacity-60" v-bind="editable('note')" v-html="fields.note?.text" />
        </div>
    </div>
</template>
