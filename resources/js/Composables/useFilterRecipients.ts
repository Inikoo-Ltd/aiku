import { ref, computed, reactive, watch } from 'vue'
import { debounce } from 'lodash-es'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'

export function useFilterRecipients(props: any) {
    /* ---------------- STATE ---------------- */
    const activeFilters = ref<Record<string, any>>(
        props.filters ? { ...props.filters } : {}
    )

    const preloadedEntities = reactive<Record<string, any[]>>({})

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

    const isByOrderValueInvalid = computed(() => {
        const f = activeFilters.value['by_order_value']
        if (!f) return false

        return f.value.amount_range?.min == null
    })

    const isAmountRangeInvalid = (filter: any) => {
        const min = filter.value.amount_range?.min
        return min == null
    }

     const FILTER_CONFLICTS: Record<string, string[]> = {
        registered_never_ordered: ['orders_in_basket','by_order_value','orders_collection','by_family','by_subdepartment','by_family_never_ordered','by_showroom_orders','by_department'],
        orders_in_basket: ['registered_never_ordered'],
        by_order_value: ['registered_never_ordered'],
        orders_collection: ['registered_never_ordered'],
        by_family: ['registered_never_ordered'],
        by_subdepartment: ['registered_never_ordered'],
        by_family_never_ordered: ['registered_never_ordered'],
        by_showroom_orders: ['registered_never_ordered'],
        by_department: ['registered_never_ordered']
    }

    function hasConflict(newKey: string) {
        const activeKeys = Object.keys(activeFilters.value)
        const conflicts = FILTER_CONFLICTS[newKey] || []
        return activeKeys.find(k => conflicts.includes(k)) || null
    }

    /* ---------------- FILTER ADD ---------------- */
   const addFilter = (key: string, config: any) => {
        const conflictWith = hasConflict(key)
        if (conflictWith) {
            notify({
                title: "Filter conflict",
                text: `"${config.label}" cannot be combined with "${activeFilters.value[conflictWith].config.label}"`,
                type: "error"
            })
            return
        }

        let value: any = true

       if (config.type === 'boolean') {
            value = { value: true }

            if (config.options?.date_range) {
                value.date_range = null
            }

            if (config.options?.amount_range) {
                value.amount_range = {
                    min: null,
                    max: null,
                }
            }

            if (config.options?.date_range?.presets) {
                value.date_range_preset = null
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

                if (!config.options?.date_range && !config.options?.amount_range) {
                    payloadValue.value = val.value ?? true
                }

                if (config.options?.date_range) {
                    payloadValue.date_range = val.date_range ?? null
                }

                if (config.options?.amount_range) {
                    payloadValue.amount_range = val.amount_range ?? null
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

            // LOCATION
            if (config.type === 'location') {
                payload[key] = {
                    value: {
                        mode: val.mode,
                        country_ids: val.country_ids,
                        postal_codes: val.postal_codes,
                        location: val.location,
                        radius: val.radius === 'custom'
                            ? Number(val.radius_custom)
                            : Number(val.radius),
                        lat: val.lat ?? 0,
                        lng: val.lng ?? 0,
                    }
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

    const presetMeters = ['5000', '10000', '25000', '50000', '100000']

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
                all_customers: {
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
                    title: "Failed to save filter",
                    type: "error",
                })
            })
            .finally(() => {
                // console.log('finally')
            });
    }

    /* ---------------- LOCATION GEOCODE ---------------- */
    const getLatLngToLocation = async (filter: any, forceMode?: 'forward' | 'reverse') => {
        const v = filter.value
        let params: any = {}

        const mode =
            forceMode ||
            (v.lastSource === 'map' ? 'reverse' : 'forward')

        if (mode === 'reverse') {
            if (!v.lat || !v.lng) return
            params.latitude = v.lat
            params.longitude = v.lng
        } else {
            if (!v.location) return
            params.location = v.location
        }

        v.loadingMap = true

        try {
            const res = await axios.get(route('grp.json.get_geocode'), { params })
            const data = res.data

            if (data.latitude && data.longitude) {
                v.lat = Number(data.latitude)
                v.lng = Number(data.longitude)
                v.zoom = v.zoom || 12
            }

            if (data.city || data.formatted_address) {
                v.location = data.city || data.formatted_address
            }
            
            v.resolved = true
        } finally {
            v.loadingMap = false
        }
    }

    const debouncedReverseGeocode = debounce(getLatLngToLocation, 500)

    const onMapClick = (e: any, filter: any) => {
        const v = filter.value

        v.lat = Number(e.latlng.lat)
        v.lng = Number(e.latlng.lng)
        v.lastSource = 'map'

        debouncedReverseGeocode(filter)
    }

    const onMarkerDrag = (e: any, filter: any) => {
        const pos = e.target.getLatLng()
        const v = filter.value

        v.lat = Number(pos.lat)
        v.lng = Number(pos.lng)
        v.lastSource = 'map'

        debouncedReverseGeocode(filter)
    }

    const shouldShowMap = (val: any) => {
        if (val.mode === 'radius') return true
        if (val.mode === 'direct' && (val.country_ids?.length || val.postal_codes?.length)) return true
        return false
    }

    const radiusInMeters = (val: any) => {
        const km = val.radius === 'custom'
            ? Number(val.radius_custom) || 0
            : Number(val.radius) || 0

        return km * 1000
    }

    const getPostalCodeModel = (filter: any) => {
        return computed({
            get: () => filter.value.postal_codes?.join(', ') || '',
            set: (val: string) => {
                filter.value.postal_codes = val
                    .split(',')
                    .map(v => v.trim())
                    .filter(Boolean)
            }
        })
    }
    return {
        isByOrderValueInvalid,
        hydrateSavedFilters,
        isAmountRangeInvalid,
        activeFilters,
        activeFilterCount,
        isAllCustomers,
        readyFilters,
        radiusInMeters,
        shouldShowMap,
        getPostalCodeModel,
        addFilter,
        removeFilter,
        clearAllFilters,
        filtersPayload,
        onMapClick,
        onMarkerDrag,
        saveFilters,
        fetchCustomers,
        preloadedEntities,
        getLatLngToLocation,
    }
}
function dayDiff(arg0: any, arg1: any) {
    throw new Error('Function not implemented.')
}

