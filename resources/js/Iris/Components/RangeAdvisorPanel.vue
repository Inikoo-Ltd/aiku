<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted, ref, watch } from "vue"
import axios from "axios"
import { get } from "lodash-es"
import Tabs from "primevue/tabs"
import TabList from "primevue/tablist"
import Tab from "primevue/tab"
import TabPanels from "primevue/tabpanels"
import TabPanel from "primevue/tabpanel"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faShoppingBasket, faCheck, faTimes } from "@far"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { ctrans } from "@/Composables/useTrans"
import { getStyles } from "@/Composables/styles"
import Button from "@/Components/Elements/Buttons/Button.vue"
import MerlinIcon from "@/Iris/Components/MerlinIcon.vue"
import RangeAdvisorProductCard from "@/Iris/Components/RangeAdvisorProductCard.vue"
import { ProductResource } from "@/types/Iris/Products"
library.add(faShoppingBasket, faCheck, faTimes)

type Card = ProductResource & { assistant_reason: string, quantity_ordered?: number }
type Pack = { family: { id: number, name: string, url: string | null }, items: Card[] }
type ListKey = "missing" | "forgotten" | "starter_packs" | "new_arrivals"
type List = { key: ListKey, items?: Card[], packs?: Pack[] }
type Panel = { shop_type: string | null, country: string | null, has_orders: boolean, remembered: string | null, lists: List[] }

const props = defineProps<{
    assistantName: string
    fieldValue?: Record<string, any>
    screenType: "mobile" | "tablet" | "desktop"
}>()

const emit = defineEmits<{
    (event: "loaded", hasProducts: boolean, hasForgotten: boolean): void
    (event: "tellShop"): void
}>()

const layout: any = inject("layout", retinaLayoutStructure)

const panel = ref<Panel | null>(null)
const isLoading = ref(true)
const activeList = ref<ListKey | null>(null)
const cardsVersion = ref(0)
const adding = ref<string | null>(null)
const addErrors = ref<Record<string, string>>({})
const seenLists = new Set<ListKey>()

const jsonUrl = (name: string, fallback: string): string => route().has(name) ? route(name) : fallback
const shopProfileUrl = () => jsonUrl("iris.json.shopping_assistant.shop_profile.delete", "/json/shopping-assistant/shop-profile")
const panelUrl = () => jsonUrl("iris.json.shopping_assistant.panel", "/json/shopping-assistant/panel")
const eventsUrl = () => jsonUrl("iris.json.shopping_assistant.panel.events.store", "/json/shopping-assistant/panel/events")

const listLabel = (key: ListKey): string => ({
    missing: panel.value?.has_orders ? ctrans("Missing from your range") : ctrans("Best sellers for shops like yours"),
    forgotten: ctrans("Forgotten favourites"),
    starter_packs: ctrans("Starter packs"),
    new_arrivals: ctrans("New for your shop"),
}[key])

const cardsOf = (list: List): Card[] => list.items ?? (list.packs ?? []).flatMap((pack) => pack.items)

const activeListItems = computed<Card[]>(() => panel.value?.lists.find((list) => list.key === activeList.value)?.items ?? [])

const shopLine = computed(() => {
    const details = [panel.value?.shop_type, panel.value?.country].filter(Boolean).join(", ")
    return panel.value?.shop_type ? ctrans("Picked for your shop: :details.", { details }) : ""
})

const load = async () => {
    try {
        const { data } = await axios.get(panelUrl())
        panel.value = data
        cardsVersion.value++
        if (!activeList.value || !data.lists.some((list: List) => list.key === activeList.value)) {
            activeList.value = data.lists[0]?.key ?? null
        }
    } catch {
        if (isLoading.value) {
            panel.value = null
        }
    } finally {
        isLoading.value = false
    }
}

const isForgetting = ref(false)
const forgetError = ref("")

const forget = async () => {
    if (isForgetting.value || !panel.value) {
        return
    }
    isForgetting.value = true
    forgetError.value = ""
    try {
        await axios.delete(shopProfileUrl())
        panel.value.remembered = null
    } catch {
        forgetError.value = ctrans("Something went wrong. Please try again.")
    } finally {
        isForgetting.value = false
    }
}

const postEvent = (body: Record<string, unknown>) => {
    const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? "")
    fetch(eventsUrl(), {
        method: "POST",
        keepalive: true,
        headers: { "Content-Type": "application/json", "Accept": "application/json", "X-XSRF-TOKEN": token },
        body: JSON.stringify(body),
    }).catch(() => {})
}

const tabsElement = ref<HTMLElement | null>(null)
const isInView = ref(false)
let viewObserver: IntersectionObserver | null = null

watch(tabsElement, (element) => {
    viewObserver?.disconnect()
    if (!element || isInView.value) {
        return
    }
    if (typeof IntersectionObserver === "undefined") {
        isInView.value = true
        return
    }
    viewObserver = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
            isInView.value = true
            viewObserver?.disconnect()
        }
    })
    viewObserver.observe(element)
})

onBeforeUnmount(() => viewObserver?.disconnect())

watch([activeList, isInView], ([key, inView]) => {
    const list = panel.value?.lists.find((candidate) => candidate.key === key)
    if (!inView || !key || !list || seenLists.has(key)) {
        return
    }
    seenLists.add(key)
    postEvent({ type: "shown", list: key, product_ids: cardsOf(list).map((card) => card.id) })
})

const recordClick = (event: MouseEvent, key: ListKey, card: Card) => {
    const target = event.target as HTMLElement | null
    if (!target?.closest("a") || target.closest("button")) {
        return
    }
    postEvent({ type: "clicked", list: key, product_id: card.id })
}

const notInBasket = (cards: Card[]): Card[] => cards.filter((card) => !card.quantity_ordered)

const freshCards = (ids: number[]): Card[] =>
    (panel.value?.lists ?? []).flatMap(cardsOf).filter((card, index, all) => ids.includes(card.id) && all.findIndex((other) => other.id === card.id) === index)

const addAll = async (addKey: string, cards: Card[]) => {
    if (!notInBasket(cards).length || adding.value !== null) {
        return
    }
    adding.value = addKey
    addErrors.value = { ...addErrors.value, [addKey]: "" }
    try {
        await load()
        const lines = notInBasket(freshCards(cards.map((card) => card.id)))
        if (!lines.length) {
            return
        }
        const { data } = await axios.post(route("iris.models.transactions.store"), {
            lines: lines.map((card) => ({ product_id: card.id, quantity: 1 })),
        })
        if (data.failed) {
            addErrors.value = { ...addErrors.value, [addKey]: ctrans("Some products could not be added to your basket.") }
        }
    } catch {
        addErrors.value = { ...addErrors.value, [addKey]: ctrans("Could not add the products. Please try again.") }
    } finally {
        layout?.reload_handle?.()
        await load()
        adding.value = null
    }
}

onMounted(async () => {
    await load()
    emit("loaded", !!panel.value?.lists.length, !!panel.value?.lists.some((list) => list.key === "forgotten"))
})
</script>

<template>
    <section v-if="isLoading || panel?.lists.length || panel?.remembered || (panel && !panel.shop_type)" class="range-panel">
        <div v-if="isLoading" class="flex flex-col gap-3" aria-hidden="true">
            <div class="skeleton h-24 rounded-xl" />
            <div class="skeleton h-10 w-2/3 rounded-full" />
            <div class="range-grid">
                <div v-for="index in 4" :key="index" class="skeleton h-72 rounded-xl" />
            </div>
        </div>

        <template v-else-if="panel">
            <header class="range-header">
                <span class="range-header-badge" aria-hidden="true">
                    <MerlinIcon themed class="range-header-icon" />
                </span>
                <div class="range-header-text">
                    <h2 v-if="panel.lists.length" class="range-title">{{ ctrans(":name noticed", { name: assistantName }) }}</h2>
                    <p v-if="panel.lists.length" class="range-subtitle">
                        {{ shopLine }}
                        {{ ctrans("In stock now, at your trade prices.") }}
                    </p>
                    <div v-if="panel.remembered" class="range-remembered">
                        <span class="range-remembered-label">{{ ctrans(":name remembers:", { name: assistantName }) }}</span>
                        <span class="range-remembered-text">{{ panel.remembered }}</span>
                        <Button type="tertiary" size="xs" :label="ctrans('Forget this')" :loading="isForgetting" :disabled="isForgetting" @click="forget">
                            <template #icon>
                                <FontAwesomeIcon icon="far fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </div>
                    <p v-if="forgetError" class="mt-1 text-xs text-red-500">{{ forgetError }}</p>
                </div>
                <Button
                    v-if="!panel.shop_type"
                    type="tertiary"
                    size="s"
                    class="range-tell"
                    :label="ctrans('Tell :name what kind of shop you run to get better picks', { name: assistantName })"
                    @click="emit('tellShop')"
                >
                    <template #icon>
                        <MerlinIcon themed />
                    </template>
                </Button>
            </header>

            <div v-if="panel.lists.length && activeList" ref="tabsElement" class="range-body">
                <Tabs v-model:value="activeList" scrollable lazy>
                    <div class="range-toolbar">
                        <TabList class="range-tabs">
                            <Tab v-for="list in panel.lists" :key="list.key" :value="list.key">
                                <span class="range-tab-label">{{ listLabel(list.key) }}</span>
                                <span class="range-count">{{ cardsOf(list).length }}</span>
                            </Tab>
                        </TabList>
                        <div v-if="activeList !== 'starter_packs'" class="range-toolbar-action">
                            <Button
                                type="tertiary"
                                size="s"
                                :label="notInBasket(activeListItems).length ? ctrans('Add all to basket') : ctrans('All in your basket')"
                                :disabled="!notInBasket(activeListItems).length || adding !== null"
                                :loading="adding === activeList"
                                @click="addAll(activeList, activeListItems)"
                            >
                                <template #icon>
                                    <FontAwesomeIcon :icon="notInBasket(activeListItems).length ? 'far fa-shopping-basket' : 'far fa-check'" class="text-green-600" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <p v-if="addErrors[activeList]" class="text-xs text-red-500">{{ addErrors[activeList] }}</p>
                        </div>
                    </div>
                    <TabPanels>
                        <TabPanel v-for="list in panel.lists" :key="list.key" :value="list.key">
                            <template v-if="list.key === 'starter_packs'">
                                <div class="flex flex-col gap-3">
                                    <div v-for="pack in list.packs" :key="pack.family.id" class="range-pack">
                                        <div class="range-pack-header">
                                            <div class="min-w-0">
                                                <a v-if="pack.family.url" :href="pack.family.url" class="range-pack-name">{{ pack.family.name }}</a>
                                                <span v-else class="range-pack-name">{{ pack.family.name }}</span>
                                                <p class="range-pack-meta">{{ ctrans("Best sellers from a family you don’t stock yet") }}</p>
                                            </div>
                                            <Button
                                                type="tertiary"
                                                size="xs"
                                                class="shrink-0"
                                                :label="notInBasket(pack.items).length ? ctrans('Add pack to basket') : ctrans('All in your basket')"
                                                :disabled="!notInBasket(pack.items).length || adding !== null"
                                                :loading="adding === `pack-${pack.family.id}`"
                                                @click="addAll(`pack-${pack.family.id}`, pack.items)"
                                            >
                                                <template #icon>
                                                    <FontAwesomeIcon :icon="notInBasket(pack.items).length ? 'far fa-shopping-basket' : 'far fa-check'" class="text-green-600" fixed-width aria-hidden="true" />
                                                </template>
                                            </Button>
                                        </div>
                                        <p v-if="addErrors[`pack-${pack.family.id}`]" class="mb-2 text-xs text-red-500">{{ addErrors[`pack-${pack.family.id}`] }}</p>
                                        <div class="range-grid">
                                            <div v-for="card in pack.items" :key="`${card.id}-${cardsVersion}`" @click.capture="(event) => recordClick(event, list.key, card)">
                                    <RangeAdvisorProductCard :product="card"
                                        :hasInBasket="get(layout, ['family_page', 'productInBasket', 'list', card.id])"
                                        :buttonStyle="getStyles(fieldValue?.button?.properties, screenType, false) ?? undefined"
                                        :buttonStyleHover="getStyles(fieldValue?.buttonHover?.properties, screenType, false) ?? undefined" />
                                </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div v-else class="range-grid">
                                <div v-for="card in list.items" :key="`${card.id}-${cardsVersion}`" @click.capture="(event) => recordClick(event, list.key, card)">
                                    <RangeAdvisorProductCard :product="card"
                                        :hasInBasket="get(layout, ['family_page', 'productInBasket', 'list', card.id])"
                                        :buttonStyle="getStyles(fieldValue?.button?.properties, screenType, false) ?? undefined"
                                        :buttonStyleHover="getStyles(fieldValue?.buttonHover?.properties, screenType, false) ?? undefined" />
                                </div>
                            </div>
                        </TabPanel>
                    </TabPanels>
                </Tabs>
            </div>
        </template>
    </section>
</template>

<style scoped>
.range-panel {
    max-width: 1280px;
    margin: 0 auto;
    padding: 2rem 1rem 1.5rem;
}

.range-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    border: 1px solid color-mix(in srgb, var(--theme-color-4) 18%, #e5e7eb);
    border-radius: 1rem;
    background:
        radial-gradient(120% 140% at 0% 0%, color-mix(in srgb, var(--theme-color-4) 12%, transparent) 0%, transparent 60%),
        color-mix(in srgb, var(--theme-color-4) 4%, #ffffff);
}

.range-header-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3.25rem;
    height: 3.25rem;
    flex-shrink: 0;
    border-radius: 9999px;
    background-color: #ffffff;
    box-shadow: 0 6px 16px -8px color-mix(in srgb, var(--theme-color-4) 60%, transparent);
    border: 1px solid color-mix(in srgb, var(--theme-color-4) 20%, #e5e7eb);
}

.range-header-icon {
    width: 2.25rem;
    height: 2.25rem;
}

.range-header-text {
    flex: 1 1 16rem;
    min-width: 0;
}

.range-title {
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1.3;
    color: #111827;
}

.range-subtitle {
    margin-top: 0.125rem;
    font-size: 0.875rem;
    color: #4b5563;
}

.range-remembered {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.375rem 0.5rem;
    margin-top: 0.625rem;
    font-size: 0.8125rem;
    color: #374151;
}

.range-remembered-label {
    font-weight: 600;
    color: color-mix(in srgb, var(--theme-color-4) 75%, #111827);
}

.range-tell {
    flex-shrink: 0;
    border-radius: 9999px;
}

.range-body {
    margin-top: 1.25rem;
}

.range-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.range-toolbar-action {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}

.range-tabs {
    min-width: 0;
    flex: 1 1 auto;
}

.range-tabs :deep(.p-tablist-content),
.range-tabs :deep(.p-tablist-tab-list) {
    background: transparent;
    border: 0;
}

.range-tabs :deep(.p-tablist-tab-list) {
    gap: 0.375rem;
    padding: 0.25rem;
    border-radius: 9999px;
    background-color: #f3f4f6;
    width: max-content;
}

.range-tabs :deep(.p-tablist-active-bar) {
    display: none;
}

.range-tabs :deep(.p-tab) {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.4375rem 0.875rem;
    border: 0;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 500;
    color: #4b5563;
    background: transparent;
    white-space: nowrap;
    transition: background-color 0.15s ease, color 0.15s ease;
}

.range-tabs :deep(.p-tab:not(.p-tab-active):hover) {
    color: #111827;
    background-color: #e5e7eb;
}

.range-tabs :deep(.p-tab-active) {
    color: var(--theme-color-5);
    background-color: var(--theme-color-4);
    box-shadow: 0 4px 10px -6px color-mix(in srgb, var(--theme-color-4) 80%, transparent);
}

.range-tabs :deep(.p-tab:focus-visible) {
    outline: 2px solid var(--theme-color-4);
    outline-offset: 2px;
}

.range-tab-label {
    white-space: nowrap;
}

.range-count {
    min-width: 1.25rem;
    padding: 0 0.375rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 600;
    line-height: 1.25rem;
    text-align: center;
    color: #4b5563;
    background-color: #ffffff;
}

.range-tabs :deep(.p-tab-active) .range-count {
    color: var(--theme-color-4);
    background-color: var(--theme-color-5);
}

.range-panel :deep(.p-tabpanels) {
    padding: 0;
    background: transparent;
}

.range-grid {
    display: grid;
    gap: 0.5rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-auto-rows: 1fr;
}

@media (min-width: 640px) {
    .range-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (min-width: 1024px) {
    .range-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.75rem;
    }
}

@media (min-width: 1280px) {
    .range-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }
}

.range-pack {
    padding: 0.75rem;
    border: 1px solid #eceef1;
    border-radius: 1rem;
    background-color: color-mix(in srgb, var(--theme-color-4) 3%, #fafafa);
}

.range-pack-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    padding-left: 0.75rem;
    border-left: 3px solid var(--theme-color-4);
}

.range-pack-name {
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
}

a.range-pack-name:hover {
    color: var(--theme-color-4);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.range-pack-meta {
    margin-top: 0.125rem;
    font-size: 0.75rem;
    color: #6b7280;
}
</style>
