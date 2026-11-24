<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"

export type SlackNode = { type: string; [key: string]: any }

defineProps<{
    nodes: SlackNode[]
    channelLinks?: Record<string, number>
}>()

const isHexColour = (colour: unknown) => typeof colour === "string" && /^#[0-9a-f]{3,8}$/i.test(colour)
</script>

<template>
    <template v-for="(node, index) in nodes" :key="index">
        <p v-if="node.type === 'paragraph'" class="whitespace-pre-wrap break-words [&:not(:first-child)]:mt-1">
            <SlackNodes :nodes="node.children" :channel-links="channelLinks" />
        </p>
        <pre v-else-if="node.type === 'pre'" class="my-1 overflow-x-auto whitespace-pre-wrap break-words rounded border border-gray-200 bg-gray-50 px-2 py-1.5 font-mono text-xs text-gray-800">{{ node.text }}</pre>
        <blockquote v-else-if="node.type === 'quote'" class="my-1 border-l-4 border-gray-300 pl-2 text-gray-700">
            <SlackNodes :nodes="node.children" :channel-links="channelLinks" />
        </blockquote>
        <component :is="node.ordered ? 'ol' : 'ul'" v-else-if="node.type === 'list'" :start="node.ordered ? node.start : undefined" class="my-1 pl-5" :class="node.ordered ? 'list-decimal' : 'list-disc'">
            <li v-for="(item, itemIndex) in node.items" :key="itemIndex" class="break-words">
                <SlackNodes :nodes="item" :channel-links="channelLinks" />
            </li>
        </component>
        <p v-else-if="node.type === 'header'" class="mt-1 font-semibold text-gray-900">
            <SlackNodes :nodes="node.children" :channel-links="channelLinks" />
        </p>
        <div v-else-if="node.type === 'fields'" class="my-1 grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
            <div v-for="(item, itemIndex) in node.items" :key="itemIndex" class="min-w-0">
                <SlackNodes :nodes="item" :channel-links="channelLinks" />
            </div>
        </div>
        <p v-else-if="node.type === 'context'" class="mt-1 text-xs text-gray-500">
            <SlackNodes :nodes="node.children" :channel-links="channelLinks" />
        </p>
        <div v-else-if="node.type === 'buttons'" class="my-1 flex flex-wrap gap-1.5">
            <span v-for="(label, labelIndex) in node.labels" :key="labelIndex" class="cursor-default rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-500">{{ label }}</span>
        </div>
        <hr v-else-if="node.type === 'divider'" class="my-2 border-gray-200" />
        <div v-else-if="node.type === 'attachment'" class="my-1 border-l-4 pl-2" :class="isHexColour(node.color) ? '' : 'border-gray-300'" :style="isHexColour(node.color) ? { borderColor: node.color } : undefined">
            <div v-if="node.author" class="text-xs font-medium text-gray-600">{{ node.author }}</div>
            <div v-if="node.title" class="font-semibold text-gray-900">
                <a v-if="node.title_url" :href="node.title_url" target="_blank" rel="noopener noreferrer nofollow" class="hover:underline">{{ node.title }}</a>
                <template v-else>{{ node.title }}</template>
            </div>
            <SlackNodes :nodes="node.blocks" :channel-links="channelLinks" />
            <div v-if="node.footer" class="mt-0.5 text-xs text-gray-500">
                <SlackNodes :nodes="node.footer" :channel-links="channelLinks" />
            </div>
        </div>

        <template v-else-if="node.type === 'text'">{{ node.text }}</template>
        <br v-else-if="node.type === 'br'" />
        <strong v-else-if="node.type === 'bold'" class="font-semibold"><SlackNodes :nodes="node.children" :channel-links="channelLinks" /></strong>
        <em v-else-if="node.type === 'italic'"><SlackNodes :nodes="node.children" :channel-links="channelLinks" /></em>
        <s v-else-if="node.type === 'strike'"><SlackNodes :nodes="node.children" :channel-links="channelLinks" /></s>
        <code v-else-if="node.type === 'code'" class="rounded border border-gray-200 bg-gray-50 px-1 font-mono text-[0.85em] text-red-700">{{ node.text }}</code>
        <a v-else-if="node.type === 'link'" :href="node.url" target="_blank" rel="noopener noreferrer nofollow" class="break-all text-[#1264a3] hover:underline">{{ node.text }}</a>
        <span v-else-if="node.type === 'user'" class="rounded bg-[#1d9bd11a] px-0.5 text-[#1264a3]">@{{ node.name ?? ctrans("unknown") }}</span>
        <Link
            v-else-if="node.type === 'channel' && channelLinks?.[node.id]"
            :href="route('grp.chat.staff.slack.show', channelLinks[node.id])"
            preserve-state
            class="rounded bg-[#1d9bd11a] px-0.5 text-[#1264a3] hover:underline">#{{ node.name ?? ctrans("unknown") }}</Link>
        <span v-else-if="node.type === 'channel'" class="rounded bg-gray-100 px-0.5 font-medium text-gray-600">#{{ node.name ?? ctrans("unknown") }}</span>
        <span v-else-if="node.type === 'group'" class="rounded bg-gray-100 px-0.5 font-medium text-gray-700">{{ node.label ?? "@" + ctrans("group") }}</span>
        <span v-else-if="node.type === 'broadcast'" class="rounded bg-amber-100 px-0.5 font-medium text-amber-800">@{{ node.range }}</span>
        <template v-else-if="node.type === 'emoji'">
            <span v-if="node.unicode" :title="':' + node.name + ':'">{{ node.unicode }}</span>
            <img v-else-if="node.url" :src="node.url" :alt="':' + node.name + ':'" :title="':' + node.name + ':'" class="inline-block h-5 w-5 align-text-bottom object-contain" loading="lazy" />
            <template v-else>:{{ node.name }}:</template>
        </template>
        <SlackNodes v-else-if="Array.isArray(node.children)" :nodes="node.children" :channel-links="channelLinks" />
    </template>
</template>
