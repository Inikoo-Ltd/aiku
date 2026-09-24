<!--
 Author Louis Perez
 Created on 21-09-2026-10h-57m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInfoCircle, faExclamationTriangle, faCheckCircle, faCopy, faSpinner } from "@fal"

library.add(faInfoCircle, faExclamationTriangle, faCheckCircle, faCopy, faSpinner)

const props = defineProps<{
    isOpen: boolean
    items: any[]
    lookupRoute?: { name: string; parameters?: Record<string, unknown> } | null
}>()

const emits = defineEmits<{
    (e: "close"): void
    (e: "apply", items: any[]): void
}>()

const pastedText = ref("")
const unlistedMode = ref<"after" | "before" | "drop">("after")
const fetchedByCode = ref<Record<string, any>>({})
const isLooking = ref(false)
const lookupFailed = ref(false)
let lookupTimer: ReturnType<typeof setTimeout> | null = null

watch(() => props.isOpen, (isOpen) => {
    if (isOpen) {
        pastedText.value = ""
        unlistedMode.value = "after"
        fetchedByCode.value = {}
        lookupFailed.value = false
    }
})

const codeOf = (item: any) => String(item?.code ?? "").trim().toLowerCase()

const parsedCodes = computed(() =>
    pastedText.value
        .split(/\r?\n/)
        .map((line) => line.replace(/^[\s]*(?:\d+\s*[.)\-:]|[-•*])\s*/, "").trim())
        .filter((line) => line !== "")
        .map((line) => line.split(/[\s,;|\t]+/)[0].trim())
        .filter((code) => code !== "")
)

const lookupMissingCodes = async (codes: string[]) => {
    if (!props.lookupRoute?.name || !codes.length) return
    isLooking.value = true
    lookupFailed.value = false
    try {
        const { data } = await axios.get(route(props.lookupRoute.name, { ...(props.lookupRoute.parameters ?? {}), codes: codes.join(",") }))
        const rows = data?.data ?? data ?? []
        const found = { ...fetchedByCode.value }
        rows.forEach((row: any) => (found[codeOf(row)] = row))
        fetchedByCode.value = found
    } catch {
        lookupFailed.value = true
    } finally {
        isLooking.value = false
    }
}

watch(() => pastedText.value, () => {
    if (lookupTimer) clearTimeout(lookupTimer)
    lookupTimer = setTimeout(() => {
        const known = new Set(props.items.map((item) => codeOf(item)))
        const missing = parsedCodes.value
            .map((code) => code.toLowerCase())
            .filter((code) => !known.has(code) && !(code in fetchedByCode.value))
        lookupMissingCodes(missing)
    }, 400)
})

const preview = computed(() => {
    const byCode = new Map<string, any>()
    props.items.forEach((item) => byCode.set(codeOf(item), item))
    Object.entries(fetchedByCode.value).forEach(([code, row]) => {
        if (!byCode.has(code)) byCode.set(code, row)
    })

    const ordered: any[] = []
    const used = new Set<string>()
    const unmatched: string[] = []
    const duplicates: string[] = []

    parsedCodes.value.forEach((code) => {
        const key = code.toLowerCase()
        const item = byCode.get(key)
        if (!item) {
            unmatched.push(code)
            return
        }
        if (used.has(key)) {
            duplicates.push(code)
            return
        }
        used.add(key)
        ordered.push(item)
    })

    const onTab = new Set(props.items.map((item) => codeOf(item)))
    const added = ordered.filter((item) => !onTab.has(codeOf(item)))
    const unlisted = props.items.filter((item) => !used.has(codeOf(item)))

    return {
        ordered,
        added,
        unlisted,
        unmatched,
        duplicates,
        result: unlistedMode.value === "drop" ? ordered : unlistedMode.value === "after" ? [...ordered, ...unlisted] : [...unlisted, ...ordered],
    }
})

const sampleCodes = computed(() => props.items.slice(0, 4).map((item) => item?.code).filter(Boolean).join(", "))

const isApplying = ref(false)

const unresolvedCodes = () => {
    const known = new Set(props.items.map((item) => codeOf(item)))
    return parsedCodes.value
        .map((code) => code.toLowerCase())
        .filter((code) => !known.has(code) && !(code in fetchedByCode.value))
}

const apply = async () => {
    isApplying.value = true
    try {
        if (lookupTimer) clearTimeout(lookupTimer)
        await lookupMissingCodes(unresolvedCodes())
    } finally {
        isApplying.value = false
    }

    if (!preview.value.ordered.length) return
    emits("apply", preview.value.result)
    emits("close")
}
</script>

<template>
    <Modal :isOpen="isOpen" width="w-full max-w-3xl" @onClose="emits('close')">
        <div class="p-1">
            <h2 class="flex items-center gap-2 text-lg font-semibold text-gray-800">
                <FontAwesomeIcon icon="fal fa-copy" fixed-width aria-hidden="true" />
                {{ trans("Paste order") }}
            </h2>

            <div class="mt-3 rounded-md border border-blue-200 bg-blue-50 p-3 text-xs text-blue-800">
                <p class="flex items-center gap-2 font-medium">
                    <FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" />
                    {{ trans("One code per line, in the order you want them") }}
                </p>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <pre class="rounded bg-white/70 p-2 font-mono leading-5">1. VEDIC-08
2. VEDIC-12
3. VEDIC-03</pre>
                    <ul class="space-y-1">
                        <li>{{ trans("Numbering like 1. 2) or - is ignored, the line order is what counts") }}</li>
                        <li>{{ trans("Anything after the code is ignored, e.g. 3. VEDIC-03 Rose incense") }}</li>
                        <li>{{ trans("Upper or lower case both work, blank lines are skipped") }}</li>
                    </ul>
                </div>
            </div>

            <p v-if="items.length" class="mt-2 text-xs text-gray-500">
                {{ trans("Matching against the :count rows on this tab, e.g.", { count: String(items.length) }) }}
                <span class="font-mono">{{ sampleCodes }}</span>
            </p>
            <p v-else class="mt-2 flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
                <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
                {{ trans("This tab has no rows loaded, so nothing can be matched") }}
            </p>

            <textarea
                v-model="pastedText"
                rows="10"
                spellcheck="false"
                :placeholder="trans('Paste the list here')"
                class="mt-3 w-full rounded-md border-gray-300 font-mono text-sm focus:border-[--app-accent] focus:ring-[--app-accent]" />

            <div v-if="parsedCodes.length" class="mt-3 space-y-2 text-sm">
                <div class="rounded-md border border-gray-200">
                    <p class="flex items-center gap-2 border-b border-gray-100 px-3 py-2 text-gray-700">
                        <FontAwesomeIcon v-if="isLooking" icon="fal fa-spinner" spin fixed-width class="text-gray-400" aria-hidden="true" />
                        <FontAwesomeIcon v-else icon="fal fa-check-circle" fixed-width class="text-green-600" aria-hidden="true" />
                        {{ trans(":matched of :total lines matched", { matched: String(preview.ordered.length), total: String(parsedCodes.length) }) }}
                    </p>
                    <ul class="divide-y divide-gray-100 text-xs">
                        <li v-if="preview.added.length" class="flex items-start gap-2 px-3 py-1.5 text-gray-600">
                            <FontAwesomeIcon icon="fal fa-info-circle" fixed-width class="mt-0.5 shrink-0 text-gray-400" aria-hidden="true" />
                            <span>{{ trans("Pulled in, not on this tab:") }} <span class="font-mono">{{ preview.added.map((item) => item.code).join(", ") }}</span></span>
                        </li>
                        <li v-if="preview.unlisted.length" class="flex items-center gap-2 px-3 py-1.5 text-gray-600">
                            <FontAwesomeIcon icon="fal fa-info-circle" fixed-width class="shrink-0 text-gray-400" aria-hidden="true" />
                            <span class="min-w-0 flex-1">{{ trans(":count rows on this tab are not in your list", { count: String(preview.unlisted.length) }) }}</span>
                            <select v-model="unlistedMode" class="shrink-0 rounded-md border-gray-300 py-0.5 pl-2 pr-7 text-xs focus:border-[--app-accent] focus:ring-[--app-accent]">
                                <option value="after">{{ trans("Keep them, after the pasted ones") }}</option>
                                <option value="before">{{ trans("Keep them, before the pasted ones") }}</option>
                                <option value="drop">{{ trans("Only keep the pasted ones") }}</option>
                            </select>
                        </li>
                        <li v-if="preview.duplicates.length" class="flex items-start gap-2 bg-amber-50 px-3 py-1.5 text-amber-800">
                            <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width class="mt-0.5 shrink-0" aria-hidden="true" />
                            <span>{{ trans("Listed more than once, first one counts:") }} <span class="font-mono">{{ preview.duplicates.join(", ") }}</span></span>
                        </li>
                        <li v-if="preview.unmatched.length" class="flex items-start gap-2 bg-amber-50 px-3 py-1.5 text-amber-800">
                            <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width class="mt-0.5 shrink-0" aria-hidden="true" />
                            <span>{{ trans("Not found here, left out:") }} <span class="font-mono">{{ preview.unmatched.join(", ") }}</span></span>
                        </li>
                        <li v-if="lookupFailed" class="flex items-start gap-2 bg-amber-50 px-3 py-1.5 text-amber-800">
                            <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width class="mt-0.5" aria-hidden="true" />
                            <span>{{ trans("Could not check the codes that are not on this tab") }}</span>
                        </li>
                    </ul>
                </div>

                <div class="max-h-60 overflow-y-auto rounded-md border border-gray-200">
                    <ol class="divide-y divide-gray-100 text-sm">
                        <li v-for="(item, index) in preview.result" :key="item.id ?? item.code" class="flex items-center gap-3 px-3 py-1.5">
                            <span class="w-8 shrink-0 text-right text-xs tabular-nums text-gray-400">{{ index + 1 }}</span>
                            <span class="shrink-0 font-mono text-xs text-gray-700">{{ item.code }}</span>
                            <span class="min-w-0 flex-1 truncate text-gray-600">{{ item.name }}</span>
                        </li>
                    </ol>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-2">
                <Button type="tertiary" :label="trans('Cancel')" @click="emits('close')" />
                <Button type="save" :label="trans('Apply order')" :loading="isApplying" :disabled="!parsedCodes.length" @click="apply" />
            </div>

            <p class="mt-2 text-right text-xs text-gray-400">{{ trans("Nothing is saved until you press Save order") }}</p>
        </div>
    </Modal>
</template>
