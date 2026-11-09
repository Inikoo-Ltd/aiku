<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 05 Nov 2026 19:10:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject } from "vue"
import { Line } from "vue-chartjs"
import { Chart as ChartJS, LineElement, PointElement, CategoryScale, LinearScale, Tooltip } from "chart.js"
import PrimeImage from "primevue/image"
import Table from "@/Components/Table/Table.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTruck, faUndo, faCreditCard, faTag, faPhone, faEnvelope, faMapMarkerAlt, faBuilding, faExternalLink, faBullhorn, faHistory, faChartLine, faGlobe } from "@fal"
import { faFacebook, faInstagram, faTiktok, faYoutube, faLinkedin, faPinterest, faXTwitter } from "@fortawesome/free-brands-svg-icons"

ChartJS.register(LineElement, PointElement, CategoryScale, LinearScale, Tooltip)
library.add(faTruck, faUndo, faCreditCard, faTag, faPhone, faEnvelope, faMapMarkerAlt, faBuilding, faExternalLink, faBullhorn, faHistory, faChartLine, faGlobe, faFacebook, faInstagram, faTiktok, faYoutube, faLinkedin, faPinterest, faXTwitter)

type Fact = { source?: string | null; [key: string]: string | null | undefined }
type Change = { label: string; from: string | null; to: string | null }

const props = defineProps<{
    dossier: {
        intel: {
            homepage?: { final_url?: string; platform?: string; banners?: string[]; coupons?: string[] }
            identity?: { name?: string | null; legal_name?: string | null; phones?: string[]; emails?: string[]; address?: string | null; country?: string | null; socials?: Record<string, string>; source?: string | null }
            shipping?: Fact
            minimum_order?: Fact
            returns?: Fact
            payments?: { methods?: string[]; source?: string | null }
            offers?: { text: string; kind: string; source: string | null }[]
            briefing?: { label: string; text: string }[] | string | null
            positioning?: string | null
        }
        numbers: { number_products: number; number_on_sale: number; share_on_sale: number | null; average_discount: number | null; price_min: number | null; price_max: number | null; number_confirmed: number; median_difference: number | null }
        changes: { fetched_at: string; previous_at: string | null; items: Change[] } | null
        timeline: { fetched_at: string; items: Change[] }[]
        on_sale: { date: string; share_on_sale: number | null; average_discount: number | null }[]
        discounted: object
    }
    screenshot: { original: string } | null
    currencyCode: string
    intelFetchedAt: string | null
}>()

const locale = inject("locale", aikuLocaleStructure)
const intel = computed(() => props.dossier.intel ?? {})
const hasIntel = computed(() => !!props.intelFetchedAt)

const money = (amount: number | null) => (amount === null ? "–" : locale.currencyFormat(props.currencyCode, amount))
const percent = (value: number | null) => (value === null ? "–" : `${value}%`)
const signed = (value: number | null) => (value === null ? "–" : `${value > 0 ? "+" : ""}${value}%`)

const keyNumbers = computed(() => [
    { label: ctrans("Products listed"), value: props.dossier.numbers.number_products.toLocaleString() },
    { label: ctrans("Price range"), value: props.dossier.numbers.price_min === null ? "–" : `${money(props.dossier.numbers.price_min)} – ${money(props.dossier.numbers.price_max)}` },
    { label: ctrans("On sale now"), value: percent(props.dossier.numbers.share_on_sale) },
    { label: ctrans("Average discount"), value: percent(props.dossier.numbers.average_discount) },
    { label: ctrans("Confirmed matches"), value: String(props.dossier.numbers.number_confirmed) },
    { label: ctrans("Median difference against us"), value: signed(props.dossier.numbers.median_difference), hint: props.dossier.numbers.median_difference === null ? null : props.dossier.numbers.median_difference > 0 ? ctrans("dearer than us") : ctrans("cheaper than us") },
])

const terms = computed(() => [
    { icon: "fal fa-truck", label: ctrans("Free shipping from"), value: intel.value.shipping?.free_shipping_threshold, source: intel.value.shipping?.source },
    { icon: "fal fa-truck", label: ctrans("Shipping costs"), value: intel.value.shipping?.costs, source: intel.value.shipping?.source },
    { icon: "fal fa-globe", label: ctrans("Ships to"), value: intel.value.shipping?.zones, source: intel.value.shipping?.source },
    { icon: "fal fa-truck", label: ctrans("Delivery times"), value: intel.value.shipping?.delivery_times, source: intel.value.shipping?.source },
    { icon: "fal fa-tag", label: ctrans("Minimum order"), value: intel.value.minimum_order?.value, source: intel.value.minimum_order?.source },
    { icon: "fal fa-undo", label: ctrans("Returns"), value: intel.value.returns?.window, source: intel.value.returns?.source },
    { icon: "fal fa-credit-card", label: ctrans("Payments"), value: intel.value.payments?.methods?.length ? intel.value.payments.methods.join(", ") : null, source: intel.value.payments?.source },
])

const socialIcons: Record<string, string> = { facebook: "fab fa-facebook", instagram: "fab fa-instagram", tiktok: "fab fa-tiktok", youtube: "fab fa-youtube", linkedin: "fab fa-linkedin", pinterest: "fab fa-pinterest", x: "fab fa-x-twitter" }
const offerKinds: Record<string, string> = { banner: ctrans("Banner"), coupon: ctrans("Coupon"), volume: ctrans("Volume discount"), newsletter: ctrans("Newsletter"), loyalty: ctrans("Loyalty"), other: ctrans("Offer") }

const formatDay = (day: string) => new Date(`${day}T00:00:00`).toLocaleDateString([], { day: "numeric", month: "short" })
const onSaleChart = computed(() => ({
    labels: props.dossier.on_sale.map((day) => formatDay(day.date)),
    datasets: [{ label: ctrans("Share of products on sale"), data: props.dossier.on_sale.map((day) => day.share_on_sale), borderColor: "#4b5563", borderWidth: 2, pointRadius: props.dossier.on_sale.length > 30 ? 0 : 3, pointBackgroundColor: "#4b5563", tension: 0.2, spanGaps: true }],
}))
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    animation: false as const,
    interaction: { mode: "index" as const, intersect: false },
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: (item: { parsed: { y: number | null } }) => `${item.parsed.y ?? "–"}%` } } },
    scales: { y: { beginAtZero: true, max: 100, ticks: { callback: (value: string | number) => `${value}%` }, grid: { color: "#f3f4f6" } }, x: { grid: { display: false } } },
}
const hostOf = (url?: string | null) => {
    try {
        return url ? new URL(url).pathname.replace(/\/$/, "") || new URL(url).host : ""
    } catch {
        return url ?? ""
    }
}
</script>

<template>
    <div class="mx-4 mt-4 space-y-4 text-sm">
        <div v-if="!hasIntel" class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-gray-500">
            {{ ctrans("Not read yet. The dossier is filled in on the next weekly refresh, or press Refresh dossier.") }}
        </div>

        <div v-if="intel.briefing" class="rounded-lg border border-gray-200 bg-white px-4 py-3">
            <div class="text-xs text-gray-500">{{ ctrans("Briefing") }} <span v-if="intel.positioning">· {{ intel.positioning }}</span></div>
            <dl v-if="Array.isArray(intel.briefing)" class="mt-2 grid grid-cols-[minmax(0,9rem)_minmax(0,1fr)] gap-x-4 gap-y-1.5">
                <template v-for="(point, index) in intel.briefing" :key="index">
                    <dt class="text-gray-500">{{ point.label }}</dt>
                    <dd class="text-gray-800">{{ point.text }}</dd>
                </template>
            </dl>
            <ul v-else class="mt-2 list-disc space-y-1 pl-5 text-gray-800">
                <li v-for="(sentence, index) in intel.briefing.split(/(?<=[.!?])\s+(?=[A-Z])/)" :key="index">{{ sentence }}</li>
            </ul>
        </div>

        <div v-if="hasIntel" class="rounded-lg border border-gray-200 bg-white px-4 py-3">
            <div class="flex items-center gap-1.5 text-xs text-gray-500"><FontAwesomeIcon icon="fal fa-history" fixed-width /> {{ ctrans("What changed") }}</div>
            <template v-if="dossier.changes?.items.length">
                <div class="mt-1 text-xs text-gray-500">{{ ctrans("Since :date", { date: useFormatTime(dossier.changes.previous_at ?? dossier.changes.fetched_at) }) }}</div>
                <ul class="mt-1 space-y-0.5">
                    <li v-for="(change, index) in dossier.changes.items" :key="index" class="flex flex-wrap gap-x-2">
                        <span class="text-gray-500">{{ change.label }}:</span>
                        <span v-if="change.from" class="text-gray-400 line-through">{{ change.from }}</span>
                        <span v-if="change.to" class="text-gray-800">{{ change.to }}</span>
                        <span v-else class="text-gray-500">{{ ctrans("ended") }}</span>
                    </li>
                </ul>
            </template>
            <div v-else-if="dossier.changes?.previous_at" class="mt-1 text-gray-500">{{ ctrans("No changes since :date", { date: useFormatTime(dossier.changes.previous_at) }) }}</div>
            <div v-else class="mt-1 text-gray-500">{{ ctrans("First dossier, nothing to compare yet") }}</div>
            <div v-if="dossier.timeline.length" class="mt-3 border-t border-gray-100 pt-2">
                <div class="text-xs text-gray-500">{{ ctrans("Earlier") }}</div>
                <div v-for="entry in dossier.timeline" :key="entry.fetched_at" class="mt-1 flex gap-3">
                    <span class="w-36 shrink-0 whitespace-nowrap tabular-nums text-gray-500">{{ useFormatTime(entry.fetched_at) }}</span>
                    <span class="text-gray-700">{{ entry.items.map((item) => `${item.label}: ${item.to ?? ctrans("ended")}`).join(" · ") }}</span>
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-2 lg:col-span-2">
                <PrimeImage v-if="screenshot" :src="screenshot.original" :alt="ctrans('Their homepage')" preview imageClass="w-full rounded object-cover cursor-zoom-in" />
                <div v-else class="flex h-48 items-center justify-center text-gray-400">{{ ctrans("No screenshot yet") }}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                <div class="flex items-center gap-1.5 text-xs text-gray-500"><FontAwesomeIcon icon="fal fa-building" fixed-width /> {{ ctrans("Identity") }}</div>
                <template v-if="intel.identity">
                    <div class="mt-1 font-medium text-gray-800">{{ intel.identity.legal_name || intel.identity.name || "–" }}</div>
                    <div v-if="intel.identity.name && intel.identity.legal_name && intel.identity.name !== intel.identity.legal_name" class="text-xs text-gray-500">{{ ctrans("trading as") }} {{ intel.identity.name }}</div>
                    <div v-for="phone in intel.identity.phones" :key="phone" class="mt-1 flex items-center gap-1.5"><FontAwesomeIcon icon="fal fa-phone" class="text-gray-400" fixed-width /><span class="tabular-nums">{{ phone }}</span></div>
                    <div v-for="email in intel.identity.emails" :key="email" class="mt-1 flex items-center gap-1.5"><FontAwesomeIcon icon="fal fa-envelope" class="text-gray-400" fixed-width /><a :href="`mailto:${email}`" class="font-medium text-gray-700 hover:underline">{{ email }}</a></div>
                    <div v-if="intel.identity.address || intel.identity.country" class="mt-1 flex items-start gap-1.5"><FontAwesomeIcon icon="fal fa-map-marker-alt" class="mt-0.5 text-gray-400" fixed-width /><span>{{ [intel.identity.address, intel.identity.country && !intel.identity.address?.toLowerCase().includes(intel.identity.country.toLowerCase()) ? intel.identity.country : null].filter(Boolean).join(", ") }}</span></div>
                    <div v-if="intel.identity.socials && Object.keys(intel.identity.socials).length" class="mt-2 flex gap-2">
                        <a v-for="(url, name) in intel.identity.socials" :key="name" :href="url" target="_blank" rel="noopener noreferrer" :title="String(name)" class="text-gray-500 hover:text-gray-800"><FontAwesomeIcon :icon="socialIcons[name] ?? 'fal fa-globe'" fixed-width /></a>
                    </div>
                </template>
                <div v-else class="mt-1 text-gray-500">{{ ctrans("Nothing read yet") }}</div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 rounded-lg border border-gray-200 bg-white px-4 py-3 sm:grid-cols-3 lg:grid-cols-6">
            <div v-for="number in keyNumbers" :key="number.label">
                <div class="text-xs text-gray-500">{{ number.label }}</div>
                <div class="tabular-nums text-gray-800">{{ number.value }}</div>
                <div v-if="number.hint" class="text-xs text-gray-500">{{ number.hint }}</div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                <div class="flex items-center gap-1.5 text-xs text-gray-500"><FontAwesomeIcon icon="fal fa-bullhorn" fixed-width /> {{ ctrans("Site-wide offers") }}</div>
                <ul v-if="intel.offers?.length" class="mt-1 space-y-1">
                    <li v-for="(offer, index) in intel.offers" :key="index">
                        <span class="mr-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ offerKinds[offer.kind] ?? offer.kind }}</span>
                        <span class="text-gray-800">{{ offer.text }}</span>
                        <a v-if="offer.source" :href="offer.source" target="_blank" rel="noopener noreferrer" class="ml-1 text-xs font-medium text-gray-500 hover:underline">{{ hostOf(offer.source) }} <FontAwesomeIcon icon="fal fa-external-link" /></a>
                    </li>
                </ul>
                <div v-else class="mt-1 text-gray-500">{{ hasIntel ? ctrans("No site-wide offer found on their pages") : ctrans("Not read yet") }}</div>
                <div v-if="intel.homepage?.banners?.length" class="mt-2 border-t border-gray-100 pt-2 text-xs text-gray-500">
                    {{ ctrans("Announcements on their homepage") }}: {{ intel.homepage.banners.join(" · ") }}
                </div>
                <div v-if="intel.homepage?.coupons?.length" class="mt-1 text-xs text-gray-500">{{ ctrans("Codes seen") }}: <span class="tabular-nums">{{ intel.homepage.coupons.join(", ") }}</span></div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                <div class="flex items-center gap-1.5 text-xs text-gray-500"><FontAwesomeIcon icon="fal fa-chart-line" fixed-width /> {{ ctrans("Share of products on sale, last 90 days") }}</div>
                <div v-if="dossier.on_sale.length > 1" class="mt-2 h-44"><Line :aria-label="ctrans('Share of products on sale over time')" :data="onSaleChart" :options="chartOptions" /></div>
                <div v-else class="mt-1 text-gray-500">{{ ctrans("The line starts after a few days of feed reads") }}</div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
            <div class="flex items-center gap-1.5 text-xs text-gray-500"><FontAwesomeIcon icon="fal fa-truck" fixed-width /> {{ ctrans("Shipping and terms") }}</div>
            <div class="mt-1 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                <div v-for="term in terms" :key="term.label">
                    <div class="text-xs text-gray-500">{{ term.label }}</div>
                    <div v-if="term.value" class="text-gray-800">
                        {{ term.value }}
                        <a v-if="term.source" :href="term.source" target="_blank" rel="noopener noreferrer" class="ml-1 whitespace-nowrap text-xs font-medium text-gray-500 hover:underline">{{ hostOf(term.source) }} <FontAwesomeIcon icon="fal fa-external-link" /></a>
                    </div>
                    <div v-else class="text-gray-400">{{ hasIntel ? ctrans("Not found on their pages") : "–" }}</div>
                </div>
            </div>
        </div>

        <div>
            <div class="flex items-center gap-1.5 text-xs text-gray-500"><FontAwesomeIcon icon="fal fa-tag" fixed-width /> {{ ctrans("Most discounted right now") }}</div>
            <Table :resource="dossier.discounted" name="discounted" class="mt-1">
                <template #cell(name)="{ item }">
                    <img v-if="item.image_url" :src="item.image_url" alt="" loading="lazy" class="float-left mr-2 h-8 w-8 rounded object-cover" />
                    <a v-if="item.url" :href="item.url" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-700 hover:underline">{{ item.name }}</a>
                    <span v-else class="font-medium text-gray-700">{{ item.name }}</span>
                </template>
                <template #cell(was_price)="{ item }"><span class="tabular-nums text-gray-400 line-through">{{ locale.currencyFormat(currencyCode, item.was_price) }}</span></template>
                <template #cell(price)="{ item }"><span class="tabular-nums">{{ locale.currencyFormat(currencyCode, item.price) }}</span></template>
                <template #cell(discount)="{ item }"><span class="tabular-nums">{{ item.discount }}%</span></template>
            </Table>
        </div>
    </div>
</template>
