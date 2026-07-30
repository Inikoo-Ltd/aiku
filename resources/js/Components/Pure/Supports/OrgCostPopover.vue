<script setup lang="ts">
import { computed, inject, ref } from "vue";
import Popover from "primevue/popover";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faInfoCircle } from "@far";
import { library } from "@fortawesome/fontawesome-svg-core";
import { trans } from "laravel-vue-i18n";

library.add(faInfoCircle);

const props = defineProps<{
    org_data?: Record<string, any> | null
    currency?: { code: string, symbol?: string, ratio_eur?: number | null }
}>();

const locale = inject("locale", {}) as any;

const orgList = computed(() => {
    const ratio = props.currency?.ratio_eur;

    return Object.values(props.org_data ?? {}).map((org: any) => {
        const baseCost = org.base_cost == null ? null : Number(org.base_cost);
        const cost = baseCost == null || ratio == null
            ? null
            : Math.round(baseCost * ratio * 100) / 100;

        return { ...org, cost };
    });
});

const popover = ref();

const toggle = (event: Event) => {
    popover.value?.toggle(event);
};
</script>

<template>
    <span class="inline-flex items-center">
        <button
            type="button"
            class="text-blue-500 transition-colors hover:text-blue-700"
            v-tooltip="trans('Organisation cost breakdown')"
            @click.stop="toggle"
        >
            <FontAwesomeIcon :icon="faInfoCircle" />
        </button>

        <Popover ref="popover">
            <div class="min-w-[22rem]">
                <div class="mb-2 text-xs font-semibold text-gray-700">
                    {{ trans('Organisation costs') }}
                    <span v-if="currency" class="text-gray-400">({{ currency.code }})</span>
                </div>

                <div v-if="orgList.length" class="overflow-x-auto">
                    <table class="w-full border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-100 text-left text-gray-600">
                                <th class="border px-3 py-1.5">{{ trans('Organisation') }}</th>
                                <th class="border px-3 py-1.5 text-right">{{ trans('Stock') }}</th>
                                <th class="border px-3 py-1.5 text-right">{{ trans('Cost') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="org in orgList" :key="org.org_code" class="hover:bg-gray-50">
                                <td class="border px-3 py-1.5 font-medium text-gray-700">{{ org.org_code }}</td>
                                <td class="border px-3 py-1.5 text-right">{{ org.stock ?? '—' }}</td>
                                <td class="border px-3 py-1.5 text-right">
                                    {{ org.cost == null ? '—' : locale.currencyFormat(currency?.code, org.cost) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="py-3 text-center text-xs italic text-gray-500">
                    {{ trans('No organisation data available') }}
                </div>
            </div>
        </Popover>
    </span>
</template>
