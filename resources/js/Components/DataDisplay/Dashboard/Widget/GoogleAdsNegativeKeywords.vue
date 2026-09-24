<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { useConfirm } from "primevue/useconfirm"
import Button from "@/Components/Elements/Buttons/Button.vue"
import HelpTip from "@/Components/Utils/HelpTip.vue"
import { ctrans } from "@/Composables/useTrans"
import { trans } from "laravel-vue-i18n"

/**
 * A mature account carries hundreds of these, so the list is filtered rather than paged: the reason
 * anyone opens it is to answer "are we already excluding this term", and typing the term answers that
 * faster than any page control. Nothing renders until the account is small or the filter is used.
 */
const props = defineProps<{
    negativeKeywords: { id: string; text: string; match_type: string }[]
    updateRoute: { name: string; parameters: Record<string, unknown> }
}>()

const confirm = useConfirm()

const MATCH_TYPES = ["BROAD", "PHRASE", "EXACT"]
const SHOWN_WITHOUT_FILTER = 25

const filter = ref("")
const newText = ref("")
const newMatchType = ref("PHRASE")
const busy = ref(false)
const errors = ref<Record<string, string>>({})

const matched = computed(() => {
    const needle = filter.value.trim().toLowerCase()
    if (!needle) return props.negativeKeywords

    return props.negativeKeywords.filter((keyword) => keyword.text?.toLowerCase().includes(needle))
})

const shown = computed(() =>
    filter.value.trim() ? matched.value : matched.value.slice(0, SHOWN_WITHOUT_FILTER)
)

const hiddenCount = computed(() => matched.value.length - shown.value.length)

const submit = (payload: Record<string, unknown>, onDone?: () => void) =>
    router.patch(route(props.updateRoute.name, props.updateRoute.parameters), payload, {
        preserveScroll: true,
        onStart: () => {
            busy.value = true
            errors.value = {}
        },
        onError: (formErrors) => (errors.value = formErrors as Record<string, string>),
        onSuccess: () => onDone?.(),
        onFinish: () => (busy.value = false),
    })

const add = () => {
    if (!newText.value.trim()) return

    submit({ text: newText.value.trim(), match_type: newMatchType.value }, () => (newText.value = ""))
}

const remove = (keyword: { id: string; text: string }) =>
    confirm.require({
        header: ctrans("Stop excluding this term"),
        message:
            ctrans("Ads will be able to show for this search again, and it cannot be undone by adding it back: it returns as a new entry. Term: ") +
            keyword.text,
        rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
        acceptProps: { label: ctrans("Stop excluding it") },
        accept: () => submit({ criterion_id: keyword.id }),
    })
</script>

<template>
    <div>
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-medium text-gray-800">
                {{ trans("Excluded search terms") }}
                <span v-if="negativeKeywords.length" class="font-normal text-gray-500">
                    · {{ negativeKeywords.length }}
                </span>
                <HelpTip :text="trans('Searches this campaign never bids on. Adding one takes effect at Google straight away and applies to the whole campaign. Removing one lets the ad show for that search again.')" />
            </h2>
            <span class="text-xs text-gray-500">{{ trans("Searches this campaign will not bid on") }}</span>
        </div>

        <form class="mt-4 flex flex-wrap items-end gap-2" @submit.prevent="add">
            <div>
                <label for="gads-negative-text" class="block text-xs text-gray-500">{{ trans("Term to exclude") }}</label>
                <input
                    id="gads-negative-text"
                    v-model="newText"
                    type="text"
                    maxlength="80"
                    :placeholder="trans('free download')"
                    :disabled="busy"
                    class="mt-1 w-56 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100" />
            </div>
            <div>
                <label for="gads-negative-match" class="block text-xs text-gray-500">{{ trans("Match") }}</label>
                <select
                    id="gads-negative-match"
                    v-model="newMatchType"
                    :disabled="busy"
                    class="mt-1 rounded-md border-gray-300 text-sm capitalize focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                    <option v-for="type in MATCH_TYPES" :key="type" :value="type" class="capitalize">
                        {{ type.toLowerCase() }}
                    </option>
                </select>
            </div>
            <Button
                type="tertiary"
                :label="trans('Exclude it')"
                :loading="busy"
                :disabled="busy || !newText.trim()"
                @click="add" />
        </form>

        <p v-if="errors.text" class="mt-1 text-xs text-[#d03b3b]">{{ errors.text }}</p>
        <p v-if="errors.criterion_id" class="mt-1 text-xs text-[#d03b3b]">{{ errors.criterion_id }}</p>

        <div v-if="negativeKeywords.length" class="mt-4">
            <label for="gads-negative-filter" class="sr-only">{{ trans("Search excluded terms") }}</label>
            <input
                id="gads-negative-filter"
                v-model="filter"
                type="search"
                :placeholder="trans('Search these terms')"
                class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-72" />

            <p v-if="filter.trim() && !matched.length" class="mt-3 text-xs text-gray-500">
                {{ trans("Nothing excluded matches that, so ads can still show for it.") }}
            </p>

            <ul v-else class="mt-3 divide-y divide-gray-50">
                <li v-for="keyword in shown" :key="keyword.id" class="flex items-center justify-between gap-3 py-2 text-xs">
                    <span class="min-w-0">
                        <span class="truncate text-gray-700">{{ keyword.text }}</span>
                        <span class="ml-2 capitalize text-gray-500">{{ keyword.match_type?.toLowerCase() }}</span>
                    </span>
                    <button
                        type="button"
                        :disabled="busy"
                        class="rounded px-1.5 py-0.5 text-[#d03b3b] underline-offset-2 transition hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50"
                        @click="remove(keyword)">
                        {{ trans("Remove") }}
                    </button>
                </li>
            </ul>

            <p v-if="hiddenCount > 0" class="mt-3 text-xs text-gray-500">
                {{ trans("and :count more, type above to find one", { count: hiddenCount }) }}
            </p>
        </div>

        <p v-else class="mt-4 text-xs text-gray-500">
            {{ trans("Nothing is excluded yet, so this campaign can pay for any search Google matches it to.") }}
        </p>
    </div>
</template>
