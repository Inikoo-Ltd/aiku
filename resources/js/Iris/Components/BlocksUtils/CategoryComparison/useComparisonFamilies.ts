import axios from "axios"
import { computed, ref, watch, type Ref } from "vue"
import type {
    CategoryComparisonValue,
    ComparisonFamily,
    ScreenType,
} from "@/Iris/Components/BlocksUtils/CategoryComparison/types"

export const DEFAULT_NUMBER_OF_FAMILIES: Record<ScreenType, number> = {
    mobile: 2,
    tablet: 3,
    desktop: 4,
}

export const useComparisonFamilies = (
    optionRouteName: string,
    detailRouteName: string,
    fetchParameters: Ref<Record<string, string> | undefined>,
    fieldValue: Ref<CategoryComparisonValue | undefined>,
    screenType: Ref<ScreenType>
) => {
    const loadingOptions = ref(false)
    const loadingDetails = ref(false)
    const fetchedFamilies = ref<ComparisonFamily[]>([])
    const details = ref<Record<string, ComparisonFamily>>({})
    const requestedSlugs = new Set<string>()

    const loadFamilies = async () => {
        details.value = {}
        requestedSlugs.clear()

        if (!fetchParameters.value) {
            fetchedFamilies.value = []
            return
        }

        loadingOptions.value = true

        try {
            const response = await axios.get(route(optionRouteName, fetchParameters.value))

            fetchedFamilies.value = response.data?.data ?? []
        } catch (error) {
            console.error("Failed loading families for comparison:", error)
            fetchedFamilies.value = []
        } finally {
            loadingOptions.value = false
        }
    }

    const loadDetails = async (slugs: string[]) => {
        const missingSlugs = slugs.filter(slug => !requestedSlugs.has(slug))

        if (!fetchParameters.value || !missingSlugs.length) {
            return
        }

        const productCategories = missingSlugs
            .map(slug => fetchedFamilies.value.find(family => family.slug === slug)?.id)
            .filter(Boolean)

        if (!productCategories.length) {
            return
        }

        missingSlugs.forEach(slug => requestedSlugs.add(slug))
        loadingDetails.value = true

        try {
            const response = await axios.get(route(detailRouteName, fetchParameters.value), {
                params: { product_category: productCategories },
            })

            const loaded: ComparisonFamily[] = response.data?.data ?? []
            const merged = { ...details.value }

            loaded.forEach(family => {
                if (family.slug) {
                    merged[family.slug] = family
                }
            })

            details.value = merged
        } catch (error) {
            console.error("Failed loading comparison details:", error)
            missingSlugs.forEach(slug => requestedSlugs.delete(slug))
        } finally {
            loadingDetails.value = false
        }
    }

    const currentFamily = computed(() => fetchedFamilies.value.find(family => family.is_current))

    const familyOptions = computed<ComparisonFamily[]>(
        () => fetchedFamilies.value.filter(family => !family.is_current)
    )

    const numberOfFamilies = computed(() => {
        const configured = fieldValue.value?.number_of_families
        const forScreen = typeof configured === "object" && configured !== null
            ? configured[screenType.value] ?? configured.desktop
            : configured

        return Number(forScreen) > 0
            ? Number(forScreen)
            : DEFAULT_NUMBER_OF_FAMILIES[screenType.value]
    })

    const numberOfComparedFamilies = computed(() =>
        Math.max(numberOfFamilies.value - (currentFamily.value ? 1 : 0), 0)
    )

    const selectedSlugs = ref<string[]>([])

    const familyOf = (slug: string) =>
        details.value[slug] ?? fetchedFamilies.value.find(family => family.slug === slug)

    const comparedSlugs = computed<string[]>(() => {
        const slugs = currentFamily.value?.slug ? [currentFamily.value.slug] : []

        return [...slugs, ...selectedSlugs.value]
    })

    const families = computed<ComparisonFamily[]>(
        () => comparedSlugs.value
            .map(familyOf)
            .filter((family): family is ComparisonFamily => Boolean(family))
    )

    const loading = computed(
        () => loadingOptions.value || (loadingDetails.value && !Object.keys(details.value).length)
    )

    const hasComparableFamilies = computed(() => fetchedFamilies.value.length > 1)

    const toggleFamily = (slug: string) => {
        if (selectedSlugs.value.includes(slug)) {
            selectedSlugs.value = selectedSlugs.value.filter(selected => selected !== slug)
            return
        }

        if (selectedSlugs.value.length >= numberOfComparedFamilies.value) {
            return
        }

        selectedSlugs.value = [...selectedSlugs.value, slug]
    }

    watch([familyOptions, numberOfComparedFamilies], () => {
        const optionSlugs = familyOptions.value.map(family => family.slug as string)
        const kept = selectedSlugs.value.filter(slug => optionSlugs.includes(slug))
        const filler = optionSlugs.filter(slug => !kept.includes(slug))

        selectedSlugs.value = [...kept, ...filler].slice(0, numberOfComparedFamilies.value)
    }, { immediate: true })

    watch(comparedSlugs, loadDetails, { immediate: true })

    watch(fetchParameters, loadFamilies, { immediate: true })

    return {
        families,
        familyOptions,
        selectedSlugs,
        numberOfComparedFamilies,
        toggleFamily,
        loading,
        hasComparableFamilies,
        loadFamilies,
        loadDetails,
    }
}
