<script setup lang="ts">
import { inject } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faGift, faImage } from '@fal'
import { ctrans } from '@/Composables/useTrans'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import LinkIris from '@/Iris/Components/LinkIris.vue'
import Image from '@common/Components/Image.vue'
import { BasketBonusStock, BasketBonusStockProduct } from '@/types/Iris/BasketBonusStock'

defineProps<{
    bonusStock: BasketBonusStock
    product: BasketBonusStockProduct
}>()

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)
</script>

<template>
    <li class="bonus-line">
        <LinkIris :href="product.canonical_url" class="bonus-line__thumb">
            <Image v-if="product.web_image_thumbnail" :src="product.web_image_thumbnail" />
            <FontAwesomeIcon v-else :icon="faImage" class="bonus-line__placeholder" fixed-width aria-hidden="true" />
        </LinkIris>

        <div class="bonus-line__body">
            <LinkIris :href="product.canonical_url" class="bonus-line__code">{{ product.code }}</LinkIris>
            <LinkIris :href="product.canonical_url" class="bonus-line__name">
                <span v-if="product.units > 1">{{ product.units }}x </span>{{ product.name }}
            </LinkIris>

            <span v-tooltip="bonusStock.label" class="bonus-line__tag">
                <FontAwesomeIcon :icon="faGift" fixed-width aria-hidden="true" />
                {{ bonusStock.is_same_product ? ctrans('Free') : ctrans('Bonus Stock') }}
            </span>

            <div class="bonus-line__price">{{ locale.currencyFormat(layout.iris?.currency?.code, 0) }}</div>
        </div>

        <div v-tooltip="ctrans('Free quantity, set by the offer')" class="bonus-line__quantity">
            {{ locale.number(product.quantity) }}
        </div>
    </li>
</template>

<style scoped>
.bonus-line {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0.375rem 0;
    padding: 0.5rem;
    border: 1px solid #dcfce7;
    border-radius: 0.5rem;
    background: #f0fdf4;
    list-style: none;
}

.bonus-line__thumb {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 3.5rem;
    height: 3.5rem;
    overflow: hidden;
    border-radius: 0.375rem;
    background: #fff;
}

.bonus-line__thumb :deep(picture),
.bonus-line__thumb :deep(img) {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.bonus-line__placeholder {
    font-size: 1.25rem;
    opacity: 0.4;
}

.bonus-line__body {
    flex: 1 1 0%;
    min-width: 0;
    font-size: 0.75rem;
    line-height: 1.125rem;
}

.bonus-line__code {
    font-style: italic;
    color: #6b7280;
    text-decoration: underline;
    text-decoration-color: var(--theme-color-0);
    text-underline-offset: 2px;
}

.bonus-line__name {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #111827;
}

.bonus-line__name:hover {
    text-decoration: underline;
}

.bonus-line__tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    margin-top: 0.125rem;
    padding: 0.125rem 0.5rem;
    border-radius: 0.25rem;
    font-weight: 500;
    color: var(--theme-color-0);
    background: color-mix(in srgb, var(--theme-color-0) 12%, white);
}

.bonus-line__price {
    margin-top: 0.125rem;
    color: #374151;
    font-variant-numeric: tabular-nums;
}

.bonus-line__quantity {
    flex-shrink: 0;
    min-width: 2.75rem;
    padding: 0.375rem 0.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    background: #fff;
    font-size: 0.875rem;
    text-align: center;
    color: #374151;
    font-variant-numeric: tabular-nums;
}
</style>
