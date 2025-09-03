/**
 * GridProducts Module Export
 * 
 * All GridProducts-related components and types are exported from here
 */

// ============================================================================
// MAIN COMPONENT
// ============================================================================

// Default export for simple import: import GridProducts from '@/Components/Product/GridProducts'
import GridProducts from './GridProducts.vue'
export default GridProducts

// ============================================================================
// NAMED EXPORTS
// ============================================================================

// Components
export { default as GridProducts } from './GridProducts.vue'
export { default as ProductCard } from './ProductCard.vue'
export { default as RecordCounter } from './RecordCounter.vue'
export { default as EmptyState } from './EmptyState.vue'

// ============================================================================
// TYPE EXPORTS
// ============================================================================

// Re-export all types from types.ts
export * from './types'

// Specific type exports for convenience
export type {
    Product,
    ProductImage,
    QueryBuilderData,
    QueryBuilderProps,
    GridProductsProps,
    GridProductsEmits,
    ProductCardProps,
    ProductCardEmits,
    RecordCounterProps,
    EmptyStateProps,
    PaginationMeta,
    ResourceResponse
} from './types'