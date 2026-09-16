/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

import { computed, ref, watch, type ComputedRef, type Ref } from "vue"

/**
 * Paging for a list the page already holds in full.
 *
 * The Google Ads campaign page is one request carrying everything about a campaign, because the chart
 * needs the whole series and the ad groups arrive with the campaign. A Search campaign can bring sixty
 * ad groups and six hundred keywords with it, which is a wall to read but nothing to a browser, so the
 * paging happens here rather than costing a round trip per page.
 */
export function useLocalPagination<T>(
    rows: Ref<T[]> | ComputedRef<T[]>,
    perPageOptions: number[] = [25, 50, 100]
) {
    const perPage = ref(perPageOptions[0])
    const page = ref(1)

    const total = computed(() => rows.value.length)
    const pageCount = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)))

    /* A filter, a shorter period or a larger page size can all leave the reader standing on a page
       that no longer exists. */
    watch(pageCount, () => {
        if (page.value > pageCount.value) page.value = pageCount.value
    })

    const firstRow = computed(() => (total.value ? (page.value - 1) * perPage.value + 1 : 0))
    const lastRow = computed(() => Math.min(page.value * perPage.value, total.value))

    const paged = computed(() => rows.value.slice(firstRow.value - 1, lastRow.value))

    // Nothing to page through until the list outgrows the smallest page offered.
    const isPaged = computed(() => total.value > perPageOptions[0])

    const toFirstPage = () => (page.value = 1)

    return { perPage, perPageOptions, page, pageCount, total, firstRow, lastRow, paged, isPaged, toFirstPage }
}
