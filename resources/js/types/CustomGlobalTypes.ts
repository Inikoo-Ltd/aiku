/**
 * Author: Vika Aqordi
 * Created on 09-03-2026-11h-59m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

import 'vue'

declare module 'vue' {
    interface ComponentCustomProperties {
        ctrans: (key: string, replacements?: Record<string, unknown>) => string
    }
}

export {}