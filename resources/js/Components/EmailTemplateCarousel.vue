<script setup lang="ts">
import { ref, computed } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faStore, faChevronLeft, faChevronRight, faEnvelope } from '@fal'
import Carousel from 'primevue/carousel'
import ProgressSpinner from 'primevue/progressspinner'
import axios from 'axios'
import TemplateCarouselItem from './TemplateCarouselItem.vue'

library.add(faStore, faChevronLeft, faChevronRight, faEnvelope);

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
    otherShopTemplates: {
        data: any[],
        current_page: number,
        first_page_url: string | null,
        from: number | null,
        last_page: number,
        last_page_url: string | null,
        links: any[],
        next_page_url: string | null,
        path: string,
        per_page: number,
        prev_page_url: string | null,
        to: number | null,
        total: number
    }
}

const props = defineProps<Props>();

const isLoading = ref(false);
const localOtherShopTemplates = ref<Template[]>([]);
const currentPage = ref(1);
const lastPage = ref(1);
const currentCarouselPage = ref(0);

const carouselOptions = computed(() => ({
    responsive: [
        {
            breakpoint: '1024px',
            numVisible: 3,
            numScroll: 3
        },
        {
            breakpoint: '768px',
            numVisible: 2,
            numScroll: 2
        },
        {
            breakpoint: '560px',
            numVisible: 1,
            numScroll: 1
        }
    ],
    numVisible: 3,
    numScroll: 1,
    circular: false,
    autoplayInterval: 0,
    showNavigators: true,
    showIndicators: false
}));

const numVisibleOther = ref(4);

const carouselOptionsForOther = [
    {
        breakpoint: '1024px',
        numVisible: 4,
        numScroll: 4
    },
    {
        breakpoint: '768px',
        numVisible: 3,
        numScroll: 3
    },
    {
        breakpoint: '560px',
        numVisible: 2,
        numScroll: 2
    }
]

const carouselOptionsOwn = [
    { breakpoint: '1024px', numVisible: 4, numScroll: 4 },
    { breakpoint: '768px', numVisible: 3, numScroll: 3 },
    { breakpoint: '560px', numVisible: 2, numScroll: 2 }
]

const hasOwnTemplates = computed(() => props.ownShopTemplates?.templates?.length > 0);

const otherShopTemplatesData = computed(() => {
    if (localOtherShopTemplates.value.length > 0) {
        return localOtherShopTemplates.value;
    }
    if (!props.otherShopTemplates) return [];
    // Handle both old format (templates array) and new paginated format (data array)
    return Array.isArray(props.otherShopTemplates)
        ? props.otherShopTemplates
        : (props.otherShopTemplates.data || []);
});

const hasOtherTemplates = computed(() => otherShopTemplatesData.value.length > 0);
const hasAnyTemplates = computed(() => hasOwnTemplates.value || hasOtherTemplates.value);

// Boundary detection for carousel
const isAtStartOfPage = computed(() => currentCarouselPage.value === 0);
const isAtEndOfPage = computed(() => {
    const totalItems = otherShopTemplatesData.value.length;
    return currentCarouselPage.value >= Math.ceil(totalItems / numVisibleOther.value) - 1;
});

// Page availability from pagination data
const hasPrevPage = computed(() => {
    if (localOtherShopTemplates.value.length > 0) {
        return currentPage.value > 1;
    }
    return props.otherShopTemplates?.prev_page_url !== null;
});

const hasNextPage = computed(() => {
    if (localOtherShopTemplates.value.length > 0) {
        return currentPage.value < lastPage.value;
    }
    return props.otherShopTemplates?.next_page_url !== null;
});

// Show custom buttons only at boundaries when pages are available
const showPrevPageButton = computed(() => isAtStartOfPage.value && hasPrevPage.value);
const showNextPageButton = computed(() => isAtEndOfPage.value && hasNextPage.value);

const pagination = computed(() => {
    if (localOtherShopTemplates.value.length > 0) {
        return {
            currentPage: currentPage.value,
            lastPage: lastPage.value,
            perPage: 4,
            total: lastPage.value * 4
        };
    }
    if (!props.otherShopTemplates || Array.isArray(props.otherShopTemplates)) {
        return null;
    }
    return {
        currentPage: props.otherShopTemplates.current_page,
        lastPage: props.otherShopTemplates.last_page,
        perPage: props.otherShopTemplates.per_page,
        total: props.otherShopTemplates.total
    };
});

const emit = defineEmits<{
    loadOtherShopTemplates: [page: number];
}>();

const handlePageUpdate = (event: number) => {
    currentCarouselPage.value = event;
};

const extractPageFromUrl = (url: string | null): number | null => {
    if (!url) return null;
    const match = url.match(/[?&]page=(\d+)/);
    return match ? parseInt(match[1], 10) : null;
};

const loadPrevPage = () => {
    let prevPage: number | null = null;

    if (localOtherShopTemplates.value.length > 0) {
        prevPage = currentPage.value - 1;
    } else {
        prevPage = extractPageFromUrl(props.otherShopTemplates?.prev_page_url);
    }

    if (prevPage !== null && prevPage >= 1) {
        fetchOtherShopTemplates(prevPage, 'prev');
    }
};

const loadNextPage = () => {
    let nextPage: number | null = null;

    if (localOtherShopTemplates.value.length > 0) {
        nextPage = currentPage.value + 1;
    } else {
        nextPage = extractPageFromUrl(props.otherShopTemplates?.next_page_url);
    }

    if (nextPage !== null) {
        const maxPage = localOtherShopTemplates.value.length > 0 ? lastPage.value : props.otherShopTemplates?.last_page;
        if (maxPage && nextPage <= maxPage) {
            fetchOtherShopTemplates(nextPage);
        }
    }
};

const fetchOtherShopTemplates = async (page: number, direction: 'next' | 'prev' = 'next') => {
    if (isLoading.value) return;

    isLoading.value = true;

    try {
        const response = await axios.get(`/json/organisation/${props.organisationSlug}/shop/${props.shopSlug}/email-templates/other-shops`, {
            params: {
                page: page,
                perPage: 4
            }
        });

        const data = response.data;
        currentPage.value = data.current_page;
        lastPage.value = data.last_page;

        // Replace templates with new page data
        localOtherShopTemplates.value = data.data;

        // Reset carousel position based on direction
        if (direction === 'next') {
            currentCarouselPage.value = 0;
        } else {
            // Set to last page of carousel when going back
            const totalItems = data.data.length;
            currentCarouselPage.value = Math.max(0, Math.ceil(totalItems / numVisibleOther.value) - 1);
        }
    } catch (error) {
        console.error('Error fetching other shop templates:', error);
    } finally {
        isLoading.value = false;
    }
};
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
                    <span v-if="pagination" class="ml-2 text-sm text-gray-500">
                        ({{ pagination.total }} total)
                    </span>
                </h3>
            </div>

            <div class="relative">
                <Carousel :value="otherShopTemplatesData" :showIndicators="true" :numVisible="4" :numScroll="4"
                    :circular="false" :responsiveOptions="carouselOptionsOwn" @update:page="handlePageUpdate"
                    class="mb-8" :class="{ 'opacity-50 pointer-events-none': isLoading }"
                    :showNavigators="!showPrevPageButton && !showNextPageButton">
                    <template #item="slotProps">
                        <TemplateCarouselItem :template="slotProps.data" :organisation-slug="props.organisationSlug"
                            :shop-slug="props.shopSlug" :mailshot-id="props.mailshotId" button-type="secondary"
                            :show-shop-name="true" :show-envelope-icon="false" />
                    </template>
                </Carousel>

                <!-- Custom Pagination Buttons -->
                <button v-if="showPrevPageButton" @click="loadPrevPage" :disabled="isLoading"
                    class="absolute left-0 top-1/2 -translate-y-1/2 z-20 w-10 h-10 flex items-center justify-center bg-white/80 hover:bg-white border border-gray-300 rounded-lg shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    <FontAwesomeIcon :icon="faChevronLeft" class="text-gray-700" />
                </button>

                <button v-if="showNextPageButton" @click="loadNextPage" :disabled="isLoading"
                    class="absolute right-0 top-1/2 -translate-y-1/2 z-20 w-10 h-10 flex items-center justify-center bg-white/80 hover:bg-white border border-gray-300 rounded-lg shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    <FontAwesomeIcon :icon="faChevronRight" class="text-gray-700" />
                </button>

                <!-- Loading Overlay -->
                <div v-if="isLoading"
                    class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-75 z-10">
                    <ProgressSpinner style="width: 50px; height: 50px" strokeWidth="4" />
                </div>
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
