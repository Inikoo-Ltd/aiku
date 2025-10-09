/**
 * GridProducts Component Types
 * 
 * TypeScript interfaces and types for GridProducts and its sub-components
 */

// ============================================================================
// PRODUCT INTERFACES
// ============================================================================

/**
 * Image source with multiple formats and resolutions
 */
export interface ImageSource {
    original: string
    avif?: string
    webp?: string
    png?: string
    avif_2x?: string
    webp_2x?: string
    png_2x?: string
    original_2x?: string
}

/**
 * Product image with metadata and multiple sources
 */
export interface ProductImage {
    id: number
    is_animated: boolean
    slug: string
    uuid: string
    name: string
    mime_type: string
    size: string
    thumbnail: ImageSource
    source: ImageSource
    created_at: string
    was_recently_created: boolean
}

/**
 * Main product interface
 */
export interface Product {
    id: number | string
    name: string
    code: string
    slug?: string
    url?: string
    description?: string
    image?: ProductImage | string  // Can be either complex object or simple URL
    price?: number | string  // Can be either number or string like "24.27"
    currency?: string
    currency_code?: string  // Alternative currency field
    is_favourite?: boolean
    stock_status?: 'in_stock' | 'out_of_stock' | 'low_stock'
    stock_quantity?: number
    unit?: string
    rrp?: number
    image_thumbnail?: any  // Legacy support
    images?: any[]  // Legacy support
    [key: string]: any  // Allow additional properties
}

// ============================================================================
// QUERY BUILDER INTERFACES
// ============================================================================

export interface QueryBuilderData {
    searchInputs?: Array<{ key: string; value: any }>
    filters?: Array<{ key: string; value: any }>
    sort?: string | null
    cursor?: string | null
    page?: number
    perPage?: number
    elementFilter?: Record<string, any>
    periodFilter?: Record<string, any>
    radioFilter?: string
    dateInterval?: any
}

export interface QueryBuilderProps {
    pageName?: string
    labelRecord?: string[]
    exportLinks?: any
    perPageOptions?: number[]
    defaultSort?: string
    defaultVisibleToggleableColumns?: string[]
    hasToggleableColumns?: boolean
    hasFilters?: boolean
    hasSearchInputs?: boolean
    globalSearch?: {
        label: string
        value: string | null
    }
    searchInputs?: Array<{ key: string; value: any }>
    filters?: Array<{ key: string; value: any }>
    columns?: Array<{
        key: string
        label: string
        hidden?: boolean
        can_be_hidden?: boolean
    }>
    [key: string]: any
}

// ============================================================================
// COMPONENT PROPS & EMITS
// ============================================================================

export interface GridProductsProps {
    name?: string
    resource?: {
        data?: Product[]
        meta?: any
        links?: any
        [key: string]: any
    }
    meta?: Record<string, any>
    data?: Product[]
    preserveScroll?: boolean | string
    preventOverlappingRequests?: boolean
    inputDebounceMs?: number
    isParentLoading?: boolean
}

export interface GridProductsEmits {
    (e: 'toggleFavorite', product: Product): void
}

export interface ProductCardProps {
    product: Product
}

export interface ProductCardEmits {
    (e: 'toggle-favorite', product: Product): void
}

export interface RecordCounterProps {
    total: number
    labelSingular: string
    labelPlural: string
}

export interface EmptyStateProps {
    message: string
    description?: string
    icon?: string
}

// ============================================================================
// UTILITY TYPES
// ============================================================================

export interface PaginationMeta {
    total: number
    per_page: number
    current_page: number
    last_page: number
    from: number
    to: number
    next_page_url?: string | null
    prev_page_url?: string | null
    path?: string
    links?: Array<{
        url: string | null
        label: string
        active: boolean
    }>
}

export interface ResourceResponse<T = Product> {
    data: T[]
    meta?: PaginationMeta
    links?: {
        first?: string
        last?: string
        prev?: string | null
        next?: string | null
    }
}