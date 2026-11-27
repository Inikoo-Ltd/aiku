<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 28 Nov 2026 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router, usePage, Link } from "@inertiajs/vue3"
import { computed, onBeforeUnmount, onMounted, ref } from "vue"
import { ctrans } from "@/Composables/useTrans"

interface HelpPerson {
    id: number
    username: string
    contact_name: string | null
}

interface HelpGrant {
    ulid: string
    granter: HelpPerson
    helper: HelpPerson
    started_at: string | null
    expires_at: string
    record_url: string
}

const help = computed<{ helping: HelpGrant | null; offered: HelpGrant | null; granted: HelpGrant | null } | null>(() => usePage().props?.auth?.user?.help ?? null)

const now = ref(Date.now())
let clock: ReturnType<typeof setInterval> | null = null
onMounted(() => (clock = setInterval(() => (now.value = Date.now()), 1000)))
onBeforeUnmount(() => clock && clearInterval(clock))

const minutesLeft = (grant: HelpGrant) => Math.max(0, Math.ceil((Date.parse(grant.expires_at) - now.value) / 60000))
const nameOf = (person: HelpPerson) => person.contact_name || person.username

const bars = computed(() => {
    const list: Array<{ grant: HelpGrant; text: string; action: "start" | "stop" | "end" }> = []
    const h = help.value
    if (!h) return list
    if (h.helping) list.push({ grant: h.helping, action: "stop", text: ctrans("You are helping :name in their aiku", { name: nameOf(h.helping.granter) }) })
    if (h.offered) list.push({ grant: h.offered, action: "start", text: ctrans(":name lets you help them in their aiku", { name: nameOf(h.offered.granter) }) })
    if (h.granted) list.push({ grant: h.granted, action: "end", text: h.granted.started_at ? ctrans(":name is helping you in your aiku", { name: nameOf(h.granted.helper) }) : ctrans(":name may help you in your aiku", { name: nameOf(h.granted.helper) }) })
    return list.filter((bar) => minutesLeft(bar.grant) > 0)
})

const busy = ref<string | null>(null)
const errorFor = ref<Record<string, string>>({})

const act = (bar: { grant: HelpGrant; action: "start" | "stop" | "end" }) => {
    busy.value = bar.grant.ulid
    errorFor.value = {}
    const options = {
        preserveScroll: true,
        onError: () => (errorFor.value = { [bar.grant.ulid]: ctrans("It could not be changed, it may have ended already") }),
        onFinish: () => (busy.value = null),
    }
    if (bar.action === "start") {
        router.post(route("grp.chat.staff.help.start", bar.grant.ulid), {}, options)
    } else {
        router.delete(route("grp.chat.staff.help.destroy", bar.grant.ulid), options)
    }
}

const actionLabel = { start: ctrans("Start"), stop: ctrans("Stop"), end: ctrans("End now") }
</script>

<template>
    <div v-if="bars.length" class="fixed left-1/2 top-1 z-[90] flex w-[calc(100vw-2rem)] max-w-lg -translate-x-1/2 flex-col gap-1">
        <div
            v-for="bar in bars"
            :key="bar.grant.ulid + bar.action"
            role="status"
            class="flex items-center gap-2 rounded-md border border-amber-300 bg-amber-100 px-2.5 py-1 text-xs text-amber-900 shadow">
            <span class="min-w-0 flex-1 truncate">
                <span class="font-semibold">{{ bar.text }}</span>
                · {{ ctrans(":minutes min left", { minutes: minutesLeft(bar.grant) }) }}
                · <Link :href="bar.grant.record_url" class="underline">{{ ctrans("Changes made") }}</Link>
                <span v-if="errorFor[bar.grant.ulid]" class="text-red-700"> · {{ errorFor[bar.grant.ulid] }}</span>
            </span>
            <button
                type="button"
                class="shrink-0 rounded border border-amber-400 bg-white px-2 py-0.5 font-semibold text-amber-900 hover:bg-amber-50 disabled:opacity-50"
                :disabled="busy === bar.grant.ulid"
                @click="act(bar)">
                {{ actionLabel[bar.action] }}
            </button>
        </div>
    </div>
</template>
