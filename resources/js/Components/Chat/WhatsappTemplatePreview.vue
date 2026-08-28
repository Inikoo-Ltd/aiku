<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import {
    faImage,
    faVideo,
    faFilePdf,
    faPhone,
    faReply,
    faArrowUpRightFromSquare,
} from "@fortawesome/free-solid-svg-icons"
import { trans } from "laravel-vue-i18n"

const props = withDefaults(
    defineProps<{
        body?: string | null
        header?: { format?: string; text?: string | null } | null
        footer?: string | null
        buttons?: { type?: string; text?: string }[]
        businessName?: string
        mergeTags?: { value: string }[]
        mediaPreview?: string | null
        placeholder?: string
    }>(),
    {
        body: "",
        header: null,
        footer: "",
        buttons: () => [],
        businessName: "",
        mergeTags: () => [],
        mediaPreview: null,
        placeholder: "",
    }
)

type PreviewSegment = { kind: "text" | "tag"; value: string }

/**
 * The preview keeps merge tags visible as chips rather than swapping in a sample value:
 * the agent is checking the wording, and a real name in there would hide where the
 * variable actually sits.
 */
const toSegments = (text: string): PreviewSegment[] => {
    const names = props.mergeTags.map((tag) => tag.value.slice(1, -1))
    const segments: PreviewSegment[] = []
    const pattern = /\[([^[\]]+)\]/g

    let cursor = 0
    let match: RegExpExecArray | null

    while ((match = pattern.exec(text)) !== null) {
        if (!names.includes(match[1])) continue

        if (match.index > cursor) {
            segments.push({ kind: "text", value: text.slice(cursor, match.index) })
        }

        segments.push({ kind: "tag", value: match[1] })
        cursor = match.index + match[0].length
    }

    if (cursor < text.length) {
        segments.push({ kind: "text", value: text.slice(cursor) })
    }

    return segments
}

const headerFormat = computed(() => props.header?.format ?? "NONE")

const previewBodySegments = computed(() =>
    toSegments(props.body || props.placeholder || trans("Your message will appear here…"))
)

const previewHeaderSegments = computed(() =>
    headerFormat.value === "TEXT" ? toSegments(props.header?.text ?? "") : []
)

const buttonIcon = (type?: string) => {
    if (type === "PHONE_NUMBER") return faPhone
    if (type === "URL" || type === "URL_DYNAMIC") return faArrowUpRightFromSquare

    return faReply
}

const now = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })

// WhatsApp's chat wallpaper, drawn inline rather than shipped as an asset: it is a few
// faint strokes, and an SVG data URI keeps the preview self-contained.
const doodleStyle = computed(() => {
    const doodle = `<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">
        <g fill="none" stroke="#D9CFC4" stroke-width="1.4" stroke-linecap="round" opacity="0.55">
            <circle cx="18" cy="22" r="7"/>
            <path d="M44 14h16v11H52l-4 5v-5h-4z"/>
            <path d="M78 18c4-5 11-5 14 0"/>
            <path d="M100 40v12M94 46h12"/>
            <circle cx="30" cy="62" r="5"/>
            <path d="M54 58l7 7-7 7-7-7z"/>
            <path d="M84 66h18M84 72h12"/>
            <path d="M14 96c5-6 13-6 18 0"/>
            <circle cx="62" cy="100" r="6"/>
            <path d="M92 94h14v10H98l-3 4v-4h-3z"/>
        </g>
    </svg>`

    return {
        backgroundImage: `url("data:image/svg+xml,${encodeURIComponent(doodle)}")`,
        backgroundSize: "120px 120px",
    }
})
</script>

<template>
    <div class="rounded-2xl overflow-hidden border border-gray-200 shadow-sm">
        <div class="flex items-center gap-2 bg-[#075E54] px-4 py-2.5 text-white">
            <FontAwesomeIcon :icon="faWhatsapp" class="text-sm" />
            <span class="text-sm font-semibold">{{ trans("Preview") }}</span>
            <span class="ml-auto text-[11px] opacity-70 truncate max-w-[130px]">{{ businessName }}</span>
        </div>

        <div class="px-3.5 py-6 bg-[#ECE5DD]" :style="doodleStyle">
            <div class="relative max-w-[90%]">
                <!-- the little tail that makes it read as a WhatsApp bubble -->
                <span class="absolute -left-1.5 top-0 w-3 h-3 bg-white"
                    style="clip-path: polygon(100% 0, 100% 100%, 0 0)"></span>

                <div class="relative rounded-lg rounded-tl-none bg-white shadow-[0_1px_1px_rgba(0,0,0,0.12)] overflow-hidden">
                    <div class="p-1.5 pb-0" v-if="headerFormat !== 'NONE' && headerFormat !== 'TEXT'">
                        <div v-if="headerFormat === 'IMAGE'"
                            class="h-36 rounded-md bg-gray-100 flex items-center justify-center overflow-hidden">
                            <img v-if="mediaPreview" :src="mediaPreview" class="h-full w-full object-cover" />
                            <FontAwesomeIcon v-else :icon="faImage" class="text-gray-300 text-3xl" />
                        </div>
                        <div v-else-if="headerFormat === 'VIDEO'"
                            class="h-36 rounded-md bg-gray-100 flex items-center justify-center">
                            <FontAwesomeIcon :icon="faVideo" class="text-gray-300 text-3xl" />
                        </div>
                        <div v-else
                            class="h-20 rounded-md bg-gray-100 flex items-center justify-center gap-2 text-gray-400">
                            <FontAwesomeIcon :icon="faFilePdf" class="text-2xl" />
                            <span class="text-[11px]">{{ trans("PDF") }}</span>
                        </div>
                    </div>

                    <div class="px-2.5 py-2 space-y-1">
                        <div v-if="previewHeaderSegments.length"
                            class="text-[14px] font-semibold text-[#111B21] leading-snug break-words">
                            <template v-for="(segment, index) in previewHeaderSegments" :key="index">
                                <span v-if="segment.kind === 'text'">{{ segment.value }}</span>
                                <span v-else
                                    class="inline-block rounded-full bg-[#E7F3FF] text-[#0091EA] font-normal text-[12px] px-2 py-0.5 mx-0.5 align-middle">
                                    {{ segment.value }}
                                </span>
                            </template>
                        </div>

                        <div class="text-[14px] leading-[19px] whitespace-pre-wrap break-words"
                            :class="body ? 'text-[#111B21]' : 'text-gray-400 italic'">
                            <template v-for="(segment, index) in previewBodySegments" :key="index">
                                <span v-if="segment.kind === 'text'">{{ segment.value }}</span>
                                <span v-else
                                    class="inline-block rounded-full bg-[#E7F3FF] text-[#0091EA] text-[12px] px-2 py-0.5 mx-0.5 align-middle">
                                    {{ segment.value }}
                                </span>
                            </template>
                        </div>

                        <div v-if="footer" class="text-[12px] text-[#667781] break-words pt-0.5">
                            {{ footer }}
                        </div>

                        <div class="flex justify-end -mb-0.5">
                            <span class="text-[10px] text-[#667781]">{{ now }}</span>
                        </div>
                    </div>

                    <div v-if="buttons.length" class="border-t border-[#E9EDEF]">
                        <div v-for="(button, index) in buttons" :key="index"
                            class="flex items-center justify-center gap-1.5 border-b border-[#E9EDEF] last:border-b-0 py-2.5 text-[14px] text-[#0091EA] font-medium">
                            <FontAwesomeIcon :icon="buttonIcon(button.type)" class="text-[11px]" />
                            {{ button.text || trans("Button") }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
