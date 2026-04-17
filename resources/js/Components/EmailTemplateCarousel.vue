<script setup lang="ts">
import { ref, computed } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faStore, faEnvelope } from '@fal'
import Carousel from 'primevue/carousel'
import TemplateCarouselItem from './TemplateCarouselItem.vue'

library.add(faStore, faEnvelope);

interface Template {
    id: number;
    name?: string;
    subject?: string;
    shop_name?: string;
    compiled_layout: string;
    created_at?: string;
}

interface Props {
    mailshotId: string;
    shopId: string;
    organisationSlug: string;
    shopSlug: string;
    ownShopTemplates: { templates: Template[], shop_name: string };
    otherShopTemplates?: Array<{
        id: number,
        slug: string,
        name: string,
        compiled_layout: string,
        created_at: string,
        shop_name: string
    }>;
}

const props = defineProps<Props>();

const carouselOptions = [
    { breakpoint: '1024px', numVisible: 4, numScroll: 4 },
    { breakpoint: '768px', numVisible: 3, numScroll: 3 },
    { breakpoint: '560px', numVisible: 2, numScroll: 2 }
]

const hasOwnTemplates = computed(() => props.ownShopTemplates?.templates?.length > 0);

const otherShopTemplatesData = computed(() => {
    return props.otherShopTemplates || [];
});

const hasOtherTemplates = computed(() => otherShopTemplatesData.value.length > 0);
const hasAnyTemplates = computed(() => hasOwnTemplates.value || hasOtherTemplates.value);
</script>


<template>
    <div class="space-y-8">
        <!-- Own Shop Templates -->
        <div v-if="hasOwnTemplates">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <FontAwesomeIcon :icon="faStore" class="mr-2 text-blue-600" />
                    {{ trans('Your Templates') }}
                    <span class="ml-2 text-sm text-gray-500">({{ props.ownShopTemplates.shop_name }})</span>
                </h3>
            </div>

            <Carousel :value="props.ownShopTemplates.templates" :numVisible="4" :options="carouselOptions" class="mb-8">
                <template #item="slotProps">
                    <TemplateCarouselItem :template="slotProps.data" :organisation-slug="props.organisationSlug"
                        :shop-slug="props.shopSlug" :mailshot-id="props.mailshotId" button-type="primary"
                        :show-shop-name="false" :show-envelope-icon="true" />
                </template>
            </Carousel>
        </div>

        <!-- Other Shop Templates -->
        <div v-if="hasOtherTemplates">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <FontAwesomeIcon :icon="faStore" class="mr-2 text-green-600" />
                    {{ trans('Templates from Other Shops') }}
                </h3>
            </div>

            <div class="relative">
                <Carousel :value="otherShopTemplatesData" :showIndicators="true" :numVisible="4" :numScroll="4"
                    :circular="false" :responsiveOptions="carouselOptions" class="mb-8">
                    <template #item="slotProps">
                        <TemplateCarouselItem :template="slotProps.data" :organisation-slug="props.organisationSlug"
                            :shop-slug="props.shopSlug" :mailshot-id="props.mailshotId" button-type="secondary"
                            :show-shop-name="true" :show-envelope-icon="false" />
                    </template>
                </Carousel>
            </div>
        </div>

        <!-- No Templates Message -->
        <div v-if="!hasAnyTemplates" class="text-center py-8">
            <FontAwesomeIcon :icon="faEnvelope" class="text-4xl text-gray-300 mb-4" />
            <p class="text-gray-500">{{ trans('No email templates with generated HTML found.') }}</p>
            <p class="text-sm text-gray-400 mt-2">
                {{ trans('Create some email templates first to see them here.') }}
            </p>
        </div>
    </div>
</template>

<style scoped>
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
