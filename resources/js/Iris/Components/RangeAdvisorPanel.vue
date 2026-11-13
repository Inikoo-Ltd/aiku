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
import { faShoppingBasket, faCheck, faSparkles } from "@far"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { ctrans } from "@/Composables/useTrans"
import { getStyles } from "@/Composables/styles"
import Button from "@/Components/Elements/Buttons/Button.vue"
import RenderProduct from "@/Iris/Components/IrisBlocks/Products/Ecom/RenderProduct.vue"
import { ProductResource } from "@/types/Iris/Products"
library.add(faShoppingBasket, faCheck, faSparkles)

type Card = ProductResource & { assistant_reason: string, quantity_ordered?: number }
type Pack = { family: { id: number, name: string, url: string | null }, items: Card[] }
type ListKey = "missing" | "forgotten" | "starter_packs" | "new_arrivals"
type List = { key: ListKey, items?: Card[], packs?: Pack[] }
type Panel = { shop_type: string | null, country: string | null, has_orders: boolean, remembered: string | null, lists: List[] }

const props = defineProps<{
    assistantName: string
    fieldValue?: Record<string, any>
    productCardCode: string
    screenType: "mobile" | "tablet" | "desktop"
}>()

const emit = defineEmits<{
    (event: "loaded", hasProducts: boolean): void
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
    emit("loaded", !!panel.value?.lists.length)
})
</script>

<template>
    <section v-if="isLoading || panel?.lists.length || panel?.remembered || (panel && !panel.shop_type)" class="range-panel">
        <div v-if="isLoading" class="flex flex-col gap-3" aria-hidden="true">
            <div class="skeleton h-10 rounded" />
            <div class="range-grid">
                <div v-for="index in 4" :key="index" class="skeleton h-64 rounded" />
            </div>
        </div>

        <template v-else-if="panel">
            <div class="range-heading">
                <h2 v-if="panel.lists.length" class="range-title">
                    <FontAwesomeIcon icon="far fa-sparkles" class="range-title-icon" fixed-width aria-hidden="true" />
                    {{ ctrans(":name noticed", { name: assistantName }) }}
                </h2>
                <p v-if="panel.lists.length" class="range-subtitle">
                    {{ shopLine }}
                    {{ ctrans("In stock now, at your trade prices.") }}
                </p>
                <p v-if="panel.remembered" class="range-remembered">
                    <span class="font-medium">{{ ctrans(":name remembers:", { name: assistantName }) }}</span>
                    {{ panel.remembered }}
                    <button type="button" class="range-tell ml-1" :disabled="isForgetting" @click="forget">{{ ctrans("Forget this") }}</button>
                </p>
                <p v-if="forgetError" class="text-xs text-red-500">{{ forgetError }}</p>
                <button v-if="!panel.shop_type" type="button" class="range-tell" @click="emit('tellShop')">
                    {{ ctrans("Tell :name what kind of shop you run to get better picks", { name: assistantName }) }}
                </button>
            </div>

            <div v-if="panel.lists.length && activeList" ref="tabsElement">
            <Tabs v-model:value="activeList" scrollable lazy>
                <TabList>
                    <Tab v-for="list in panel.lists" :key="list.key" :value="list.key">
                        <span class="whitespace-nowrap">{{ listLabel(list.key) }} <span class="range-count">{{ cardsOf(list).length }}</span></span>
                    </Tab>
                </TabList>
                <TabPanels>
                    <TabPanel v-for="list in panel.lists" :key="list.key" :value="list.key">
                        <template v-if="list.key === 'starter_packs'">
                            <div class="flex flex-col gap-4">
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
                                            <p class="range-reason" :title="card.assistant_reason">{{ card.assistant_reason }}</p>
                                            <div :style="getStyles(fieldValue?.card_product?.properties, screenType)" class="relative flex justify-center rounded">
                                                <RenderProduct :code="productCardCode" :product="card"
                                                    :buttonStyle="getStyles(fieldValue?.button?.properties, screenType, false) ?? undefined"
                                                    :buttonStyleLogin="getStyles(fieldValue?.buttonLogin?.properties, screenType) ?? undefined"
                                                    :buttonStyleHover="getStyles(fieldValue?.buttonHover?.properties, screenType, false)"
                                                    :button="fieldValue?.button"
                                                    :hasInBasketList="get(layout, ['family_page', 'productInBasket', 'list'], [])"
                                                    :bestSeller="fieldValue?.bestseller" :screenType />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template v-else>
                            <div class="range-grid">
                                <div v-for="card in list.items" :key="`${card.id}-${cardsVersion}`" @click.capture="(event) => recordClick(event, list.key, card)">
                                    <p class="range-reason" :title="card.assistant_reason">{{ card.assistant_reason }}</p>
                                    <div :style="getStyles(fieldValue?.card_product?.properties, screenType)" class="relative flex justify-center rounded">
                                        <RenderProduct :code="productCardCode" :product="card"
                                            :buttonStyle="getStyles(fieldValue?.button?.properties, screenType, false) ?? undefined"
                                            :buttonStyleLogin="getStyles(fieldValue?.buttonLogin?.properties, screenType) ?? undefined"
                                            :buttonStyleHover="getStyles(fieldValue?.buttonHover?.properties, screenType, false)"
                                            :button="fieldValue?.button"
                                            :hasInBasketList="get(layout, ['family_page', 'productInBasket', 'list'], [])"
                                            :bestSeller="fieldValue?.bestseller" :screenType />
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <Button
                                    type="tertiary"
                                    size="xs"
                                    :label="notInBasket(list.items ?? []).length ? ctrans('Add all to basket') : ctrans('All in your basket')"
                                    :disabled="!notInBasket(list.items ?? []).length || adding !== null"
                                    :loading="adding === list.key"
                                    @click="addAll(list.key, list.items ?? [])"
                                >
                                    <template #icon>
                                        <FontAwesomeIcon :icon="notInBasket(list.items ?? []).length ? 'far fa-shopping-basket' : 'far fa-check'" class="text-green-600" fixed-width aria-hidden="true" />
                                    </template>
                                </Button>
                                <p v-if="addErrors[list.key]" class="text-xs text-red-500">{{ addErrors[list.key] }}</p>
                            </div>
                        </template>
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
    padding: 2rem 1rem 1rem;
}

.range-heading {
    margin-bottom: 0.75rem;
}

.range-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: #111827;
}

.range-title-icon {
    color: var(--theme-color-4, var(--app-accent));
}

.range-subtitle {
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: #4b5563;
}

.range-remembered {
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: #374151;
}

.range-remembered .range-tell {
    margin-top: 0;
}

.range-tell {
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: #374151;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.range-tell:hover {
    color: #111827;
}

.range-count {
    margin-left: 0.25rem;
    font-size: 0.75rem;
    color: #6b7280;
}

.range-grid {
    display: grid;
    gap: 1.5rem 1rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

@media (min-width: 640px) {
    .range-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (min-width: 1024px) {
    .range-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

.range-reason {
    margin-bottom: 0.25rem;
    min-height: 2rem;
    font-size: 0.75rem;
    line-height: 1rem;
    color: #4b5563;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.range-pack {
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 0.75rem;
}

.range-pack-header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.range-pack-name {
    font-weight: 600;
    color: #374151;
}

a.range-pack-name {
    text-decoration: underline;
    text-underline-offset: 2px;
}

a.range-pack-name:hover {
    color: #111827;
}

.range-pack-meta {
    font-size: 0.75rem;
    color: #4b5563;
}
</style>
