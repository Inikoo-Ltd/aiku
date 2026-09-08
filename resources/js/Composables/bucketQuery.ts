export function bucketQuery(bucket?: string, extra: Record<string, string> = {}): string {
    const params = new URLSearchParams(bucket ? { bucket, ...extra } : { ...extra })
    const search = new URLSearchParams(location.search)
    const sort = search.get('sort') ?? (bucket ? search.get(`${bucket}_sort`) : null)

    if (sort) {
        params.set('bucket_sort', sort)
    }

    const query = params.toString()

    return query ? `?${query}` : ''
}
