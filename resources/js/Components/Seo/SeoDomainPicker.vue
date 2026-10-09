<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import InputText from "primevue/inputtext"
import MultiSelect from "primevue/multiselect"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    tab: string
    parameter: string
    competitors: string[]
    domains: string[]
    max: number
    label: string
}>()

const page = usePage()

const picked = ref<string[]>(props.domains.filter((domain) => props.competitors.includes(domain)))
const typed = ref(props.domains.filter((domain) => !props.competitors.includes(domain)).join(", "))
const isRunning = ref(false)

const selectedDomains = computed(() => [
    ...picked.value,
    ...typed.value.split(",").map((domain) => domain.trim()).filter(Boolean),
])

const run = () => {
    const url = new URL(page.url, window.location.origin)

    url.searchParams.set(props.parameter, selectedDomains.value.join(","))

    router.get(url.pathname + url.search, {}, {
        preserveState: true,
        preserveScroll: true,
        only: [props.tab],
        onStart: () => isRunning.value = true,
        onFinish: () => isRunning.value = false,
    })
}
</script>

<template>
    <form
        class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 ring-1 ring-gray-200 lg:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_auto] lg:items-end"
        @submit.prevent="run">
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">{{ ctrans("Competitors") }}</span>
            <MultiSelect
                v-model="picked"
                class="h-10 w-full items-center"
                :options="competitors"
                :selectionLimit="max"
                :placeholder="competitors.length ? ctrans('Pick up to :max', { max }) : ctrans('No competitors set yet')"
                display="chip" />
        </label>
        <label class="flex flex-col gap-1 text-sm">
            <span class="font-medium text-gray-700">{{ ctrans("Or other domains") }}</span>
            <InputText v-model="typed" class="h-10 w-full" :placeholder="ctrans('Comma separated, for example: example.com')" />
        </label>
        <Button
            class="h-10 justify-center"
            type="primary"
            :label="label"
            icon="fal fa-search"
            :loading="isRunning"
            :disabled="!selectedDomains.length || selectedDomains.length > max"
            @click="run" />
    </form>
</template>
