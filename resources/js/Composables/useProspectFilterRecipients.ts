/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 8 Apr 2026 15:03:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

import { ref, computed, reactive, watch } from 'vue'
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

            // if (config.options?.date_range) {
            //     value.date_range = null
            // }

            // if (config.options?.amount_range) {
            //     value.amount_range = {
            //         min: null,
            //         max: null,
            //     }
            // }

            // if (config.options?.date_range?.presets) {
            //     value.date_range_preset = null
            // }

            // if (config.options?.count) {
            //     value.count = config.options.count.default ?? 3
            // }

            if (config.options?.weeks) {
                value.mode = config.options.weeks.default ?? 'three_weeks_ago'
                value.custom_date = calculateDateFromPreset(value.mode)
            }
        }

        if (config.type === 'select') value = config.options?.[0]?.value ?? null
        if (config.type === 'multiselect') value = config.label === 'By Family Never Ordered' ? { ids: null } : config.behavior_options ? { ids: [], behaviors: ['purchased'], combine_logic: 'or' } : { ids: [] }
        if (config.type === 'daterange') value = { date_range: null }
        if (config.type === 'entity_behaviour') value = { ids: [], behaviors: [], combine_logic: true }
        if (config.type === 'location') value = { mode:'direct', country_ids:[], postal_codes:[], location:'', radius:null, radius_custom:null, lat:null, lng:null, resolved:false }

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

            // ENTITY BEHAVIOUR
            if (config.type === 'entity_behaviour') {
                payload[key] = {
                    value: {
                        ids: val.ids ?? [],
                        behaviors: val.combine_logic
                            ? (val.behaviors ?? [])
                            : (val.behaviors?.length ? [val.behaviors[0]] : []),
                        combine_logic: val.combine_logic ?? true
                    }
                }
                return
            }

            // MULTISELECT (simple)
            if (config.type === 'multiselect') {
                if (config.label === 'By Family Never Ordered') {
                    payload[key] = {
                        value: val.ids != null ? [val.ids] : []
                    }
                } else {
                    payload[key] = {
                        value: val.ids ?? []
                    }
                }
                return
            }

            // SELECT
            if (config.type === 'select') {
                payload[key] = {
                    value: val
                }
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

    function dayDiff(start: string | number | Date, end: string | number | Date): number {
        const s = new Date(start).getTime()
        const e = new Date(end).getTime()
        return Math.round((e - s) / (1000*60*60*24))
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

                // DATE RANGE
                if (config.options?.date_range) {
                    const dr = clean.date_range

                    uiValue.date_range = Array.isArray(dr)
                        ? [normalizeDate(dr[0]), normalizeDate(dr[1])]
                        : null

                    // preset detection
                    if (key === 'orders_in_basket' && Array.isArray(dr)) {
                        const diff = dayDiff(dr[0], dr[1])
                        if ([3,7,14].includes(diff)) uiValue.mode = diff
                        else uiValue.mode = 'custom'
                    }
                }

                // AMOUNT RANGE
                if (config.options?.amount_range) {
                    uiValue.amount_range = clean.amount_range ?? {
                        min: null,
                        max: null,
                        currency: config.options.amount_range.currency || 'GBP'
                    }
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

            else if (config.type === 'select') {
                uiValue = typeof val === 'object' && 'value' in val
                    ? val.value
                    : val
            }

            else if (config.type === 'multiselect') {
                if (config.behavior_options) {
                    uiValue = {
                        ids: val?.ids ?? [],
                        behaviors: val?.behaviors ?? ['purchased'],
                        combine_logic: val?.combine_logic ?? 'or'
                    }
                }

                else {
                    const src = wrapper?.value ?? wrapper

                    if (config.label === 'By Family Never Ordered') {
                        uiValue = {
                            ids: Array.isArray(src) ? src[0] ?? null : src ?? null
                        }
                    } else {
                        uiValue = {
                            ids: Array.isArray(src)
                                ? src
                                : Array.isArray(src?.ids)
                                    ? src.ids
                                    : []
                        }
                    }
                }
            }

            else if (config.type === 'entity_behaviour') {
                uiValue = {
                    ids: raw.ids ?? [],
                    behaviors: Array.isArray(raw.behaviors)
                        ? raw.behaviors
                        : raw.behaviors
                            ? [raw.behaviors]
                            : [],
                    combine_logic: typeof raw.combine_logic === 'boolean'
                        ? raw.combine_logic
                        : true
                }
            }

            else if (config.type === 'location') {
                const v = val?.value ?? {}

                let radiusPreset = '5000'
                let radiusCustom = null

                if (v.radius) {
                    const asString = String(v.radius)

                    if (presetMeters.includes(asString)) {
                        radiusPreset = asString
                    } else {
                        radiusPreset = 'custom'
                        radiusCustom = Number(v.radius)
                    }
                }

                uiValue = {
                    mode: v.mode ?? 'direct',
                    country_ids: v.country_ids ?? [],
                    postal_codes: v.postal_codes ?? [],
                    location: v.location ?? '',
                    radius: radiusPreset,
                    radius_custom: radiusCustom,
                    lat: v.lat ?? 0,
                    lng: v.lng ?? 0,
                    zoom: 10
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

