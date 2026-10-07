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
        class="relative overflow-hidden max-w-[calc(100vw-2rem)] flex flex-col sm:flex-row"
        :style="getStyles(dialogData?.container_properties, screenType)"
        :class="{ 'website-dialog-editable': isEditable }"
        @click.self="isEditable && onClickField('container')"
    >
        <div
            class="sm:w-1/2 shrink-0 bg-gray-100 min-h-40"
            :class="{ 'website-dialog-editable': isEditable }"
            @click="isEditable && onClickField('image')"
        >
            <Image
                v-if="dialogData?.fields?.image?.source"
                :src="dialogData.fields.image.source"
                :alt="dialogData.fields.image.alt"
                class="h-full w-full"
                :imgAttributes="{ class: 'h-full w-full max-h-48 sm:max-h-none object-cover' }"
            />
        </div>

        <div class="flex flex-col justify-center gap-y-3 p-8 sm:w-1/2">
            <div
                v-if="dialogData?.fields?.title?.text"
                :class="{ 'website-dialog-editable': isEditable }"
                v-html="dialogData.fields.title.text"
                @click="isEditable && onClickField('title')"
            />

            <div
                v-if="dialogData?.fields?.description?.text"
                :class="{ 'website-dialog-editable': isEditable }"
                v-html="dialogData.fields.description.text"
                @click="isEditable && onClickField('description')"
            />

            <div v-if="dialogData?.fields?.button?.text">
                <a
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
    </div>
</template>
