<script setup lang="ts">
/*
 * A received email, shown the way it was designed. Staff read the same message in Gmail and
 * judge this screen against it, so a column of stripped text reads as the system being broken.
 *
 * It is rendered inside a sandboxed frame, which is the second barrier: the html was already
 * purified when it was stored. The sandbox carries no allow-scripts, so nothing in the message
 * can execute whatever got past the purifier, and allow-same-origin only exists so the height
 * can be measured. Without scripts that grants the message no reach of its own.
 */
import { ref, computed, onBeforeUnmount, watch } from "vue"

const props = defineProps<{ html: string }>()

const frame = ref<HTMLIFrameElement | null>(null)
const height = ref(120)

// A page of its own, so the message cannot inherit or fight the application's stylesheet.
const document = computed(() => `<!doctype html><html><head><meta charset="utf-8">
<base target="_blank" rel="noreferrer noopener">
<style>
  html,body{margin:0;padding:0;background:#fff;}
  body{font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:14px;color:#1f2937;word-break:break-word;}
  img{max-width:100%;height:auto;}
  table{max-width:100%;}
</style></head><body>${props.html}</body></html>`)

const measure = () => {
    const body = frame.value?.contentDocument?.body

    if (body) {
        height.value = Math.min(Math.max(body.scrollHeight + 8, 60), 1600)
    }
}

// Images arrive after the frame reports it has loaded, and each one changes the height.
let observer: ResizeObserver | null = null

const onLoad = () => {
    measure()

    const body = frame.value?.contentDocument?.body

    if (body && typeof ResizeObserver !== "undefined") {
        observer?.disconnect()
        observer = new ResizeObserver(measure)
        observer.observe(body)
    }
}

watch(() => props.html, () => {
    height.value = 120
})

onBeforeUnmount(() => observer?.disconnect())
</script>

<template>
    <iframe ref="frame" :srcdoc="document" sandbox="allow-same-origin allow-popups"
        class="w-full border-0 bg-white rounded" :style="{ height: `${height}px` }"
        referrerpolicy="no-referrer" loading="lazy" @load="onLoad" />
</template>
