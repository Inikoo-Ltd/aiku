<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useExpectedBackInStockDate } from "@/Composables/useOutOfStockLabel"

const props = defineProps<{
    product: {
        stock?: number | null
        is_coming_soon?: boolean
        expected_back_in_stock_at?: string | null
    }
}>()

const expectedBackDate = computed(() =>
    (props.product.stock ?? 0) > 0 || props.product.is_coming_soon ? null : useExpectedBackInStockDate(props.product)
)
</script>

<template>
    <div v-if="expectedBackDate" class="eta-sash" aria-hidden="true">
        <div class="eta-sash__band">
            <div class="eta-sash__stitch">
                <div class="eta-sash__caption">{{ ctrans("Expected back") }}</div>
                <div class="eta-sash__date">{{ expectedBackDate }}</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.eta-sash {
    position: absolute;
    top: 0;
    left: 0;
    z-index: 10;
    width: 96px;
    height: 96px;
    overflow: hidden;
    pointer-events: none;
}

.eta-sash__band {
    position: absolute;
    top: 22px;
    left: -40px;
    width: 150px;
    padding: 2px 0;
    text-align: center;
    color: #fff;
    background: linear-gradient(90deg, #9a3412 0%, #c2410c 50%, #9a3412 100%);
    box-shadow: 0 2px 5px rgb(0 0 0 / 0.3);
    transform: rotate(-45deg);
}


.eta-sash__stitch {
    padding: 2px 0;
    border-top: 1px dashed rgb(255 255 255 / 0.55);
    border-bottom: 1px dashed rgb(255 255 255 / 0.55);
}

.eta-sash__caption {
    font-size: 6px;
    font-weight: 600;
    line-height: 1;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.9;
}

.eta-sash__date {
    margin-top: 1px;
    font-size: 9px;
    font-weight: 700;
    line-height: 1.1;
    white-space: nowrap;
    text-shadow: 0 1px 1px rgb(0 0 0 / 0.2);
}

@media (min-width: 768px) {
    .eta-sash {
        width: 112px;
        height: 112px;
    }

    .eta-sash__band {
        top: 26px;
        left: -46px;
        width: 170px;
    }

    .eta-sash__caption {
        font-size: 7px;
    }

    .eta-sash__date {
        font-size: 10px;
    }
}
</style>
