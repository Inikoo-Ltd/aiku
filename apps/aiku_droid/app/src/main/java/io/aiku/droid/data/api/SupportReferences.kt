package io.aiku.droid.data.api

import java.time.Duration
import java.time.Instant
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import okhttp3.Interceptor
import okhttp3.Response

private const val SERVER_ERROR_FROM = 500
private const val MAX_PEEKED_BYTES = 64L * 1024
private val RECENT = Duration.ofMinutes(10)

data class SupportReference(val reference: String, val statusCode: Int, val at: Instant)

object SupportReferences {
    private val mutableLatest = MutableStateFlow<SupportReference?>(null)

    val latest: StateFlow<SupportReference?> = mutableLatest.asStateFlow()

    fun record(reference: SupportReference) {
        mutableLatest.value = reference
    }

    fun recent(now: Instant = Instant.now()): SupportReference? =
        mutableLatest.value?.takeIf { !it.at.isAfter(now) && Duration.between(it.at, now) <= RECENT }

    fun clear() {
        mutableLatest.value = null
    }
}

class SupportReferenceInterceptor(private val clock: () -> Instant = Instant::now) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val response = chain.proceed(chain.request())
        if (response.code >= SERVER_ERROR_FROM) {
            supportReferenceOf(response.peekBody(MAX_PEEKED_BYTES).string())?.let { reference ->
                SupportReferences.record(SupportReference(reference = reference, statusCode = response.code, at = clock()))
            }
        }
        return response
    }
}

internal fun supportReferenceOf(body: String): String? = runCatching {
    Json.parseToJsonElement(body).jsonObject["reference"]?.jsonPrimitive?.contentOrNull
}.getOrNull()?.trim()?.takeIf { it.isNotEmpty() }
