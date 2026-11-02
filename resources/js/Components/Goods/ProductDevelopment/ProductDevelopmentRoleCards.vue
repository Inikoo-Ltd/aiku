<!--
  - Author Louis Perez
  - Created on 16-10-2026-00h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { nextTick, ref } from "vue"
import Popover from "primevue/popover"
import { ctrans } from "@/Composables/useTrans"
import ProductDevelopmentProgressBar from "@/Components/Goods/ProductDevelopment/ProductDevelopmentProgressBar.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBellOn, faChevronDown, faArrowRight } from "@fal"
library.add(faBellOn, faChevronDown, faArrowRight)

export interface RoleMissingItem {
    label: string
    skus: string
    tab: string
    target: string
}

export interface RoleCard {
    role: string
    label: string
    done: number
    total: number
    missing: number
    starts_after_gate: boolean
    reminded?: { at: string, by: string } | null
    missing_items?: RoleMissingItem[]
}

defineProps<{
    roles: RoleCard[]
}>()

const emit = defineEmits<{
    jump: [target: { tab: string, target: string }]
}>()

const remindedTooltip = (reminded: { at: string, by: string }) => ctrans("Reminded :date by :user", { date: useFormatTime(reminded.at, { formatTime: "d MMM, HH:mm" }), user: reminded.by })

const popover = ref<InstanceType<typeof Popover> | null>(null)
const openRole = ref<RoleCard | null>(null)

const hasDetails = (role: RoleCard) => !role.starts_after_gate && !!role.missing_items?.length

let pending: { role: RoleCard, element: HTMLElement } | null = null

function toggle(event: Event, role: RoleCard) {
    if (!hasDetails(role)) {
        return
    }
    const element = event.currentTarget as HTMLElement
    if (!openRole.value) {
        openRole.value = role
        popover.value?.show(event, element)
        return
    }
    pending = openRole.value.role === role.role ? null : { role, element }
    popover.value?.hide()
}

function onHide() {
    openRole.value = null
    const next = pending
    pending = null
    if (next) {
        nextTick(() => {
            openRole.value = next.role
            popover.value?.show({ currentTarget: next.element } as unknown as Event, next.element)
        })
    }
}

function go(item: RoleMissingItem) {
    popover.value?.hide()
    emit("jump", { tab: item.tab, target: item.target })
}
</script>

<template>
    <div class="grid grid-cols-2 gap-3 px-4 py-4 sm:grid-cols-3 md:px-6" :class="roles.length > 5 ? 'lg:grid-cols-6' : 'lg:grid-cols-5'">
        <component
            :is="hasDetails(role) ? 'button' : 'div'"
            v-for="role in roles"
            :key="role.role"
            :type="hasDetails(role) ? 'button' : undefined"
            class="rounded-lg border bg-white p-3 text-left transition-colors"
            :class="[
                hasDetails(role) ? 'cursor-pointer hover:border-[--app-accent-muted] hover:bg-[--app-accent-soft]/40' : '',
                openRole?.role === role.role ? 'border-[--app-accent] ring-1 ring-[--app-accent]' : 'border-gray-200',
            ]"
            :aria-haspopup="hasDetails(role) ? 'dialog' : undefined"
            @click="(event: Event) => toggle(event, role)"
        >
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-medium text-gray-800">{{ role.label }}</span>
                <span class="text-xs tabular-nums text-gray-500">{{ role.total ? role.done + "/" + role.total : "—" }}</span>
            </div>
            <ProductDevelopmentProgressBar :done="role.done" :total="role.total" class="mt-2" />
            <div class="mt-2 flex items-center gap-2 text-xs">
                <span v-if="role.starts_after_gate" class="text-gray-500">{{ ctrans("Starts after the gate") }}</span>
                <span v-else-if="!role.total" class="text-gray-400">{{ ctrans("Nothing to fill") }}</span>
                <span v-else-if="role.missing" class="flex items-center gap-1 text-amber-700">
                    {{ ctrans(":count missing", { count: String(role.missing) }) }}
                    <FontAwesomeIcon v-if="hasDetails(role)" icon="fal fa-chevron-down" class="text-[10px]" fixed-width aria-hidden="true" />
                </span>
                <span v-else class="text-green-700">{{ ctrans("All done") }}</span>
                <span
                    v-if="role.reminded && role.missing"
                    v-tooltip="remindedTooltip(role.reminded)"
                    class="ml-auto inline-flex items-center gap-1 rounded-full bg-violet-50 px-1.5 py-0.5 text-violet-700"
                    :aria-label="remindedTooltip(role.reminded)"
                >
                    <FontAwesomeIcon icon="fal fa-bell-on" fixed-width aria-hidden="true" />
                    {{ useFormatTime(role.reminded.at, { formatTime: "d MMM" }) }}
                </span>
            </div>
        </component>

        <Popover ref="popover" @hide="onHide">
            <div v-if="openRole" class="w-80 max-w-[85vw] text-sm">
                <p class="px-1 pb-2 text-xs font-medium text-gray-500">{{ ctrans(":role · still missing", { role: openRole.label }) }}</p>
                <ul class="max-h-80 divide-y divide-gray-100 overflow-y-auto">
                    <li v-for="item in openRole.missing_items" :key="item.label + item.target">
                        <button type="button" class="group flex w-full items-start gap-2 rounded-md px-1 py-2 text-left hover:bg-gray-50" @click="go(item)">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" aria-hidden="true" />
                            <span class="min-w-0 flex-1">
                                <span class="block text-gray-800">{{ item.label }}</span>
                                <span v-if="item.skus" class="block truncate text-xs text-gray-500">{{ item.skus }}</span>
                            </span>
                            <FontAwesomeIcon icon="fal fa-arrow-right" class="mt-1 shrink-0 text-xs text-gray-300 group-hover:text-[--app-accent]" fixed-width aria-hidden="true" />
                        </button>
                    </li>
                </ul>
            </div>
        </Popover>
    </div>
</template>
