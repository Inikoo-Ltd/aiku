/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 8 Apr 2026 15:03:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

import { ref, computed } from 'vue'
import { debounce } from 'lodash-es'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'

export function useProspectFilterRecipients(props: any) {
    /* ---------------- STATE ---------------- */
    const activeFilters = ref<Record<string, any>>(
        props.filters ? { ...props.filters } : {}
    )

    /* ---------------- COMPUTED ---------------- */
    const activeFilterCount = computed(() =>
        Object.keys(activeFilters.value ?? {}).length
    )

    const isAllCustomers = computed(() =>
        Object.keys(activeFilters.value).length === 0
    )

    const readyFilters = computed(() =>
        Object.fromEntries(
            Object.entries(activeFilters.value).filter(
                ([_, f]: any) => f?.config?.type
            )
        )
    )

     const FILTER_CONFLICTS: Record<string, string[]> = {
        never_contacted: ['last_contacted', 'sent_email_times'],
        last_contacted: ['never_contacted'],
        sent_email_times: ['never_contacted'],
    }

    function hasConflict(newKey: string) {
        const activeKeys = Object.keys(activeFilters.value)
        const conflicts = FILTER_CONFLICTS[newKey] || []
        return activeKeys.find(k => conflicts.includes(k)) || null
    }

    function calculateDateFromPreset(preset: string): string | null {
        if (preset === 'custom') return null

        const weeks = preset === 'one_week_ago' ? 1 :
                     preset === 'two_weeks_ago' ? 2 :
                     preset === 'three_weeks_ago' ? 3 : 3

        const date = new Date()
        date.setDate(date.getDate() - (weeks * 7))
        return date.toISOString().split('T')[0]
    }

    function updateLastContactedMode(filterKey: string, newMode: string) {
        const filter = activeFilters.value[filterKey]
        if (!filter || !filter.config.options?.weeks) return

        filter.value.mode = newMode
        // Only calculate date if mode is not custom
        if (newMode !== 'custom') {
            filter.value.custom_date = calculateDateFromPreset(newMode)
        }
        // If mode is custom, keep the existing custom_date or set to null
    }

    /* ---------------- FILTER ADD ---------------- */
   const addFilter = (key: string, config: any) => {
        const conflictWith = hasConflict(key)
        if (conflictWith) {
            notify({
                title: trans("Filter conflict"),
                text: `"${config.label}" cannot be combined with "${activeFilters.value[conflictWith].config.label}"`,
                type: "error"
            })
            return
        }

        let value: any = true

           if (config.type === 'boolean') {
            value = { value: true }

            if (config.options?.weeks) {
                value.mode = config.options.weeks.default ?? 'three_weeks_ago'
                value.custom_date = calculateDateFromPreset(value.mode)
            }
        }

        activeFilters.value[key] = { value, config }
    }

    const removeFilter = (key: string) => {
        delete activeFilters.value[key]
    }

    const clearAllFilters = () => {
        Object.keys(activeFilters.value).forEach(k => delete activeFilters.value[k])
        fetchCustomers()
    }

    /* ---------------- PAYLOAD ---------------- */
    const filtersPayload = computed(() => {
        const payload: any = {}

        Object.entries(activeFilters.value).forEach(([key, filter]: any) => {
            const val = filter.value
            const config = filter.config

            // BOOLEAN
            if (config.type === 'boolean') {
                const payloadValue: any = {}

                if (!config.options?.date_range && !config.options?.amount_range && !config.options?.count) {
                    payloadValue.value = val.value ?? true
                }

                if (config.options?.date_range) {
                    payloadValue.date_range = val.date_range ?? null
                }

                if (config.options?.amount_range) {
                    payloadValue.amount_range = val.amount_range ?? null
                }

                if (config.options?.count) {
                    payloadValue.count = val.count ?? 3
                }

                if (config.options?.weeks) {
                    payloadValue.mode = val.mode ?? 'three_weeks_ago'
                    payloadValue.custom_date = val.custom_date ?? null
                }

                payload[key] = { value: payloadValue }
                return
            }

            payload[key] = { value: val }
        })

        return payload
    })

    /* ---------------- FETCH CUSTOMERS ---------------- */
    const fetchCustomers = debounce(() => {
        const currentRoute = route().current()
        if (!currentRoute) return

        router.get(
            route(currentRoute, route().params),
            { filters: filtersPayload.value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['customers', 'filters', 'estimatedRecipients']
            }
        )
    }, 400)

    function findConfigByKey(structure: any, key: string) {
        for (const group of Object.values(structure)) {
            if (group.filters?.[key]) return group.filters[key]
        }
        return null
    }

    function normalizeDate(d: string | null) {
        if (!d) return null
        return d.split('T')[0]
    }

    function unwrapBoolean(val: any) {
        let v = val
        while (v && typeof v === 'object' && 'value' in v && typeof v.value === 'object') {
            v = v.value
        }
        return v
    }

    function hydrateSavedFilters(saved: any, structure: any) {
        const hydrated: any = {}

        Object.entries(saved || {}).forEach(([key, wrapper]: any) => {
            const config = findConfigByKey(structure, key)
            if (!config) return
            let val

            if (key === 'orders_in_basket') {
                val = wrapper
            } else {
                val = typeof wrapper === 'object' && 'value' in wrapper
                    ? wrapper
                    : { value: wrapper }
            }

            const raw = val?.value ?? val
            let uiValue: any = {}

            if (config.type === 'boolean') {
                const clean = unwrapBoolean(val)

                uiValue = {
                    value: clean.value ?? true
                }

                // COUNT
                if (config.options?.count) {
                    uiValue.count = clean.count ?? config.options.count.default ?? 3
                }

                // WEEKS
                if (config.options?.weeks) {
                    uiValue.mode = clean.mode ?? config.options.weeks.default ?? 'three_weeks_ago'
                    // Only calculate date from preset if mode is not custom and no custom_date exists
                    if (uiValue.mode !== 'custom' && !clean.custom_date) {
                        uiValue.custom_date = calculateDateFromPreset(uiValue.mode)
                    } else {
                        uiValue.custom_date = clean.custom_date ?? null
                    }
                }
            }
            hydrated[key] = { config, value: uiValue }
        })
        return hydrated
    }

    const saveFilters = async () => {

        let payload = filtersPayload.value

        if (!payload || Object.keys(payload).length === 0) {
            payload = {
                all_prospects: {
                    value: true
                }
            }
        }

        axios
            .patch(
                route(props.recipientFilterRoute.name, props.recipientFilterRoute.parameters) as unknown as string,
                {
                    recipients_recipe: payload
                },
            )
            .then((response) => {

                notify({
                    title: trans('Success!'),
                    text: trans('Success to save filter'),
                    type: 'success',
                })
            })
            .catch((error) => {
                notify({
                    title: trans("Failed to save filter"),
                    type: "error",
                })
            })
            .finally(() => {
                // console.log('finally')
            });
    }
    return {
        hydrateSavedFilters,
        activeFilters,
        activeFilterCount,
        isAllCustomers,
        readyFilters,
        addFilter,
        removeFilter,
        clearAllFilters,
        filtersPayload,
        saveFilters,
        fetchCustomers,
        updateLastContactedMode,
        calculateDateFromPreset
    }
}

    