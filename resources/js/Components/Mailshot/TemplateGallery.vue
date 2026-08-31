<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from "vue"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

const props = defineProps<{
    data: any
    tab: string
}>()

const emits = defineEmits<{
    (e: "select-snapshot", snapshot: any): void
}>()

const items = computed(() => props.data?.data ?? [])

const isMailshotTab = computed(() => props.tab === "previous_mailshots" || props.tab === "other_store_mailshots")

const previewRoute = (id: number, preview: boolean) =>
    isMailshotTab.value
        ? route("grp.json.mailshot.template", { mailshot: id, ...(preview ? { preview: 1 } : {}) })
        : route("grp.json.email_templates.layout", { emailTemplate: id, ...(preview ? { preview: 1 } : {}) })

const previews = ref<Record<number, string>>({})
const loadingPreview = ref<Record<number, boolean>>({})
const usingId = ref<number | null>(null)

const loadPreview = async (id: number) => {
    if (previews.value[id] !== undefined || loadingPreview.value[id]) {
        return
    }
    loadingPreview.value[id] = true
    try {
        const { data } = await axios.get(previewRoute(id, true))
        previews.value[id] = data?.html ?? ""
    } catch (error) {
        previews.value[id] = ""
    } finally {
        loadingPreview.value[id] = false
    }
}

const useTemplate = async (id: number) => {
    usingId.value = id
    try {
        const { data } = await axios.get(previewRoute(id, false))
        if (data) {
            emits("select-snapshot", data)
        }
    } finally {
        usingId.value = null
    }
}

/*
 * Built here rather than in onMounted: the template's ref callbacks run before onMounted,
 * so a later observer would miss every card of the first render.
 */
const observer = new IntersectionObserver(
    (entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                loadPreview(Number((entry.target as HTMLElement).dataset.templateId))
                observer.unobserve(entry.target)
            }
        })
    },
    { rootMargin: "200px" }
)

const observeCard = (el: Element | null, id: number) => {
    if (!el) {
        return
    }
    el.setAttribute("data-template-id", String(id))
    observer.observe(el)
}

onBeforeUnmount(() => observer.disconnect())
</script>

<template>
    <div>
        <div v-if="!items.length" class="py-10 text-center text-sm text-gray-500">
            {{ trans("No templates here yet") }}
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-5">
            <div
                v-for="item in items"
                :key="item.id"
                :ref="(el) => observeCard(el as Element, item.id)"
                class="border border-gray-200 rounded-lg overflow-hidden bg-white flex flex-col"
            >
                <div class="px-3 pt-3">
                    <div class="text-sm text-gray-900 truncate" :title="item.name ?? item.subject">
                        {{ item.name ?? item.subject }}
                    </div>
                    <div class="text-xs text-gray-500 truncate">
                        <span v-if="item.shop_name">{{ item.shop_name }} &bull; </span>
                        <span>{{ useFormatTime(item.created_at) }}</span>
                    </div>
                </div>

                <div class="p-3 flex-1">
                    <div class="h-56 border border-gray-200 rounded bg-gray-50 overflow-hidden">
                        <iframe
                            v-if="previews[item.id]"
                            :srcdoc="previews[item.id]"
                            sandbox=""
                            loading="lazy"
                            class="w-[250%] h-[560px] origin-top-left scale-[0.4] border-0"
                        />
                        <div v-else class="h-full w-full animate-pulse bg-gray-100" />
                    </div>
                </div>

                <div class="px-3 pb-3">
                    <Button
                        :label="trans('Use Template')"
                        type="secondary"
                        size="xs"
                        class="w-full"
                        :loading="usingId === item.id"
                        @click="useTemplate(item.id)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
