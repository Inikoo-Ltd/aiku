<!--
  - Author Louis Perez
  - Created on 15-10-2026-19h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import ProductDevelopmentChecklistRow from "@/Components/Goods/ProductDevelopment/ProductDevelopmentChecklistRow.vue"
import type { ChecklistEditorData } from "@/Components/Goods/ProductDevelopment/productDevelopmentChecklist"
import TicketUserHoverCard from "@/Components/Tickets/TicketUserHoverCard.vue"
import ProductDevelopmentEditDialog, { type ProductDevelopmentEditKind, type ProductDevelopmentEditor } from "@/Components/Goods/ProductDevelopment/ProductDevelopmentEditDialog.vue"
import { computed, ref } from "vue"
import ProductDevelopmentSidePanel from "@/Components/Goods/ProductDevelopment/ProductDevelopmentSidePanel.vue"
import ProductDevelopmentBriefEditor, { type BriefEditor } from "@/Components/Goods/ProductDevelopment/ProductDevelopmentBriefEditor.vue"
import ProductDevelopmentProgressBar from "@/Components/Goods/ProductDevelopment/ProductDevelopmentProgressBar.vue"
import { productDevelopmentColor } from "@/Components/Goods/ProductDevelopment/productDevelopmentColors"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faShoppingBag, faUserTie, faFileInvoiceDollar, faShieldCheck, faPencilRuler, faGlobe, faStamp, faPencil, faFileAlt, faClipboardList, faInfoCircle, faUsers } from "@fal"
library.add(faShoppingBag, faUserTie, faFileInvoiceDollar, faShieldCheck, faPencilRuler, faGlobe, faStamp, faPencil, faFileAlt, faClipboardList, faInfoCircle, faUsers)

const props = defineProps<{
    editor?: ProductDevelopmentEditor | null
    data: {
        brief: string | null
        brief_editor: BriefEditor
        markets: string[]
        target_landing_date: string | null
        target_online_date: string | null
        packaging_family: string | null
        members: { id: number, user_id: number, name: string, username: string | null, avatar: Record<string, string> | null, profile_url: string | null, role: { label: string, icon: string, color: string } }[]
        editor: ChecklistEditorData
    }
}>()

const editDialog = ref<InstanceType<typeof ProductDevelopmentEditDialog> | null>(null)
const edit = (kind: ProductDevelopmentEditKind) => editDialog.value?.open(kind)

const checklist = computed(() => {
    const rows = props.data?.editor.family ?? []
    const open = rows.filter((row) => row.state === "missing")
    return {
        done: rows.length - open.length,
        total: rows.length,
        open: [...open.filter((row) => row.required), ...open.filter((row) => !row.required)],
    }
})

const MEMBERS_PREVIEW = 6

const formatDate = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM yyyy" }) : "—"
</script>

<template>
    <div v-if="data" class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-4">
            <ProductDevelopmentBriefEditor :brief="data.brief" :editor="data.brief_editor" />

            <ProductDevelopmentSidePanel :title="ctrans('Family checklist')" icon="fal fa-clipboard-list">
                <template #meta>
                    <span v-if="checklist.total" class="rounded-full px-2 py-0.5 text-xs font-medium tabular-nums" :class="checklist.open.length ? 'bg-amber-50 text-amber-800' : 'bg-green-50 text-green-700'">{{ checklist.done }}/{{ checklist.total }}</span>
                </template>
                <template #summary>
                    <div class="space-y-2">
                        <ProductDevelopmentProgressBar :done="checklist.done" :total="checklist.total" />
                        <ul v-if="checklist.open.length" class="space-y-1 text-sm">
                            <li v-for="row in checklist.open" :key="row.id" class="flex items-center gap-2">
                                <FontAwesomeIcon :icon="row.role.icon" :class="productDevelopmentColor(row.role.color).icon" fixed-width :aria-label="row.role.label" />
                                <span class="min-w-0 flex-1 truncate text-gray-700">{{ row.label }}</span>
                                <span v-if="!row.required" class="shrink-0 text-xs text-gray-400">{{ ctrans("optional") }}</span>
                            </li>
                        </ul>
                        <p v-else-if="checklist.total" class="text-sm text-green-700">{{ ctrans("All done") }}</p>
                    </div>
                </template>
                <ul class="divide-y divide-gray-100 px-4">
                    <ProductDevelopmentChecklistRow v-for="row in data.editor.family" :key="row.id" :row="row" :updateRoute="data.editor.routes.update" :countries="data.editor.countries" />
                    <li v-if="!data.editor.family.length" class="py-3 text-sm text-gray-400">{{ ctrans("No checklist items") }}</li>
                </ul>
            </ProductDevelopmentSidePanel>
        </div>

        <div class="min-w-0 space-y-4">
            <ProductDevelopmentSidePanel :title="ctrans('Details')" icon="fal fa-info-circle">
                <template #summary>
                    <div class="flex flex-wrap gap-1.5 text-xs">
                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-gray-700">{{ data.markets.length ? data.markets.join(", ") : ctrans("No markets") }}</span>
                        <span class="rounded-md bg-gray-100 px-2 py-0.5 tabular-nums text-gray-700">{{ ctrans("Landing :date", { date: formatDate(data.target_landing_date) }) }}</span>
                        <span class="rounded-md bg-gray-100 px-2 py-0.5 tabular-nums text-gray-700">{{ ctrans("Online :date", { date: formatDate(data.target_online_date) }) }}</span>
                    </div>
                </template>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 px-4 py-3 text-sm">
                    <div class="col-span-2">
                        <dt class="flex items-center justify-between gap-2 text-xs text-gray-500">
                            {{ ctrans("Markets") }}
                            <button v-if="editor" type="button" class="flex h-6 w-6 items-center justify-center rounded text-[--app-accent] transition-colors hover:bg-[--app-accent-soft] hover:text-[--app-accent-strong]" :aria-label="ctrans('Edit markets')" @click="edit('markets')">
                                <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                            </button>
                        </dt>
                        <dd class="text-gray-800">{{ data.markets.length ? data.markets.join(", ") : "—" }}</dd>
                    </div>
                    <div>
                        <dt class="flex items-center justify-between gap-2 text-xs text-gray-500">
                            {{ ctrans("Target landing") }}
                            <button v-if="editor" type="button" class="flex h-6 w-6 items-center justify-center rounded text-[--app-accent] transition-colors hover:bg-[--app-accent-soft] hover:text-[--app-accent-strong]" :aria-label="ctrans('Edit dates')" @click="edit('dates')">
                                <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                            </button>
                        </dt>
                        <dd class="tabular-nums text-gray-800">{{ formatDate(data.target_landing_date) }}</dd>
                    </div>
                    <div>
                        <dt class="flex items-center justify-between gap-2 text-xs text-gray-500">
                            {{ ctrans("Target online") }}
                            <button v-if="editor" type="button" class="flex h-6 w-6 items-center justify-center rounded text-[--app-accent] transition-colors hover:bg-[--app-accent-soft] hover:text-[--app-accent-strong]" :aria-label="ctrans('Edit dates')" @click="edit('dates')">
                                <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                            </button>
                        </dt>
                        <dd class="tabular-nums text-gray-800">{{ formatDate(data.target_online_date) }}</dd>
                    </div>
                </dl>
            </ProductDevelopmentSidePanel>

            <ProductDevelopmentSidePanel :title="ctrans('Members')" icon="fal fa-users">
                <template #meta>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium tabular-nums text-gray-600">{{ data.members.length }}</span>
                </template>
                <template v-if="editor" #actions>
                    <button type="button" class="flex h-6 w-6 items-center justify-center rounded text-[--app-accent] transition-colors hover:bg-[--app-accent-soft] hover:text-[--app-accent-strong]" :aria-label="ctrans('Edit members')" @click="edit('members')">
                        <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                    </button>
                </template>
                <template #summary>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span
                            v-for="member in data.members.slice(0, MEMBERS_PREVIEW)"
                            :key="member.id"
                            class="flex items-center gap-1 rounded-full border py-0.5 pl-0.5 pr-1.5"
                            :class="productDevelopmentColor(member.role.color).soft"
                            v-tooltip="`${member.name} · ${member.role.label}`"
                        >
                            <TicketUserHoverCard :name="member.name" :avatar="member.avatar" :username="member.username" :profileUrl="member.profile_url" :messageUserId="member.user_id" :canMention="false" size="sm" avatarOnly />
                            <FontAwesomeIcon :icon="member.role.icon" class="text-xs" :class="productDevelopmentColor(member.role.color).icon" fixed-width :aria-label="member.role.label" />
                        </span>
                        <span v-if="data.members.length > MEMBERS_PREVIEW" class="text-xs text-gray-500">{{ ctrans("+:count more", { count: String(data.members.length - MEMBERS_PREVIEW) }) }}</span>
                        <span v-if="!data.members.length" class="text-sm text-gray-400">{{ ctrans("No members yet") }}</span>
                    </div>
                </template>
                <ul class="space-y-1.5 px-4 py-3">
                    <li
                        v-for="member in data.members"
                        :key="member.id"
                        class="flex items-center gap-2 rounded-md border px-2 py-1.5"
                        :class="productDevelopmentColor(member.role.color).soft"
                    >
                        <TicketUserHoverCard :name="member.name" :avatar="member.avatar" :username="member.username" :profileUrl="member.profile_url" :messageUserId="member.user_id" :canMention="false" size="sm" avatarOnly />
                        <span class="min-w-0 flex-1 truncate text-sm text-gray-800">{{ member.name }}</span>
                        <span class="hidden shrink-0 text-xs text-gray-500 sm:inline xl:hidden 2xl:inline">{{ member.role.label }}</span>
                        <FontAwesomeIcon :icon="member.role.icon" :class="productDevelopmentColor(member.role.color).icon" v-tooltip="member.role.label" fixed-width :aria-label="member.role.label" />
                    </li>
                    <li v-if="!data.members.length" class="text-sm text-gray-400">{{ ctrans("No members yet") }}</li>
                </ul>
            </ProductDevelopmentSidePanel>
        </div>

        <ProductDevelopmentEditDialog v-if="editor" ref="editDialog" :editor="editor" />
    </div>
</template>
