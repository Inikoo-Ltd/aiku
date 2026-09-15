<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { useForm } from "@inertiajs/vue3"
import { useConfirm } from "primevue/useconfirm"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { trans } from "laravel-vue-i18n"

/**
 * Opens on a copy of an ad Google has already approved, for the marketer to vary.
 *
 * Starting from a working ad rather than an empty form is the whole point. It carries the structure
 * and the voice that earned the rating, so the variant differs in the lines being tested and nothing
 * else, and there is no need for a strength meter to tell somebody they have written too little.
 */
const props = defineProps<{
    adGroupId: string
    ad: {
        id: string
        headlines: string[]
        descriptions: string[]
        final_urls: string[]
    }
    storeRoute: { name: string; parameters: Record<string, unknown> }
}>()

const HEADLINE_MAX = 30
const DESCRIPTION_MAX = 90

const confirm = useConfirm()
const open = ref(false)

const form = useForm({
    ad_group_id: props.adGroupId,
    final_url: props.ad.final_urls[0] ?? "",
    headlines: [...props.ad.headlines],
    descriptions: [...props.ad.descriptions],
})

const filled = (lines: string[]) => lines.filter((line) => line.trim()).length

/* Google refuses an ad that repeats an asset, comparing them without regard to case, so a duplicate
   is caught while it is being typed rather than coming back as a refusal after the round trip. */
const duplicates = (lines: string[]) => {
    const seen = new Map<string, number>()
    const repeated = new Set<number>()

    lines.forEach((line, index) => {
        const key = line.trim().toLowerCase()
        if (!key) return
        if (seen.has(key)) {
            repeated.add(seen.get(key) as number)
            repeated.add(index)
        } else {
            seen.set(key, index)
        }
    })

    return repeated
}

const duplicateHeadlines = computed(() => duplicates(form.headlines))
const duplicateDescriptions = computed(() => duplicates(form.descriptions))

/* A copy that changes nothing is not a test, it is a second identical ad splitting the same traffic. */
const isUnchanged = computed(
    () =>
        form.headlines.join("|") === props.ad.headlines.join("|") &&
        form.descriptions.join("|") === props.ad.descriptions.join("|")
)

const missing = computed(() => {
    const items: string[] = []

    if (filled(form.headlines) < 3) items.push(trans("at least 3 headlines"))
    if (filled(form.descriptions) < 2) items.push(trans("at least 2 descriptions"))
    if (!form.final_url.trim()) items.push(trans("a landing page"))
    if (duplicateHeadlines.value.size || duplicateDescriptions.value.size) items.push(trans("no repeated lines"))
    if (isUnchanged.value) items.push(trans("at least one line changed from the original"))

    return items
})

const addHeadline = () => form.headlines.length < 15 && form.headlines.push("")
const addDescription = () => form.descriptions.length < 4 && form.descriptions.push("")

const submit = () =>
    confirm.require({
        header: ctrans("Create this ad"),
        message: ctrans(
            "It starts running in this ad group straight away and rotates against the original, so Google can learn which does better. It reviews the copy before showing it, and rates it overnight."
        ),
        rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
        acceptProps: { label: ctrans("Create it") },
        accept: () =>
            form
                .transform((data) => ({
                    ...data,
                    headlines: data.headlines.filter((line: string) => line.trim()),
                    descriptions: data.descriptions.filter((line: string) => line.trim()),
                }))
                .post(route(props.storeRoute.name, props.storeRoute.parameters), {
                    preserveScroll: true,
                    onSuccess: () => (open.value = false),
                }),
    })
</script>

<template>
    <div>
        <button
            type="button"
            class="rounded px-1.5 py-0.5 text-xs text-indigo-600 underline-offset-2 transition hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            :aria-expanded="open"
            @click="open = !open">
            {{ open ? trans("Cancel") : trans("Test a variant of this ad") }}
        </button>

        <div v-if="open" class="mt-3 rounded-lg bg-gray-50 p-3 ring-1 ring-gray-200">
            <p class="text-xs text-gray-600">
                {{ trans("This is a copy of the ad above. Change the lines you want to test, and Google will rotate the two against each other.") }}
            </p>

            <div class="mt-3">
                <label :for="`dup-url-${ad.id}`" class="block text-xs text-gray-500">{{ trans("Landing page") }}</label>
                <input
                    :id="`dup-url-${ad.id}`"
                    v-model="form.final_url"
                    type="url"
                    class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            <div class="mt-3">
                <div class="flex items-baseline justify-between">
                    <span class="text-xs font-medium text-gray-700">
                        {{ trans("Headlines") }}
                        <span class="font-normal text-gray-500">· {{ filled(form.headlines) }}/15</span>
                    </span>
                    <button
                        v-if="form.headlines.length < 15"
                        type="button"
                        class="rounded px-1.5 text-xs text-indigo-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        @click="addHeadline">
                        {{ trans("Add one") }}
                    </button>
                </div>
                <div class="mt-1 space-y-1">
                    <div v-for="(line, i) in form.headlines" :key="`h-${i}`" class="flex items-center gap-2">
                        <input
                            v-model="form.headlines[i]"
                            type="text"
                            :maxlength="HEADLINE_MAX"
                            :aria-label="trans('Headline') + ' ' + (i + 1)"
                            class="w-full rounded-md text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :class="duplicateHeadlines.has(i) ? 'border-[#d03b3b]' : 'border-gray-300'" />
                        <span class="w-10 shrink-0 text-right text-xs tabular-nums text-gray-500">
                            {{ line.length }}/{{ HEADLINE_MAX }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <div class="flex items-baseline justify-between">
                    <span class="text-xs font-medium text-gray-700">
                        {{ trans("Descriptions") }}
                        <span class="font-normal text-gray-500">· {{ filled(form.descriptions) }}/4</span>
                    </span>
                    <button
                        v-if="form.descriptions.length < 4"
                        type="button"
                        class="rounded px-1.5 text-xs text-indigo-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        @click="addDescription">
                        {{ trans("Add one") }}
                    </button>
                </div>
                <div class="mt-1 space-y-1">
                    <div v-for="(line, i) in form.descriptions" :key="`d-${i}`" class="flex items-start gap-2">
                        <textarea
                            v-model="form.descriptions[i]"
                            rows="2"
                            :maxlength="DESCRIPTION_MAX"
                            :aria-label="trans('Description') + ' ' + (i + 1)"
                            class="w-full rounded-md text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :class="duplicateDescriptions.has(i) ? 'border-[#d03b3b]' : 'border-gray-300'"></textarea>
                        <span class="w-12 shrink-0 pt-2 text-right text-xs tabular-nums text-gray-500">
                            {{ line.length }}/{{ DESCRIPTION_MAX }}
                        </span>
                    </div>
                </div>
            </div>

            <p v-if="missing.length" class="mt-3 text-xs text-[#a15c00]">
                {{ trans("Still needed") }}: {{ missing.join(", ") }}.
            </p>
            <p v-for="(message, field) in form.errors" :key="field" class="mt-1 text-xs text-[#d03b3b]">
                {{ message }}
            </p>

            <div class="mt-3">
                <Button
                    type="save"
                    size="xs"
                    :label="trans('Create it')"
                    :loading="form.processing"
                    :disabled="form.processing || missing.length > 0"
                    @click="submit" />
            </div>
        </div>
    </div>
</template>
