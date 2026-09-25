const PRODUCT_SNIPPET_REQUIRED_PROPERTIES = ["offers", "review", "aggregateRating"]

export const isEligibleForProductSnippet = (
    productNode: Record<string, unknown> | null | undefined
): boolean => {
    return PRODUCT_SNIPPET_REQUIRED_PROPERTIES.some(
        property => productNode?.[property] !== null && productNode?.[property] !== undefined
    )
}
