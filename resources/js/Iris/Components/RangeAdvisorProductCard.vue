<script setup lang="ts">
import { computed, inject } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faImage } from "@fal"
import { faCrown } from "@fas"
import Image from "@common/Components/Image.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import AssistantIcon from "@/Iris/Components/AssistantIcon.vue"
import NewAddToCartButton from "@/Components/CMS/Webpage/Products/NewAddToCartButton.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { getBestOffer } from "@/Composables/useOffers"
import { resolveProductBadge } from "@/Composables/useProductBadge"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { ProductResource } from "@/types/Iris/Products"
library.add(faImage, faCrown)

type Card = ProductResource & {
    assistant_reason: string
    assistant_reason_code?: string
    discounted_price?: number
    discounted_price_per_unit?: number
    product_offers_data?: any
}

const props = defineProps<{
    product: Card
    hasInBasket?: any
    buttonStyle?: object
    buttonStyleHover?: object
    dark?: boolean
}>()

const layout: any = inject("layout", retinaLayoutStructure)
const locale = useLocaleStore()

const currencyCode = computed(() => layout?.iris?.currency?.code ?? props.product.currency_code ?? "")

const image = computed(() => props.product.web_images?.main?.gallery ?? props.product.image?.source ?? null)

const isInStock = computed(() => props.product.stock > 0)

const isBestseller = computed(() => resolveProductBadge(props.product, true) === "bestseller")

const isMerlinBestseller = computed(() => props.product.assistant_reason_code === "best_seller" || props.product.assistant_reason === ctrans("A best seller"))

const showBestsellerBadge = computed(() => isBestseller.value || isMerlinBestseller.value)

const isMerlinNewArrival = computed(() => props.product.assistant_reason_code === "new_arrival")

const showReason = computed(() => !!props.product.assistant_reason && !isMerlinBestseller.value && !isMerlinNewArrival.value)

const canAddToBasket = computed(() => !!layout?.iris?.is_logged_in && (isInStock.value || !!props.product.pre_order))

const bestOffer = computed(() => props.product.product_offers_data?.number_offers > 0 ? getBestOffer(props.product.product_offers_data) : null)

const lastStep = computed(() => props.product.step_discount?.steps?.at(-1) ?? null)

const offerPercentage = computed(() => {
    if (lastStep.value) {
        return lastStep.value.percentage_off_label
    }

    const raw = Number(bestOffer.value?.max_percentage_discount ?? props.product.product_offers_data?.best_percentage_off?.percentage_off)

    return raw > 0 ? `${Math.round(raw * 1000) / 10}%` : null
})

const offerPrice = computed(() => lastStep.value?.price ?? props.product.discounted_price ?? null)

const offerHint = computed(() => {
    if (lastStep.value) {
        return ctrans("Buy :qty+ :unit, save :off", { qty: lastStep.value.min_quantity, unit: props.product.unit, off: lastStep.value.percentage_off_label })
    }

    const label = bestOffer.value?.products_triggers_label || bestOffer.value?.label || ""

    return String(label).replace(/<[^>]*>/g, "").trim()
})

const money = (amount: number) => locale.currencyFormat(currencyCode.value, amount)
</script>

<template>
    <article class="advisor-card" :class="{ 'is-dark': dark }">
        <div class="advisor-media">
            <component :is="product.url ? LinkIris : 'div'" :href="product.url ?? undefined" class="advisor-media-link">
                <Image v-if="image" :src="image" :alt="product.name" class="advisor-image" />
                <FontAwesomeIcon v-else icon="fal fa-image" class="advisor-image-empty" aria-hidden="true" />
            </component>

            <div v-if="showBestsellerBadge || isMerlinNewArrival" class="advisor-badges">
                <span v-if="showBestsellerBadge" class="advisor-badge" :title="isMerlinBestseller ? product.assistant_reason : undefined">
                    <FontAwesomeIcon v-if="isBestseller" icon="fas fa-crown" aria-hidden="true" />
                    <AssistantIcon v-if="isMerlinBestseller" mono class="advisor-badge-merlin" />
                    {{ ctrans("Bestseller") }}
                </span>
                <span v-if="isMerlinNewArrival" class="advisor-badge advisor-badge-new" :title="product.assistant_reason">
                    <AssistantIcon mono class="advisor-badge-merlin" />
                    {{ ctrans("New In") }}
                </span>
            </div>

            <div v-if="canAddToBasket" class="advisor-basket">
                <NewAddToCartButton
                    :product="product"
                    :hasInBasket
                    :addToBasketRoute="{ name: 'iris.models.transaction.store' }"
                    :updateBasketQuantityRoute="{ name: 'iris.models.transaction.store' }"
                    :buttonStyle
                    :buttonStyleHover
                />
            </div>
        </div>

        <div class="advisor-body">
            <p v-if="showReason" class="advisor-reason" :title="product.assistant_reason">
                <AssistantIcon mono class="advisor-reason-icon" />
                <span class="advisor-reason-text">{{ product.assistant_reason }}</span>
            </p>

            <component :is="product.url ? LinkIris : 'span'" :href="product.url ?? undefined" class="advisor-name" :title="product.name">
                <span v-if="product.units > 1" class="advisor-units">{{ product.units }}×</span>
                {{ product.name }}
            </component>

            <div class="advisor-meta">
                <span class="advisor-code">
                    <span class="advisor-stock" :class="isInStock ? 'is-in-stock' : 'is-out-of-stock'" :title="isInStock ? ctrans('In stock') : ctrans('Out of stock')" />
                    {{ product.code }}
                </span>
                <span v-if="product.rrp_per_unit > 0" class="advisor-rrp">
                    {{ ctrans("RRP") }} {{ locale.currencyFormatRrp(currencyCode, product.rrp_per_unit) }}
                </span>
            </div>

            <div v-if="layout?.iris?.is_logged_in" class="advisor-footer">
            <div class="advisor-price-row">
                <span class="advisor-price">
                    {{ money(product.price) }}<span v-if="product.units <= 1" class="advisor-price-unit">/{{ product.unit }}</span>
                </span>
                <span v-if="product.units > 1 && product.price_per_unit" class="advisor-price-per-unit">
                    {{ money(product.price_per_unit) }}/{{ product.unit }}
                </span>
            </div>

            <p v-if="offerPercentage" class="advisor-offer">
                <span class="advisor-offer-tag">−{{ offerPercentage }}</span>
                <span v-if="offerPrice" class="advisor-offer-price">{{ money(offerPrice) }}</span>
            </p>
            <p v-if="offerPercentage && offerHint" class="advisor-offer-hint">{{ offerHint }}</p>
            </div>
        </div>
    </article>
</template>

<style scoped>
.advisor-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    border: 1px solid #eceef1;
    border-radius: 0.75rem;
    background-color: #ffffff;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.advisor-card:hover {
    border-color: color-mix(in srgb, var(--theme-color-4) 35%, #e5e7eb);
    box-shadow: 0 8px 18px -12px rgb(0 0 0 / 0.25);
}

.advisor-media {
    position: relative;
    aspect-ratio: 1 / 1;
    background-color: #ffffff;
}

.advisor-media-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    padding: 0.75rem;
}

.advisor-image {
    width: 100%;
    height: 100%;
}

.advisor-image :deep(img) {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.advisor-image-empty {
    font-size: 2rem;
    color: #d1d5db;
}

.advisor-badges {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
}

.advisor-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.625rem;
    font-weight: 600;
    line-height: 1rem;
    color: #7a5a00;
    background-color: #fdf3d3;
}

.advisor-badge-new {
    color: #166534;
    background-color: #dcfce7;
}

.advisor-badge-merlin {
    width: 0.875rem;
    height: 0.875rem;
}

.advisor-basket {
    position: absolute;
    right: 0.5rem;
    bottom: 0.5rem;
}

.advisor-body {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.5rem 0.625rem 0.625rem;
    border-top: 1px solid #f3f4f6;
}

.advisor-reason {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    align-self: flex-start;
    max-width: 100%;
    padding: 0.0625rem 0.5rem 0.0625rem 0.25rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 500;
    line-height: 1rem;
    color: color-mix(in srgb, var(--theme-color-4) 70%, #111827);
    background-color: color-mix(in srgb, var(--theme-color-4) 10%, #ffffff);
}

.advisor-reason-icon {
    width: 0.875rem;
    height: 0.875rem;
    color: var(--theme-color-4);
}

.advisor-reason-text {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.advisor-name {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.5rem;
    font-size: 0.8125rem;
    font-weight: 600;
    line-height: 1.25rem;
    color: #111827;
}

a.advisor-name:hover {
    color: var(--theme-color-4);
}

.advisor-units {
    color: var(--theme-color-4);
}

.advisor-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.6875rem;
    color: #6b7280;
}

.advisor-code {
    display: inline-flex;
    align-items: center;
    gap: 0.3125rem;
    white-space: nowrap;
}

.advisor-stock {
    width: 0.4375rem;
    height: 0.4375rem;
    border-radius: 9999px;
}

.advisor-stock.is-in-stock {
    background-color: #22c55e;
}

.advisor-stock.is-out-of-stock {
    background-color: #ef4444;
}

.advisor-rrp {
    white-space: nowrap;
}

.advisor-footer {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-top: auto;
    padding-top: 0.25rem;
}

.advisor-price-row {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.25rem 0.5rem;
}

.advisor-price {
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
    white-space: nowrap;
}

.advisor-price-unit {
    margin-left: 0.125rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: #6b7280;
}

.advisor-price-per-unit {
    font-size: 0.75rem;
    color: #6b7280;
    white-space: nowrap;
}

.advisor-offer {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    min-width: 0;
    font-size: 0.6875rem;
    line-height: 1rem;
    color: #4b5563;
}

.advisor-offer-tag {
    flex-shrink: 0;
    padding: 0 0.375rem;
    border-radius: 0.25rem;
    font-weight: 700;
    color: var(--theme-color-5);
    background-color: var(--theme-color-4);
}

.advisor-offer-price {
    flex-shrink: 0;
    font-weight: 700;
    color: var(--theme-color-4);
    white-space: nowrap;
}

.advisor-offer-hint {
    font-size: 0.6875rem;
    line-height: 1rem;
    color: #4b5563;
}

.advisor-card.is-dark {
    border-color: #46535f;
    border-radius: 0.9375rem;
    background-color: #293541;
    transition: transform 0.2s ease, border-color 0.2s ease;
}

.advisor-card.is-dark:hover {
    transform: translateY(-2px);
    border-color: var(--theme-color-4);
    box-shadow: none;
}

.is-dark .advisor-body {
    padding: 0.625rem 0.75rem 0.75rem;
    border-top: 0;
}

.is-dark .advisor-reason {
    color: color-mix(in srgb, var(--theme-color-4) 50%, #ffffff);
    background-color: color-mix(in srgb, var(--theme-color-4) 17%, transparent);
}

.is-dark .advisor-name,
.is-dark .advisor-price {
    color: #ffffff;
}

.is-dark .advisor-meta,
.is-dark .advisor-price-unit,
.is-dark .advisor-price-per-unit,
.is-dark .advisor-offer,
.is-dark .advisor-offer-hint {
    color: #c8d2db;
}
</style>
