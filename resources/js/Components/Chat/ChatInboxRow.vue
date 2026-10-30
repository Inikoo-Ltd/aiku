<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 30 Oct 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faStar, faStoreAlt, faEye, faPaperclip } from "@fal"
import { faEllipsisVertical, faBan, faStar as faStarSolid } from "@fortawesome/free-solid-svg-icons"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { waitingLeft } from "@/Composables/useChatWaitingForCustomer"
import type { Contact } from "@/types/Chat/chat"

/**
 * One conversation in the Customer Inbox list, its own component so a change elsewhere on the page
 * does not re-render every row and its tooltips (INI-145). Page state comes from Inbox.vue's
 * "chatInboxRow" provide.
 */
defineProps<{
    c: Contact
    isSelected: boolean
}>()

const ctx = inject<Record<string, any>>("chatInboxRow")!

/** Time-based labels read the page's minute clock, as Vue does not track the time itself. */
const waitingLabel = (at: string) => ctx.minuteClock && waitingLeft(at)
const promiseLabel = (at: string) => ctx.minuteClock && ctx.formatPromiseTime(at)
</script>

<template>
    <div>
        <div v-if="ctx.isMailLayout"
            class="group relative flex items-center gap-3 h-11 pl-2 pr-3 border-b border-gray-100 cursor-pointer text-[13px] hover:z-10 hover:shadow-[0_1px_3px_rgba(0,0,0,0.12)] max-md:h-auto max-md:flex-wrap max-md:gap-x-2 max-md:gap-y-0.5 max-md:py-2"
            :class="c.unread && !ctx.onlyClosed ? 'bg-white' : 'bg-gray-50'"
            @click="ctx.onRowClick(c)"
            @pointerdown="ctx.startLongPress(c, $event)"
            @pointermove="ctx.moveLongPress"
            @pointerup="ctx.cancelLongPress"
            @pointerleave="ctx.cancelLongPress"
            @pointercancel="ctx.cancelLongPress"
            @contextmenu.prevent="ctx.onRowContextMenu(c, $event)">
            <div v-if="ctx.isAssigning[c.ulid] || ctx.isSpamming[c.ulid]"
                class="absolute inset-0 bg-black/30 flex items-center justify-center z-10">
                <LoadingIcon class="w-6 h-6 text-white" />
            </div>

            <button v-if="!ctx.trashView && !ctx.isReadOnly" type="button"
                v-tooltip="c.is_highlighted ? ctrans('Remove highlight') : ctrans('Highlight')"
                :aria-label="c.is_highlighted ? ctrans('Remove highlight') : ctrans('Highlight')"
                class="shrink-0 w-7 h-7 flex items-center justify-center rounded-full hover:bg-gray-200"
                :class="c.is_highlighted ? 'text-amber-400' : 'text-gray-400'"
                @click.stop="ctx.toggleHighlight(c)" @pointerdown.stop>
                <FontAwesomeIcon :icon="c.is_highlighted ? faStarSolid : faStar" class="text-sm" fixed-width />
            </button>

            <span class="shrink-0 w-28 sm:w-48 min-w-0 flex items-center gap-1.5 max-md:w-auto max-md:flex-1">
                <span class="min-w-0 truncate" :class="c.unread && !ctx.onlyClosed ? 'font-medium text-gray-900' : 'text-gray-700'">
                    {{ capitalize(c.name) }}
                </span>
                <span v-if="c.unread && !ctx.onlyClosed"
                    v-tooltip="ctrans(':count unread messages', { count: String(c.unread) })"
                    class="shrink-0 inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[--app-accent] px-1.5 text-[11px] font-semibold tabular-nums text-[--app-accent-text]">
                    {{ c.unread }}
                </span>
            </span>

            <span class="flex-1 min-w-0 flex items-center gap-1.5 overflow-hidden whitespace-nowrap max-md:order-last max-md:basis-full max-md:flex-wrap max-md:gap-y-0.5 max-md:pl-9">
                <span v-if="!ctx.selectedShopId && (c.shop?.code || c.shop?.name)" v-tooltip="c.shop.name"
                    class="hidden sm:inline shrink-0 rounded bg-gray-100 px-1.5 text-[11px] font-semibold text-gray-600">
                    {{ c.shop.code ?? c.shop.name }}
                </span>
                <span v-if="c.urgent === 'delivery_instruction'" v-tooltip="ctrans('Tells us how to deliver an order that has not arrived yet, such as a safe place. First in the queue until answered.')"
                    class="shrink-0 rounded bg-orange-500 px-1.5 text-[11px] font-semibold text-white">
                    {{ ctrans("Delivery instruction") }}
                </span>
                <span v-else-if="c.urgent" v-tooltip="ctrans('Asks to cancel an order or change its delivery address. First in the queue until answered.')"
                    class="shrink-0 rounded bg-red-600 px-1.5 text-[11px] font-semibold text-white">
                    {{ c.urgent === 'cancel_order' ? ctrans("Cancel order") : ctrans("Change address") }}
                </span>
                <span v-if="c.waiting_until" v-tooltip="ctrans('Waiting for the customer to write back. Closes by itself if they write nothing.')"
                    class="shrink-0 rounded bg-amber-50 px-1.5 text-[11px] font-medium text-amber-700">
                    ⏳ {{ waitingLabel(c.waiting_until) }}
                </span>
                <span v-if="(ctx.mailTab === 'all' || !ctx.isEmailMailbox) && c.is_unanswered && c.status !== 'closed' && !c.waiting_until"
                    v-tooltip="ctrans('The customer wrote last and nobody has replied yet, even if a colleague opened it.')"
                    class="shrink-0 rounded bg-amber-50 px-1.5 text-[11px] font-medium text-amber-700">
                    {{ ctrans("Unanswered") }}
                </span>
                <span v-for="label in c.labels ?? []" :key="label.id"
                    class="shrink-0 rounded px-1.5 text-[11px] font-medium" :style="ctx.labelChipStyle(label)">
                    {{ label.name }}
                </span>
                <span v-if="ctx.emailSubject(c)" class="shrink truncate max-md:basis-full"
                    :class="c.unread && !ctx.onlyClosed ? 'font-medium text-gray-900' : 'text-gray-800'">
                    {{ ctx.emailSubject(c) }}
                </span>
                <span class="min-w-0 truncate text-gray-500 max-md:basis-full max-md:text-xs">
                    <span v-if="ctx.emailSubject(c)" class="max-md:hidden">— </span>{{ c.lastMessage }}
                </span>
                <span v-if="c.promise" v-tooltip="ctrans('Told while we were closed that we would reply when we open. Not answered yet.')"
                    class="shrink-0 rounded px-1.5 text-[11px] font-medium"
                    :class="c.promise.overdue ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700'">
                    {{ ctrans("Promised :time", { time: promiseLabel(c.promise.at) }) }}
                </span>
                <button v-if="c.has_attachments" type="button" :aria-label="ctrans('Pictures and files')"
                    class="shrink-0 flex h-6 w-6 items-center justify-center rounded text-pink-500 hover:bg-pink-50 hover:text-pink-700"
                    @click.stop="ctx.openFiles(c)" @pointerdown.stop @mouseenter="ctx.filesHoverCard?.show($event, c.ulid)" @mouseleave="ctx.filesHoverCard?.hide()">
                    <FontAwesomeIcon :icon="faPaperclip" class="text-sm" fixed-width />
                </button>
                <span v-if="ctx.writesInOtherLanguage(c)" v-tooltip="ctrans('Customer language')"
                    class="hidden sm:inline shrink-0 rounded border border-gray-300 px-1.5 text-[11px] uppercase text-gray-600">
                    {{ c.language }}
                </span>
            </span>

            <button v-if="!ctx.isReadOnly" type="button" :aria-label="ctrans('More actions')"
                class="max-md:absolute max-md:top-1/2 max-md:right-2 max-md:-translate-y-1/2 z-30 shrink-0 w-7 h-7 flex items-center justify-center rounded-full bg-white text-gray-500 ring-1 ring-gray-200 hover:bg-gray-100 hover:text-gray-800 opacity-0 group-hover:opacity-100 transition-opacity"
                :class="{ '!opacity-100': ctx.openMenuUlid === c.ulid }"
                @click.stop="ctx.toggleRowMenu(c.ulid, $event)" @pointerdown.stop>
                <FontAwesomeIcon :icon="faEllipsisVertical" class="text-sm" fixed-width />
            </button>

            <span v-tooltip="c.agent?.name ? ctrans('Owner: :name', { name: c.agent.name }) : ctrans('Unassigned')"
                class="shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold"
                :class="c.agent?.name
                    ? 'bg-[--app-accent-soft] text-[--app-accent-deep]'
                    : 'border border-dashed border-gray-400 text-gray-500'">
                {{ c.agent?.name ? ctx.ownerInitials(c.agent.name) : "–" }}
            </span>

            <span class="shrink-0 w-24 whitespace-nowrap text-right text-xs group-hover:invisible md:group-hover:visible"
                :class="c.unread && !ctx.onlyClosed ? 'font-medium text-gray-900' : 'text-gray-500'">
                {{ c.lastMessageTime }}
            </span>
        </div>

        <div v-else class="group relative flex items-center gap-2 px-3 py-2 border-b cursor-pointer transition-colors"
            :class="isSelected ? '' : 'hover:bg-gray-50'"
            :style="isSelected ? ctx.selectedItemStyle : {}"
            @click="ctx.onRowClick(c)"
            @pointerdown="ctx.startLongPress(c, $event)"
            @pointermove="ctx.moveLongPress"
            @pointerup="ctx.cancelLongPress"
            @pointerleave="ctx.cancelLongPress"
            @pointercancel="ctx.cancelLongPress"
            @contextmenu.prevent="ctx.onRowContextMenu(c, $event)">
            <div v-if="ctx.isAssigning[c.ulid] || ctx.isSpamming[c.ulid]"
                class="absolute inset-0 bg-black/30 flex items-center justify-center z-10">
                <LoadingIcon class="w-8 h-8 text-white" />
            </div>

            <button v-if="!ctx.isReadOnly" type="button"
                class="absolute top-1/2 right-2 -translate-y-1/2 z-30 w-7 h-7 flex items-center justify-center rounded-full bg-white text-gray-500 shadow-md ring-1 ring-gray-200 hover:bg-gray-100 hover:text-gray-800 opacity-0 group-hover:opacity-100 transition-opacity"
                :class="{ '!opacity-100': ctx.openMenuUlid === c.ulid }"
                @click.stop="ctx.toggleRowMenu(c.ulid, $event)">
                <FontAwesomeIcon :icon="faEllipsisVertical" class="text-sm" fixed-width />
            </button>

            <span v-tooltip="ctx.channelBadge(c.channel).label"
                class="shrink-0 flex h-9 w-9 items-center justify-center self-start rounded-full"
                :class="ctx.channelBadge(c.channel).class">
                <FontAwesomeIcon :icon="ctx.channelBadge(c.channel).icon" class="text-lg" fixed-width aria-hidden="true" />
            </span>

            <div class="flex-1 min-w-0 flex flex-col gap-0.5">
                <div class="flex items-center justify-between gap-2">
                    <!-- Only the guests are marked. A customer is already shown
                         by their name being a link through to their record, and
                         a badge on every other row says nothing. -->
                    <span v-if="!c.webUser?.customer_id"
                        class="shrink-0 text-[9px] px-1 py-0.5 border border-blue-300 text-blue-400 leading-none">
                        G
                    </span>
                    <img v-if="(c as any).country_code" :src="`/flags/${(c as any).country_code.toLowerCase()}.png`"
                        :alt="(c as any).country_code" v-tooltip="(c as any).country_code" class="shrink-0 h-3 w-auto" />
                    <span class="min-w-0 text-sm truncate" :class="c.unread && !ctx.onlyClosed ? 'font-medium text-gray-900' : 'text-gray-700'">{{ capitalize(c.name) }}</span>
                    <button type="button" v-tooltip="ctrans('Preview')" :aria-label="ctrans('Preview')"
                        class="shrink-0 -my-1 flex h-6 w-6 items-center justify-center rounded text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700"
                        @click.stop="ctx.previewContact = c" @pointerdown.stop>
                        <FontAwesomeIcon :icon="faEye" class="text-xs" fixed-width aria-hidden="true" />
                    </button>
                    <span class="flex-1" />
                    <span class="text-[10px] text-gray-500 shrink-0">
                        {{ c.lastMessageTime }}
                        <span v-if="c.lastMessageAge" class="text-gray-400">({{ c.lastMessageAge }})</span>
                    </span>
                </div>
                <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] text-gray-400">
                    <span v-for="label in c.labels ?? []" :key="label.id"
                        class="shrink-0 max-w-full truncate rounded px-1 font-medium" :style="ctx.labelChipStyle(label)">
                        {{ label.name }}
                    </span>
                    <span v-if="!ctx.selectedShopId && (c.shop?.code || c.shop?.name)" v-tooltip="c.shop.name"
                        class="flex items-center gap-1 shrink-0 rounded bg-gray-100 px-1 font-semibold text-gray-600">
                        <FontAwesomeIcon :icon="faStoreAlt" class="text-[9px] shrink-0" fixed-width />
                        {{ c.shop.code ?? c.shop.name }}
                    </span>
                    <span v-if="ctx.writesInOtherLanguage(c)" v-tooltip="ctrans('Customer language')"
                        class="shrink-0 max-w-full rounded bg-gray-100 px-1 font-semibold uppercase text-gray-600">
                        {{ c.language }}
                    </span>
                    <span v-if="c.is_unanswered && c.status !== 'closed' && !c.waiting_until"
                        v-tooltip="ctrans('The customer wrote last and nobody has replied yet, even if a colleague opened it.')"
                        class="shrink-0 max-w-full rounded bg-amber-50 px-1 font-medium text-amber-700">
                        {{ ctrans("Unanswered") }}
                    </span>
                    <span v-if="c.agent?.name" class="truncate">
                        {{ c.agent.name.split(' ')[0] }}
                    </span>
                    <span v-if="c.customer_suggestion?.customer && !c.webUser?.customer_id" v-tooltip="c.customer_suggestion.label"
                        class="shrink-0 max-w-full truncate rounded bg-amber-50 px-1 font-medium text-amber-700">
                        {{ ctrans("Probably") }} {{ c.customer_suggestion.customer.name }}
                    </span>
                    <span v-if="c.noise" v-tooltip="c.noise.note"
                        class="shrink-0 max-w-full rounded px-1 font-medium"
                        :class="c.noise.automatic ? 'bg-gray-100 text-gray-600' : 'bg-amber-50 text-amber-700'">
                        {{ c.noise.automatic ? ctrans("Put aside automatically") : ctrans("Possible noise") }}: {{ c.noise.label }}
                    </span>
                    <span v-if="c.claim" v-tooltip="ctrans('Asked out of hours for the order number, the items and photos. This is what has arrived since.')"
                        class="shrink-0 max-w-full truncate rounded px-1 font-medium"
                        :class="c.claim.order_reference && c.claim.photos ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
                        {{ ctrans("Claim") }}:
                        {{ c.claim.order_reference ? c.claim.order_reference : ctrans("no order number") }}
                        ·
                        {{ c.claim.photos ? ctrans(":count photos", { count: c.claim.photos }) : ctrans("no photos") }}
                    </span>
                    <span v-for="task in c.open_tasks" :key="task.reference"
                        v-tooltip="ctrans(':reference for :who', { reference: task.reference, who: task.who })"
                        class="shrink-0 max-w-full truncate rounded bg-amber-50 px-1 font-medium text-amber-700">
                        {{ ctrans("Waiting") }}: {{ task.subject }}
                    </span>
                    <span v-if="c.metadata?.ds_kind === 'integration'" v-tooltip="ctrans('Needs technical help with their store or marketplace connection')"
                        class="shrink-0 max-w-full truncate rounded bg-sky-50 px-1 font-medium text-sky-700">
                        {{ ctrans("Integration") }}
                    </span>
                    <span v-else-if="c.metadata?.ds_kind === 'documents'" v-tooltip="ctrans('Asks for a compliance document, an invoice or customs paperwork')"
                        class="shrink-0 max-w-full truncate rounded bg-violet-50 px-1 font-medium text-violet-700">
                        {{ ctrans("Documents") }}
                    </span>
                    <span v-if="c.urgent === 'delivery_instruction'" v-tooltip="ctrans('Tells us how to deliver an order that has not arrived yet, such as a safe place. First in the queue until answered.')"
                        class="shrink-0 max-w-full truncate rounded bg-orange-500 px-1 font-semibold text-white">
                        {{ ctrans("Delivery instruction") }}
                    </span>
                    <span v-else-if="c.urgent" v-tooltip="ctrans('Asks to cancel an order or change its delivery address. First in the queue until answered.')"
                        class="shrink-0 max-w-full truncate rounded bg-red-600 px-1 font-semibold text-white">
                        {{ c.urgent === 'cancel_order' ? ctrans("Cancel order") : ctrans("Change address") }}
                    </span>
                    <span v-if="c.waiting_until" v-tooltip="ctrans('Waiting for the customer to write back. Closes by itself if they write nothing.')"
                        class="shrink-0 max-w-full truncate rounded bg-amber-50 px-1 font-medium text-amber-700">
                        ⏳ {{ waitingLabel(c.waiting_until) }}
                    </span>
                    <span v-if="c.promise" v-tooltip="ctrans('Told while we were closed that we would reply when we open. Not answered yet.')"
                        class="shrink-0 max-w-full truncate rounded px-1 font-medium"
                        :class="c.promise.overdue ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700'">
                        {{ ctrans("Promised :time", { time: promiseLabel(c.promise.at) }) }}
                    </span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-xs truncate flex-1 leading-snug" :class="c.unread && !ctx.onlyClosed ? 'font-medium text-gray-900' : 'text-gray-500'">{{ c.lastMessage }}</span>
                    <button v-if="c.has_attachments" type="button" :aria-label="ctrans('Pictures and files')"
                        class="shrink-0 flex items-center justify-center text-pink-500 hover:text-pink-700"
                        @click.stop="ctx.openFiles(c)" @pointerdown.stop @mouseenter="ctx.filesHoverCard?.show($event, c.ulid)" @mouseleave="ctx.filesHoverCard?.hide()">
                        <FontAwesomeIcon :icon="faPaperclip" class="text-[11px]" fixed-width />
                    </button>
                    <button v-if="!c.webUser?.customer_id && !c.is_spam && !ctx.trashView && !ctx.onlyClosed && !ctx.isReadOnly" type="button"
                        :disabled="ctx.isSpamming[c.ulid]"
                        v-tooltip="ctrans('Report spam')"
                        class="shrink-0 flex items-center justify-center text-gray-400 hover:text-red-500 transition-colors disabled:opacity-50"
                        @click.stop="ctx.markSpam(c, true)">
                        <FontAwesomeIcon :icon="faBan" class="text-[11px]" fixed-width />
                    </button>
                    <button v-if="!ctx.trashView && !ctx.isReadOnly" type="button"
                        v-tooltip="c.is_highlighted ? ctrans('Remove highlight') : ctrans('Highlight')"
                        class="shrink-0 flex items-center justify-center transition-opacity"
                        :class="c.is_highlighted ? 'text-amber-400 opacity-100' : 'text-gray-400 opacity-0 group-hover:opacity-100 hover:text-amber-500'"
                        @click.stop="ctx.toggleHighlight(c)">
                        <FontAwesomeIcon :icon="c.is_highlighted ? faStarSolid : faStar" class="text-[11px]" fixed-width />
                    </button>
                    <span v-if="c.unread && !ctx.onlyClosed"
                        v-tooltip="ctrans('Unread messages')"
                        class="shrink-0 min-w-[16px] h-4 px-1 text-[9px] font-semibold leading-4 text-white rounded-full text-center bg-red-500">
                        {{ c.unread }}
                    </span>
                </div>
            </div>
        </div>

        <div v-if="ctx.errorPerContact[c.ulid]"
            class="px-3 py-1 text-xs text-red-600 bg-red-50 border-b">
            {{ ctx.errorPerContact[c.ulid] }}
        </div>
    </div>
</template>
