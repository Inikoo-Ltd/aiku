<script setup lang="ts">
import { Head, Link, router, usePoll } from "@inertiajs/vue3"
import { computed, onBeforeUnmount, onMounted, ref } from "vue"
import axios from "axios"
import { route } from "ziggy-js"
import Select from "primevue/select"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faGlobe, faEnvelope, faInbox, faExternalLink, faUserHeadset, faPhone } from "@fal"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faUserHeadset)

type Presence = "online" | "away" | "offline"

interface Agent {
    id: number
    name: string | null
    presence: Presence
    open: number
    max: number
    on_call: boolean
    on_call_since: string | null
    shop_ids: number[]
}

interface QueueRow {
    ulid: string
    channel: "website" | "email" | "whatsapp"
    contact_name: string
    is_customer: boolean
    subject: string | null
    language: string | null
    waiting_since: string
    owner: { id: number; name: string | null } | null
    shop: { id: number; code: string } | null
    can_assign: boolean
    url: string
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    organisation: { id: number; slug: string }
    shops: Array<{ id: number; code: string; name: string; can_act: boolean }>
    stats: {
        waiting_chats: number
        waiting_chats_since: string | null
        unanswered_emails: number
        unanswered_emails_since: string | null
        unassigned: number
        unassigned_busiest_shop: { code: string; count: number } | null
        answered_today: number
    }
    queue: QueueRow[]
    agents: Agent[]
}>()

usePoll(30000, { only: ["stats", "queue", "agents"] })

const now = ref(Date.now())
let clock: ReturnType<typeof setInterval> | undefined
onMounted(() => (clock = setInterval(() => (now.value = Date.now()), 30000)))
onBeforeUnmount(() => clearInterval(clock))

const ageOf = (iso: string | null): string => {
    if (!iso) {
        return ""
    }

    const minutes = Math.max(0, Math.floor((now.value - new Date(iso).getTime()) / 60000))
    const hours = Math.floor(minutes / 60)
    const days = Math.floor(hours / 24)

    if (days) {
        return `${days} d ${hours % 24} h`
    }

    return hours ? `${hours} h ${minutes % 60} m` : `${minutes} m`
}

const isLongWait = (iso: string): boolean => now.value - new Date(iso).getTime() > 60 * 60 * 1000

const shopCode = computed(() => Object.fromEntries(props.shops.map((shop) => [shop.id, shop.code])))
const actableShopIds = computed(() => new Set(props.shops.filter((shop) => shop.can_act).map((shop) => shop.id)))
const agentById = computed(() => Object.fromEntries(props.agents.map((agent) => [agent.id, agent])))

const channels = {
    website: { label: ctrans("Website chat"), icon: faGlobe },
    email: { label: ctrans("Email"), icon: faEnvelope },
    whatsapp: { label: ctrans("WhatsApp"), icon: faWhatsapp },
}

const presenceLabel: Record<Presence, string> = {
    online: ctrans("Online"),
    away: ctrans("Away"),
    offline: ctrans("Offline"),
}

const presenceDot: Record<Presence, string> = {
    online: "bg-green-600",
    away: "bg-amber-500",
    offline: "bg-gray-400",
}

const agentOptionsFor = (row: QueueRow) =>
    props.agents
        .filter((agent) => row.shop && agent.shop_ids.includes(row.shop.id) && agent.id !== row.owner?.id)
        .map((agent) => ({
            value: agent.id,
            label: `${agent.name ?? ctrans("Unknown")} · ${presenceLabel[agent.presence]} · ${agent.open}/${agent.max}`,
            disabled: agent.presence !== "online",
        }))

const canAssign = (row: QueueRow) => row.can_assign && !!row.shop && actableShopIds.value.has(row.shop.id)

const assigning = ref<Record<string, boolean>>({})
const assignError = ref<Record<string, string>>({})

const assign = async (row: QueueRow, agentId: number | null) => {
    if (!agentId || assigning.value[row.ulid]) {
        return
    }

    assigning.value = { ...assigning.value, [row.ulid]: true }
    assignError.value = { ...assignError.value, [row.ulid]: "" }

    try {
        await axios.patch(route("grp.org.chat.agents.assign", [props.organisation.slug, row.ulid]), { agent_id: agentId }, { withCredentials: true })
        router.reload({ only: ["stats", "queue", "agents"] })
    } catch (error: any) {
        assignError.value = { ...assignError.value, [row.ulid]: error?.response?.data?.message ?? ctrans("Could not assign this conversation") }
    } finally {
        assigning.value = { ...assigning.value, [row.ulid]: false }
    }
}

const statCards = computed(() => [
    {
        label: ctrans("Waiting · chat & WhatsApp"),
        value: props.stats.waiting_chats,
        note: props.stats.waiting_chats_since ? ctrans("Longest :age", { age: ageOf(props.stats.waiting_chats_since) }) : ctrans("Nobody waiting"),
    },
    {
        label: ctrans("Unanswered · email"),
        value: props.stats.unanswered_emails,
        note: props.stats.unanswered_emails_since ? ctrans("Oldest :age", { age: ageOf(props.stats.unanswered_emails_since) }) : ctrans("Nothing unanswered"),
    },
    {
        label: ctrans("Unassigned"),
        value: props.stats.unassigned,
        note: props.stats.unassigned_busiest_shop
            ? ctrans(":count on :shop", { count: props.stats.unassigned_busiest_shop.count, shop: props.stats.unassigned_busiest_shop.code })
            : ctrans("Everything has an owner"),
    },
    {
        label: ctrans("Answered today"),
        value: props.stats.answered_today,
        note: ctrans("Conversations replied to since midnight"),
    },
])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <Link :href="route('grp.org.chat.supervision', [organisation.slug])">
                <Button type="tertiary" size="s" :label="ctrans('All conversations')" :icon="faInbox" />
            </Link>
        </template>
    </PageHeading>

    <div class="flex flex-col gap-5 p-4 text-[13px] text-gray-800 md:p-6">
        <div class="text-xs text-gray-500">
            {{ ctrans(":count shops · live · updates every 30 seconds", { count: shops.length }) }}
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="card in statCards" :key="card.label" class="rounded-lg border border-gray-200 bg-white px-4 py-3.5">
                <div class="text-xs text-gray-500">{{ card.label }}</div>
                <div class="mt-1 text-2xl font-bold tabular-nums">{{ card.value }}</div>
                <div class="text-xs text-gray-500">{{ card.note }}</div>
            </div>
        </div>

        <div class="flex flex-col items-start gap-5 lg:flex-row">
            <section :aria-label="ctrans('Unanswered queue')" class="w-full min-w-0 flex-1 overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div class="flex flex-wrap items-baseline gap-2 border-b border-gray-200 px-4 py-3.5">
                    <h2 class="text-[15px] font-semibold">{{ ctrans("Unanswered — oldest first") }}</h2>
                    <span class="text-xs text-gray-500">{{ ctrans("Opening a conversation does not mark it answered") }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] border-collapse">
                        <thead class="whitespace-nowrap bg-gray-50 text-left text-xs text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">{{ ctrans("Waiting") }}</th>
                                <th class="px-2 py-2.5 font-semibold">{{ ctrans("Customer · subject") }}</th>
                                <th class="px-2 py-2.5 font-semibold">{{ ctrans("Channel") }}</th>
                                <th class="px-2 py-2.5 font-semibold">{{ ctrans("Shop · language") }}</th>
                                <th class="px-2 py-2.5 font-semibold">{{ ctrans("Owner") }}</th>
                                <th class="py-2.5 pl-2 pr-4"><span class="sr-only">{{ ctrans("Actions") }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in queue" :key="row.channel + row.ulid" class="border-t border-gray-100 align-middle hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3 font-bold tabular-nums" :class="isLongWait(row.waiting_since) ? 'text-orange-800' : ''">
                                    {{ ageOf(row.waiting_since) }}
                                </td>
                                <td class="w-full max-w-0 px-2 py-3">
                                    <Link :href="row.url" class="font-semibold text-gray-900 hover:text-[--app-accent] hover:underline">
                                        {{ row.contact_name }}
                                    </Link>
                                    <span v-if="!row.is_customer" class="ml-1 text-xs text-gray-400">{{ ctrans("guest") }}</span>
                                    <div v-if="row.subject" class="truncate text-gray-500">{{ row.subject }}</div>
                                </td>
                                <td class="whitespace-nowrap px-2 py-3">
                                    <FontAwesomeIcon :icon="channels[row.channel].icon" fixed-width class="mr-1 text-gray-400" aria-hidden="true" />
                                    {{ channels[row.channel].label }}
                                </td>
                                <td class="whitespace-nowrap px-2 py-3">
                                    {{ row.shop?.code ?? "—" }}<span v-if="row.language" class="text-gray-500"> · {{ row.language }}</span>
                                </td>
                                <td class="whitespace-nowrap px-2 py-3">
                                    <template v-if="row.owner">
                                        {{ row.owner.name }}
                                        <div v-if="agentById[row.owner.id] && agentById[row.owner.id].presence !== 'online'" class="text-xs text-gray-500">
                                            {{ presenceLabel[agentById[row.owner.id].presence] }}
                                        </div>
                                    </template>
                                    <span v-else class="text-gray-500">{{ ctrans("Unassigned") }}</span>
                                </td>
                                <td class="py-3 pl-2 pr-4 text-right">
                                    <Select v-if="canAssign(row)" :options="agentOptionsFor(row)" optionLabel="label" optionValue="value" optionDisabled="disabled"
                                        :placeholder="row.owner ? ctrans('Reassign') : ctrans('Assign')" :loading="assigning[row.ulid]"
                                        :emptyMessage="ctrans('No other agent covers this shop')" size="small" class="supervision-assign w-32 text-left" :pt="{ option: { class: 'text-xs' } }"
                                        @update:modelValue="(agentId: number) => assign(row, agentId)" />
                                    <Link v-else :href="row.url">
                                        <Button type="tertiary" size="xs" :label="ctrans('Open')" :icon="faExternalLink" />
                                    </Link>
                                    <div v-if="assignError[row.ulid]" class="mt-1 max-w-[12rem] text-left text-xs text-red-600">{{ assignError[row.ulid] }}</div>
                                </td>
                            </tr>
                            <tr v-if="!queue.length">
                                <td colspan="6" class="px-4 py-10 text-center text-gray-500">{{ ctrans("Every conversation has been answered") }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section :aria-label="ctrans('Agents')" class="w-full shrink-0 rounded-lg border border-gray-200 bg-white lg:sticky lg:top-4 lg:w-80">
                <div class="flex items-baseline border-b border-gray-200 px-4 py-3.5">
                    <h2 class="text-[15px] font-semibold">{{ ctrans("Agents") }}</h2>
                    <span class="ml-auto text-xs text-gray-500">{{ ctrans("open / max") }}</span>
                </div>
                <div v-for="agent in agents" :key="agent.id" class="flex items-center gap-2.5 border-b border-gray-100 px-4 py-3 last:border-b-0">
                    <span class="h-2 w-2 shrink-0 rounded-full" :class="presenceDot[agent.presence]" :title="presenceLabel[agent.presence]" />
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold">{{ agent.name ?? ctrans("Unknown") }}</div>
                        <div class="text-xs text-gray-500">
                            {{ agent.shop_ids.map((id) => shopCode[id]).filter(Boolean).join(", ") }} · {{ presenceLabel[agent.presence] }}
                            <span v-if="agent.on_call" class="whitespace-nowrap">
                                · <FontAwesomeIcon :icon="faPhone" aria-hidden="true" /> {{ ctrans("on a call :age", { age: ageOf(agent.on_call_since) }) }}
                            </span>
                        </div>
                    </div>
                    <span class="font-semibold tabular-nums" :class="{ 'text-gray-500': !agent.open }">{{ agent.open }} / {{ agent.max }}</span>
                </div>
                <div v-if="!agents.length" class="px-4 py-6 text-center text-xs text-gray-500">{{ ctrans("No agents cover these shops") }}</div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.supervision-assign {
    height: 30px;
    font-size: 12px;
}
.supervision-assign :deep(.p-select-label) {
    padding: 0 0.625rem;
    display: flex;
    align-items: center;
    font-size: 12px;
}
.supervision-assign :deep(.p-select-dropdown) {
    width: 1.75rem;
}
</style>
