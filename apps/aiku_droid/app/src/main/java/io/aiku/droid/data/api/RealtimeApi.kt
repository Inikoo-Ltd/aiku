package io.aiku.droid.data.api

import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import kotlinx.serialization.json.put

private const val HTTP_OK = 200
private const val SECURE_SCHEME = "https"
private const val PROTOCOL_VERSION = 7

@Serializable
data class RealtimeServer(
    val key: String,
    val cluster: String? = null,
    val host: String,
    val port: Int,
    val scheme: String = SECURE_SCHEME,
) {
    fun socketUrl(client: String, version: String): String {
        val socketScheme = if (scheme == SECURE_SCHEME) "wss" else "ws"
        return "$socketScheme://$host:$port/app/$key?protocol=$PROTOCOL_VERSION&client=$client&version=$version&flash=false"
    }
}

interface RealtimeApi {
    suspend fun realtimeServer(token: String): GoodsOutResult<RealtimeServer?>

    suspend fun authorizeChannel(token: String, socketId: String, channel: String): String?
}

object RealtimeResponses {
    private val json = Json { ignoreUnknownKeys = true }

    fun parseServer(statusCode: Int, body: String): GoodsOutResult<RealtimeServer?> = when (statusCode) {
        HTTP_OK -> runCatching {
            val data = json.parseToJsonElement(body).jsonObject["data"]
            GoodsOutResult.Loaded(if (data == null || data is JsonNull) null else json.decodeFromJsonElement(RealtimeServer.serializer(), data))
        }.getOrElse { GoodsOutResult.ServerError(statusCode) }
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun authBody(socketId: String, channel: String): String = buildJsonObject {
        put("socket_id", socketId)
        put("channel_name", channel)
    }.toString()

    fun parseAuth(statusCode: Int, body: String): String? = if (statusCode == HTTP_OK) {
        runCatching { json.parseToJsonElement(body).jsonObject["auth"]?.jsonPrimitive?.contentOrNull }.getOrNull()?.takeIf { it.isNotBlank() }
    } else {
        null
    }
}
