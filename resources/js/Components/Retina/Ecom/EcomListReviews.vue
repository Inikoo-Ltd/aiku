<script setup lang="ts">
import { routeType } from '@/types/route'
import { Table as TableTS } from '@/types/Table'
import { GridProducts } from "@/Components/Product"
import Card from "primevue/card"
import Tag from "primevue/tag"
import Rating from "primevue/rating"
import Button from "primevue/button"
import Divider from "primevue/divider"
import Avatar from "primevue/avatar"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCube } from "@fal"
import { faBadgeCheck } from "@fas"

const props = defineProps<{
    data: any[] | TableTS
    tab: string
    updateRoute: routeType
    state?: string
    readonly?: boolean
}>()


</script>

<template>
    <GridProducts :resource="data" :preserve-scroll="true" class="mt-5 " :name="tab"
        :gridClass="'lg:grid-cols-1 xl:grid-cols-1 grid grid-cols-1 gap-0'">
        <template #card="{ item }">
            <Card class="border border-gray-200 shadow-none">
                <template #content>
                    <div class="space-y-3">
                        <!-- Product -->
                        <div class="flex items-start gap-3">
                            <Avatar shape="square" size="normal" class="h-10 w-10 bg-gray-100">
                                <FontAwesomeIcon :icon="faCube" class="text-sm text-gray-500" />
                            </Avatar>

                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-semibold text-gray-900">
                                    {{ item.asset_name }}
                                </div>

                                <div class="text-xs text-gray-500">
                                    {{ item.asset_code }}
                                </div>
                            </div>

                            <Tag severity="contrast" class="text-xs" :value="`x${item.quantity_ordered}`" />
                        </div>

                        <template v-if="item.review?.review_id">
                            <!-- Rating -->
                            <div class="flex items-center justify-between">
                                <Rating :modelValue="item.review.rating" readonly :cancel="false" />

                                <Tag severity="success" rounded class="text-xs">
                                    <template #icon>
                                        <FontAwesomeIcon :icon="faBadgeCheck" class="mr-1 text-[10px]" />
                                    </template>

                                    Verified
                                </Tag>
                            </div>

                            <!-- Review -->
                            <p class="line-clamp-3 border-l-2 border-green-500 pl-3 text-sm text-gray-600">
                                {{ item.review.message }}
                            </p>

                            <!-- Footer -->
                            <div class="flex items-center justify-between border-t pt-2">
                                <small class="text-xs text-gray-400">
                                    2 days ago
                                </small>
                            </div>
                        </template>
                    </div>
                </template>
            </Card>
        </template>
    </GridProducts>
</template>
