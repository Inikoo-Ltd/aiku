<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import Table from "@/Components/Table/Table.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime, useRangeFromNow } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type NotFoundPathRow = {
    id: number
    path: string
    hits: number
    backlinks: number
    last_referrer: string | null
    is_ignored: boolean
    is_fixed: boolean
    first_seen_at: string
    last_seen_at: string
    ignore_route: routeType & { method: string }
}

const props = defineProps<{
    data: object
    website: { id: number, domain: string, url: string }
    canEdit: boolean
    tab?: string
}>()

const locale = useLocaleStore()

const routeParams = route().params as Record<string, string>

const selectedPath = ref<NotFoundPathRow | null>(null)
const targetWebpageId = ref<number | null>(null)
const redirectError = ref("")
const isSavingRedirect = ref(false)

const openRedirectModal = (notFoundPath: NotFoundPathRow) => {
    selectedPath.value = notFoundPath
    targetWebpageId.value = null
    redirectError.value = ""
}

const closeRedirectModal = () => {
    selectedPath.value = null
}

const createRedirect = async () => {
    if (!selectedPath.value || !targetWebpageId.value) {
        redirectError.value = ctrans("Pick the page this path should open.")

        return
    }

    isSavingRedirect.value = true
    redirectError.value = ""

    try {
        await axios.post(route("grp.models.website.redirect.store", { website: props.website.id }), {
            from_url: selectedPath.value.path,
            to_url: targetWebpageId.value,
        })

        notify({ title: ctrans("Redirect created"), text: selectedPath.value.path, type: "success" })
        closeRedirectModal()
        router.reload({ only: ["data"] })
    } catch (error: any) {
        const errors = error?.response?.data?.errors ?? {}
        redirectError.value = errors.from_url?.[0] ?? errors.from_path?.[0] ?? errors.to_url?.[0] ?? ctrans("The redirect could not be created.")
    } finally {
        isSavingRedirect.value = false
    }
}

const setIgnored = (notFoundPath: NotFoundPathRow, isIgnored: boolean) => {
    router.patch(route(notFoundPath.ignore_route.name, notFoundPath.ignore_route.parameters), { is_ignored: isIgnored }, {
        preserveScroll: true,
        only: ["data"],
    })
}

const referrerLabel = (referrer: string) => {
    try {
        const url = new URL(referrer)

        return url.host.replace(/^www\./, "") + url.pathname
    } catch {
        return referrer
    }
}
</script>

<template>
    <Table :resource="data" :name="tab">
        <template #cell(path)="{ item: notFoundPath }: { item: NotFoundPathRow }">
            <a :href="website.url + notFoundPath.path" target="_blank" rel="noopener noreferrer" class="break-all text-gray-900 underline-offset-2 hover:underline focus-visible:underline">
                {{ notFoundPath.path }}
            </a>
            <span v-if="notFoundPath.is_fixed" class="ml-2 text-xs text-green-700">{{ ctrans("Redirected") }}</span>
            <span v-else-if="notFoundPath.is_ignored" class="ml-2 text-xs text-gray-500">{{ ctrans("Ignored") }}</span>
        </template>

        <template #cell(hits)="{ item: notFoundPath }: { item: NotFoundPathRow }">
            <span class="tabular-nums">{{ locale.number(notFoundPath.hits) }}</span>
        </template>

        <template #cell(backlinks)="{ item: notFoundPath }: { item: NotFoundPathRow }">
            <span class="tabular-nums" :class="notFoundPath.backlinks ? 'font-medium text-gray-900' : 'text-gray-400'">{{ locale.number(notFoundPath.backlinks) }}</span>
        </template>

        <template #cell(last_seen_at)="{ item: notFoundPath }: { item: NotFoundPathRow }">
            <span class="whitespace-nowrap" :title="useFormatTime(notFoundPath.last_seen_at, { formatTime: 'short-datetime' })">{{ useRangeFromNow(notFoundPath.last_seen_at) }}</span>
        </template>

        <template #cell(first_seen_at)="{ item: notFoundPath }: { item: NotFoundPathRow }">
            <span class="whitespace-nowrap tabular-nums">{{ useFormatTime(notFoundPath.first_seen_at, { formatTime: "mdy" }) }}</span>
        </template>

        <template #cell(last_referrer)="{ item: notFoundPath }: { item: NotFoundPathRow }">
            <a v-if="notFoundPath.last_referrer" :href="notFoundPath.last_referrer" target="_blank" rel="noopener noreferrer" class="break-all text-gray-700 underline-offset-2 hover:underline">
                {{ referrerLabel(notFoundPath.last_referrer) }}
            </a>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(actions)="{ item: notFoundPath }: { item: NotFoundPathRow }">
            <div v-if="canEdit" class="flex justify-end gap-2 whitespace-nowrap">
                <Button
                    v-if="!notFoundPath.is_fixed && !notFoundPath.is_ignored"
                    type="secondary"
                    size="xs"
                    :label="ctrans('Create redirect')"
                    @click="openRedirectModal(notFoundPath)" />
                <Button
                    v-if="!notFoundPath.is_fixed"
                    type="tertiary"
                    size="xs"
                    :label="notFoundPath.is_ignored ? ctrans('Restore') : ctrans('Ignore')"
                    @click="setIgnored(notFoundPath, !notFoundPath.is_ignored)" />
            </div>
        </template>
    </Table>

    <Modal :isOpen="selectedPath !== null" width="w-full max-w-lg" closeButton @onClose="closeRedirectModal">
        <form v-if="selectedPath" class="space-y-4" @submit.prevent="createRedirect">
            <h2 class="text-base font-semibold text-gray-900">{{ ctrans("Create redirect") }}</h2>

            <div>
                <div class="text-sm font-medium text-gray-700">{{ ctrans("From") }}</div>
                <div class="mt-1 break-all rounded-md bg-gray-50 px-3 py-2 font-mono text-sm text-gray-900">{{ selectedPath.path }}</div>
            </div>

            <div>
                <div class="text-sm font-medium text-gray-700">{{ ctrans("To page") }}</div>
                <PureMultiselectInfiniteScroll
                    v-model="targetWebpageId"
                    class="mt-1"
                    :fetchRoute="{ name: 'grp.json.active_webpages.index', parameters: { shop: routeParams.shop } }"
                    :placeholder="ctrans('Search a live page by code or URL')"
                    valueProp="id"
                    labelProp="code"
                    :disabled="isSavingRedirect">
                    <template #singlelabel="{ value }">
                        <div class="w-full truncate pl-3 pr-2 text-left text-sm">
                            {{ value.code }} <span class="text-gray-400">{{ value.href }}</span>
                        </div>
                    </template>
                    <template #option="{ option }">
                        <div class="text-sm">{{ option.code }} <span class="text-gray-400">{{ option.href }}</span></div>
                    </template>
                </PureMultiselectInfiniteScroll>
                <p class="mt-1 text-xs text-gray-500">{{ ctrans("Redirects match the last part of the path, so every URL ending the same way goes to this page.") }}</p>
            </div>

            <p v-if="redirectError" role="alert" class="text-sm text-red-600">{{ redirectError }}</p>

            <div class="flex justify-end gap-2">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="closeRedirectModal" />
                <Button type="save" :label="ctrans('Create redirect')" :loading="isSavingRedirect" @click="createRedirect" />
            </div>
        </form>
    </Modal>
</template>
