<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { marked } from "marked"
import Image from "@/Common/Components/Image.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPaperclip } from "@fal"

library.add(faPaperclip)

const props = defineProps<{
    text: string | null
    images?: Record<string, string>[]
    attachments?: { name: string; url: string }[]
}>()

const escapeHtml = (text: string) => text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")

const html = computed(() => {
    const rendered = marked.parse(escapeHtml(props.text ?? ""), { breaks: true, gfm: true, async: false }) as string
    return rendered.replace(/<a /g, '<a target="_blank" rel="noopener" ')
})
</script>

<template>
    <div>
        <div v-if="text" class="ticket-body text-sm break-words" v-html="html" />
        <div v-if="images?.length" class="mt-2 flex flex-wrap gap-2">
            <a v-for="(image, index) in images" :key="index" :href="image.original" target="_blank" rel="noopener" class="block">
                <Image :src="image" alt="" image-cover class="h-32 w-32 rounded border border-gray-200 hover:opacity-90" />
            </a>
        </div>
        <ul v-if="attachments?.length" class="mt-2 space-y-1 text-sm">
            <li v-for="file in attachments" :key="file.url">
                <a :href="file.url" target="_blank" rel="noopener" class="text-indigo-600 hover:underline break-all"><FontAwesomeIcon icon="fal fa-paperclip" class="mr-1" />{{ file.name }}</a>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.ticket-body :deep(p) { margin: 0 0 0.75em; }
.ticket-body :deep(p:last-child) { margin-bottom: 0; }
.ticket-body :deep(a) { color: rgb(79 70 229); text-decoration: underline; word-break: break-all; }
.ticket-body :deep(ul) { list-style: disc; padding-left: 1.5em; margin: 0 0 0.75em; }
.ticket-body :deep(ol) { list-style: decimal; padding-left: 1.5em; margin: 0 0 0.75em; }
.ticket-body :deep(h1), .ticket-body :deep(h2), .ticket-body :deep(h3) { font-weight: 600; margin: 0.5em 0; }
.ticket-body :deep(blockquote) { border-left: 3px solid rgb(209 213 219); padding-left: 0.75em; color: rgb(75 85 99); margin: 0 0 0.75em; }
.ticket-body :deep(pre) { background: rgb(243 244 246); padding: 0.5em; border-radius: 0.25em; overflow-x: auto; margin: 0 0 0.75em; }
.ticket-body :deep(code) { font-family: ui-monospace, monospace; font-size: 0.9em; }
.ticket-body :deep(table) { border-collapse: collapse; margin: 0 0 0.75em; }
.ticket-body :deep(td), .ticket-body :deep(th) { border: 1px solid rgb(209 213 219); padding: 0.25em 0.5em; }
</style>
