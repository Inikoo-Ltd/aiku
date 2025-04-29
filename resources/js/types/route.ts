export interface routeType {
    name: string
    parameters?: string[] | { [key: string]: any }
    method?: 'get' | 'post' | 'patch' | 'delete'
    body?: {
        [key: string]: any
    }
}