<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { cloneDeep, get, set } from "lodash-es"
import VueDatePicker from "@vuepic/vue-datepicker"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faPlus } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import type { WebsiteDialogData } from "@/types/WebsiteDialog"

library.add(faTimes, faPlus)

defineProps<{
    displayFrequencies: { label: string, value: string }[]
    triggers: { label: string, value: string }[]
}>()

const dialogData = inject<WebsiteDialogData>("websiteDialogData")!
const schedule = inject<{ schedule_at: Date | string | null, schedule_finish_at: Date | string | null }>("websiteDialogSchedule")!

const fillDefaultSettings = () => {
    if (!dialogData.settings || Array.isArray(dialogData.settings)) {
        dialogData.settings = {}
    }

    if (!get(dialogData.settings, "target_pages.type")) {
        set(dialogData.settings, "target_pages", { type: "all", specific: [] })
    }

    if (!Array.isArray(get(dialogData.settings, "target_pages.specific"))) {
        set(dialogData.settings, "target_pages.specific", [])
    }

    if (!get(dialogData.settings, "target_users.auth_state")) {
        set(dialogData.settings, "target_users", { auth_state: "all" })
    }

    if (!get(dialogData.settings, "display_frequency")) {
        set(dialogData.settings, "display_frequency", "once_per_session")
    }

    if (!get(dialogData.settings, "trigger")) {
        set(dialogData.settings, "trigger", "automatic")
    }

    if (get(dialogData.settings, "delay_seconds") === undefined) {
        set(dialogData.settings, "delay_seconds", 0)
    }
}

fillDefaultSettings()

const settings = computed(() => dialogData.settings)
const isAutomatic = computed(() => settings.value.trigger !== "on_click")
const buttonLink = computed(() => `#website-dialog-${dialogData.ulid}`)

const newPageRule = ref({ will: "show" as "show" | "hide", when: "contain" as const, url: "" })

const addPageRule = () => {
    if (!newPageRule.value.url.trim()) {
        return
    }

    settings.value.target_pages?.specific.push(cloneDeep({ ...newPageRule.value, url: newPageRule.value.url.trim() }))
    newPageRule.value.url = ""
}

const removePageRule = (index: number) => {
    settings.value.target_pages?.specific.splice(index, 1)
}

const visitorOptions = [
    { value: "all", label: ctrans("Everybody") },
    { value: "logged_in", label: ctrans("Logged in") },
    { value: "logged_out", label: ctrans("Logged out") },
] as const
</script>

<template>
    <div v-if="settings" class="space-y-1.5 text-xs">
        <section class="rounded-md border border-slate-200 bg-white p-3">
            <h3 class="mb-0.5 text-sm font-semibold text-slate-800">{{ ctrans("Opens") }}</h3>
            <p class="mb-2 text-slate-500">{{ ctrans("When the dialog comes out") }}</p>

            <div class="space-y-1">
                <label
                    v-for="trigger in triggers"
                    :key="trigger.value"
                    class="flex cursor-pointer items-center gap-x-2 rounded border px-2 py-1.5 transition-colors"
                    :class="settings.trigger === trigger.value ? 'border-[var(--theme-color-0)] bg-[color-mix(in_srgb,var(--theme-color-0)_10%,white)] font-medium text-slate-900' : 'border-slate-200 hover:bg-slate-50'"
                >
                    <input v-model="settings.trigger" :value="trigger.value" type="radio" class="h-3.5 w-3.5 border-gray-300 text-[var(--theme-color-0)] accent-[var(--theme-color-0)] focus:ring-[var(--theme-color-0)]" />
                    {{ trigger.label }}
                </label>
            </div>

            <p class="mt-2 text-slate-500">
                {{ ctrans("Any webpage button can open it: in the webpage workshop choose the link type Dialog and pick this dialog. Link used:") }}
                <code class="select-all rounded bg-slate-100 px-1 text-slate-700">{{ buttonLink }}</code>
            </p>
        </section>

        <section v-if="isAutomatic" class="rounded-md border border-slate-200 bg-white p-3">
            <h3 class="mb-0.5 text-sm font-semibold text-slate-800">{{ ctrans("Pages") }}</h3>
            <p class="mb-2 text-slate-500">{{ ctrans("Where the dialog pops up") }}</p>

            <div class="grid grid-cols-2 overflow-hidden rounded border border-slate-200">
                <button
                    v-for="pageType in (['all', 'specific'] as const)"
                    :key="pageType"
                    type="button"
                    class="py-1.5 font-medium transition-colors"
                    :class="settings.target_pages?.type === pageType ? 'bg-[var(--theme-color-0)] text-[var(--theme-color-1)]' : 'bg-white text-slate-600 hover:bg-slate-50'"
                    @click="settings.target_pages!.type = pageType"
                >
                    {{ pageType === 'all' ? ctrans("All pages") : ctrans("Specific pages") }}
                </button>
            </div>

            <div v-if="settings.target_pages?.type === 'specific'" class="mt-2 space-y-2">
                <div class="flex gap-1">
                    <select v-model="newPageRule.will" :aria-label="ctrans('Show or hide')" class="w-20 rounded border-slate-300 py-1 pl-2 pr-6 text-xs">
                        <option value="show">{{ ctrans("Show") }}</option>
                        <option value="hide">{{ ctrans("Hide") }}</option>
                    </select>
                    <input
                        v-model="newPageRule.url"
                        type="text"
                        :placeholder="ctrans('URL contains, e.g. blog')"
                        :aria-label="ctrans('URL contains')"
                        class="min-w-0 flex-1 rounded border-slate-300 px-2 py-1 text-xs placeholder:text-slate-400"
                        @keydown.enter.prevent="addPageRule"
                    />
                    <button
                        type="button"
                        class="rounded border border-slate-300 px-2 text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                        :disabled="!newPageRule.url.trim()"
                        :aria-label="ctrans('Add')"
                        @click="addPageRule"
                    >
                        <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                    </button>
                </div>

                <ul v-if="settings.target_pages.specific.length" class="divide-y divide-slate-100 rounded border border-slate-200">
                    <li v-for="(rule, ruleIndex) in settings.target_pages.specific" :key="`${rule.will}-${rule.url}-${ruleIndex}`" class="flex items-center gap-x-1.5 px-2 py-1">
                        <span class="font-semibold" :class="rule.will === 'show' ? 'text-green-700' : 'text-red-700'">
                            {{ rule.will === 'show' ? ctrans("Show") : ctrans("Hide") }}
                        </span>
                        <span class="min-w-0 flex-1 truncate text-slate-600">… {{ rule.url }} …</span>
                        <button type="button" class="text-slate-300 hover:text-red-500" :aria-label="ctrans('Remove')" @click="removePageRule(ruleIndex)">
                            <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                        </button>
                    </li>
                </ul>
                <p v-else class="italic text-slate-400">{{ ctrans("No rule yet, the dialog shows on every page") }}</p>
            </div>
        </section>

        <section v-if="isAutomatic" class="rounded-md border border-slate-200 bg-white p-3">
            <h3 class="mb-0.5 text-sm font-semibold text-slate-800">{{ ctrans("Visitors") }}</h3>
            <p class="mb-2 text-slate-500">{{ ctrans("Who sees the dialog") }}</p>

            <div class="grid grid-cols-3 overflow-hidden rounded border border-slate-200">
                <button
                    v-for="option in visitorOptions"
                    :key="option.value"
                    type="button"
                    class="py-1.5 font-medium transition-colors"
                    :class="settings.target_users?.auth_state === option.value ? 'bg-[var(--theme-color-0)] text-[var(--theme-color-1)]' : 'bg-white text-slate-600 hover:bg-slate-50'"
                    @click="settings.target_users!.auth_state = option.value"
                >
                    {{ option.label }}
                </button>
            </div>
        </section>

        <section v-if="isAutomatic" class="rounded-md border border-slate-200 bg-white p-3">
            <h3 class="mb-0.5 text-sm font-semibold text-slate-800">{{ ctrans("Display") }}</h3>
            <p class="mb-2 text-slate-500">{{ ctrans("How often a visitor sees it after closing it") }}</p>

            <div class="space-y-1">
                <label
                    v-for="frequency in displayFrequencies"
                    :key="frequency.value"
                    class="flex cursor-pointer items-center gap-x-2 rounded border px-2 py-1.5 transition-colors"
                    :class="settings.display_frequency === frequency.value ? 'border-[var(--theme-color-0)] bg-[color-mix(in_srgb,var(--theme-color-0)_10%,white)] font-medium text-slate-900' : 'border-slate-200 hover:bg-slate-50'"
                >
                    <input v-model="settings.display_frequency" :value="frequency.value" type="radio" class="h-3.5 w-3.5 border-gray-300 text-[var(--theme-color-0)] accent-[var(--theme-color-0)] focus:ring-[var(--theme-color-0)]" />
                    {{ frequency.label }}
                </label>
            </div>

            <div class="mt-3 flex items-center gap-x-2">
                <label for="website-dialog-delay" class="text-slate-600">{{ ctrans("Pop up after") }}</label>
                <input
                    id="website-dialog-delay"
                    v-model.number="settings.delay_seconds"
                    type="number"
                    min="0"
                    max="600"
                    class="w-16 rounded border-slate-300 px-2 py-1 text-xs"
                />
                <span class="text-slate-600">{{ ctrans("seconds") }}</span>
            </div>
        </section>

        <section class="rounded-md border border-slate-200 bg-white p-3">
            <h3 class="mb-0.5 text-sm font-semibold text-slate-800">{{ ctrans("Schedule") }}</h3>
            <p class="mb-2 text-slate-500">{{ ctrans("Applied when you publish") }}</p>

            <div class="space-y-2">
                <div class="flex items-center justify-between gap-x-2">
                    <span class="font-medium text-slate-600">{{ ctrans("Start") }}</span>
                    <div class="flex items-center gap-x-1">
                        <button
                            type="button"
                            class="rounded px-2 py-1"
                            :class="!schedule.schedule_at ? 'bg-[var(--theme-color-0)] text-[var(--theme-color-1)]' : 'text-slate-500 hover:bg-slate-100'"
                            @click="schedule.schedule_at = null"
                        >
                            {{ ctrans("Now") }}
                        </button>
                        <VueDatePicker v-model="schedule.schedule_at" time-picker-inline auto-apply :min-date="new Date()" :clearable="false" teleport>
                            <template #trigger>
                                <button type="button" class="rounded px-2 py-1" :class="schedule.schedule_at ? 'bg-[var(--theme-color-0)] text-[var(--theme-color-1)]' : 'text-slate-500 hover:bg-slate-100'">
                                    {{ schedule.schedule_at ? useFormatTime(schedule.schedule_at, { formatTime: 'hm' }) : ctrans("Pick date") }}
                                </button>
                            </template>
                        </VueDatePicker>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-x-2">
                    <span class="font-medium text-slate-600">{{ ctrans("Finish") }}</span>
                    <div class="flex items-center gap-x-1">
                        <button
                            type="button"
                            class="rounded px-2 py-1"
                            :class="!schedule.schedule_finish_at ? 'bg-[var(--theme-color-0)] text-[var(--theme-color-1)]' : 'text-slate-500 hover:bg-slate-100'"
                            @click="schedule.schedule_finish_at = null"
                        >
                            {{ ctrans("Never") }}
                        </button>
                        <VueDatePicker
                            v-model="schedule.schedule_finish_at"
                            time-picker-inline
                            auto-apply
                            :min-date="schedule.schedule_at ? new Date(schedule.schedule_at) : new Date()"
                            :clearable="false"
                            teleport
                        >
                            <template #trigger>
                                <button type="button" class="rounded px-2 py-1" :class="schedule.schedule_finish_at ? 'bg-[var(--theme-color-0)] text-[var(--theme-color-1)]' : 'text-slate-500 hover:bg-slate-100'">
                                    {{ schedule.schedule_finish_at ? useFormatTime(schedule.schedule_finish_at, { formatTime: 'hm' }) : ctrans("Pick date") }}
                                </button>
                            </template>
                        </VueDatePicker>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
