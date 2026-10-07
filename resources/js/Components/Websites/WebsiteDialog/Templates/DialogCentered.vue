<script setup lang="ts">
import { inject, ref } from "vue"
import { getStyles } from "@/Composables/styles"
import Image from "@common/Components/Image.vue"
import type { WebsiteDialogTemplateData } from "@/types/WebsiteDialog"

defineProps<{
    dialogData?: WebsiteDialogTemplateData
    isEditable?: boolean
}>()

const screenType = inject("screenType", ref("desktop"))
const openFieldWorkshop = inject("openFieldWorkshop", ref<string | number | null>(null))

const onClickField = (accordionKey: string) => {
    openFieldWorkshop.value = accordionKey
}
</script>

<template>
    <div
        class="relative overflow-hidden max-w-[calc(100vw-2rem)]"
        :style="getStyles(dialogData?.container_properties, screenType)"
        :class="{ 'website-dialog-editable': isEditable }"
        @click.self="isEditable && onClickField('container')"
    >
        <div
            v-if="dialogData?.fields?.image?.source"
            :class="{ 'website-dialog-editable': isEditable }"
            @click="isEditable && onClickField('image')"
        >
            <Image
                :src="dialogData.fields.image.source"
                :alt="dialogData.fields.image.alt"
                class="w-full"
                :imgAttributes="{ class: 'w-full max-h-72 object-cover' }"
            />
        </div>

        <div class="flex flex-col items-center gap-y-3 px-8 pt-8 pb-8 text-center">
            <div
                v-if="dialogData?.fields?.title?.text"
                class="w-full"
                :class="{ 'website-dialog-editable': isEditable }"
                v-html="dialogData.fields.title.text"
                @click="isEditable && onClickField('title')"
            />

            <div
                v-if="dialogData?.fields?.description?.text"
                class="w-full"
                :class="{ 'website-dialog-editable': isEditable }"
                v-html="dialogData.fields.description.text"
                @click="isEditable && onClickField('description')"
            />

            <a
                v-if="dialogData?.fields?.button?.text"
                :href="isEditable ? undefined : (dialogData.fields.button.link?.href || '#')"
                :target="dialogData.fields.button.link?.target"
                class="mt-2 inline-flex items-center justify-center"
                :class="{ 'website-dialog-editable': isEditable }"
                :style="getStyles(dialogData.fields.button.container?.properties, screenType)"
                data-website-dialog-action
                @click="isEditable && onClickField('button')"
                v-html="dialogData.fields.button.text"
            />
        </div>
    </div>
</template>
