<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { useConfirm } from "primevue/useconfirm"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { trans } from "laravel-vue-i18n"

/**
 * Adding a keyword straight to an ad group, for when somebody already knows what they want to bid on.
 * The search terms table above is the other way in, for when they do not.
 */
const props = defineProps<{
    adGroupId: string
    adGroupName: string | null
    updateRoute: { name: string; parameters: Record<string, unknown> }
}>()

const MATCH_TYPES = ["BROAD", "PHRASE", "EXACT"]

const confirm = useConfirm()
const text = ref("")
const matchType = ref("PHRASE")
const busy = ref(false)
const error = ref<string | null>(null)

const add = () => {
    const keyword = text.value.trim()
    if (!keyword) return

    confirm.require({
        header: ctrans("Add keyword"),
        message:
            ctrans("It starts costing money as soon as Google accepts it, in ad group ") +
            (props.adGroupName ?? props.adGroupId) + ". " + ctrans("Keyword: ") + keyword,
        rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
        acceptProps: { label: ctrans("Add it") },
        accept: () =>
            router.post(
                route(props.updateRoute.name, props.updateRoute.parameters),
                { ad_group_id: props.adGroupId, text: keyword, match_type: matchType.value },
                {
                    preserveScroll: true,
                    onStart: () => {
                        busy.value = true
                        error.value = null
                    },
                    onError: (errors) =>
                        (error.value = Object.values(errors as Record<string, string>)[0] ?? ctrans("That change was refused.")),
                    onSuccess: () => (text.value = ""),
                    onFinish: () => (busy.value = false),
                }
            ),
    })
}
</script>

<template>
    <div class="mt-3 border-t border-gray-100 pt-3">
        <div class="flex flex-wrap items-end gap-2">
            <div>
                <label :for="`gads-kw-${adGroupId}`" class="block text-xs text-gray-500">
                    {{ trans("Add a keyword") }}
                </label>
                <input
                    :id="`gads-kw-${adGroupId}`"
                    v-model="text"
                    type="text"
                    maxlength="80"
                    :placeholder="trans('wholesale candles')"
                    :disabled="busy"
                    class="mt-1 w-48 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100"
                    @keydown.enter.prevent="add" />
            </div>
            <div>
                <label :for="`gads-kw-match-${adGroupId}`" class="sr-only">{{ trans("Match type") }}</label>
                <select
                    :id="`gads-kw-match-${adGroupId}`"
                    v-model="matchType"
                    :disabled="busy"
                    class="rounded-md border-gray-300 text-sm capitalize focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                    <option v-for="type in MATCH_TYPES" :key="type" :value="type" class="capitalize">
                        {{ type.toLowerCase() }}
                    </option>
                </select>
            </div>
            <Button
                type="tertiary"
                size="xs"
                :label="trans('Add')"
                :loading="busy"
                :disabled="busy || !text.trim()"
                @click="add" />
        </div>

        <p v-if="error" class="mt-1 text-xs text-[#d03b3b]">{{ error }}</p>
    </div>
</template>
