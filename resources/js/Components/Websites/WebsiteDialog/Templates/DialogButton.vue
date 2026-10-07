<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import type { WebsiteDialogTemplateData } from "@/types/WebsiteDialog"

defineProps<{
    button?: WebsiteDialogTemplateData["fields"]["button"]
    screenType: string
    isEditable?: boolean
}>()
</script>

<template>
    <a
        v-if="button?.text"
        :href="isEditable ? undefined : (button.link?.href || '#')"
        :target="button.link?.target"
        class="inline-flex items-center justify-center font-semibold shadow-sm transition-transform duration-150 hover:-translate-y-0.5 hover:shadow-md"
        :style="getStyles(button.container?.properties, screenType)"
        data-website-dialog-action
        v-html="button.text"
    />
</template>
