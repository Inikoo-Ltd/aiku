<!--
  - Author: eka yudinata (https://github.com/ekayudinata)
  - Copyright (c) 2026, eka yudinata
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCommentAlt } from "@fortawesome/free-solid-svg-icons"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { routeType } from "@/types/route"

const props = defineProps<{
    data: object
    tab?: string
    inboxRoute: routeType
}>()

const recipientsTotal = computed(() => (props.data as any)?.meta?.total ?? 0)

const getInitials = (name: string): string =>
    (name ?? "")
        .split(" ")
        .filter(Boolean)
        .map((word) => word[0])
        .join("")
        .slice(0, 2)
        .toUpperCase()

const conversationHref = (ulid: string) =>
    `${route(props.inboxRoute.name, props.inboxRoute.parameters)}?channel=whatsapp&session=${ulid}`
</script>

<template>
    <div class="w-full max-w-5xl mx-auto px-4 sm:px-6 py-8">
        <div class="mb-4">
            <h2 class="text-lg font-medium text-gray-800">
                {{ trans(":count recipients", { count: recipientsTotal }) }}
            </h2>
            <p class="text-sm text-gray-500">
                {{ trans("Your campaign has been sent to the contacts listed below.") }}
            </p>
        </div>

        <Table :resource="data" name="recipients">
            <template #cell(name)="{ item }">
                <div class="flex items-center gap-3">
                    <span
                        class="w-8 h-8 rounded-full bg-gray-800 text-white flex items-center justify-center text-xs font-semibold shrink-0">
                        {{ getInitials(item.name) }}
                    </span>
                    <span class="text-gray-800">{{ item.name }}</span>
                </div>
            </template>

            <template #cell(status)="{ item }">
                <Icon :data="item.status_icon" />
            </template>

            <template #cell(actions)="{ item }">
                <Link
                    v-if="item.meta_chat_session_ulid"
                    :href="conversationHref(item.meta_chat_session_ulid)"
                    class="text-gray-400 hover:text-gray-600"
                    v-tooltip="trans('Open conversation')">
                    <FontAwesomeIcon :icon="faCommentAlt" />
                </Link>
            </template>
        </Table>
    </div>
</template>
