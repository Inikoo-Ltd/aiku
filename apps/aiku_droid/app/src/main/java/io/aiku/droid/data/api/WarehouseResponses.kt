package io.aiku.droid.data.api

import kotlinx.serialization.Serializable

private const val HTTP_OK = 200
private const val HTTP_UNAUTHORISED = 401
private const val HTTP_FORBIDDEN = 403
private const val HTTP_NOT_FOUND = 404

@Serializable
private data class WarehousesResponse(val data: List<WarehouseAccess> = emptyList())

object WarehouseResponses {

    fun parse(statusCode: Int, body: String): WarehousesResult = when (statusCode) {
        HTTP_OK -> runCatching { AuthResponses.json.decodeFromString<WarehousesResponse>(body) }
            .map { WarehousesResult.Loaded(it.data) }
            .getOrElse { WarehousesResult.ServerError(statusCode) }
        HTTP_UNAUTHORISED, HTTP_FORBIDDEN -> WarehousesResult.SessionExpired
        HTTP_NOT_FOUND -> WarehousesResult.AppKeyRejected
        else -> WarehousesResult.ServerError(statusCode)
    }
}
