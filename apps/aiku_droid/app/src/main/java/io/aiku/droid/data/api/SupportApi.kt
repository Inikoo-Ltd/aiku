package io.aiku.droid.data.api

import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive

private const val HTTP_CREATED = 201
private const val HTTP_UNAUTHORIZED = 401
private const val HTTP_UNPROCESSABLE = 422
private const val HTTP_TOO_MANY_REQUESTS = 429

enum class ProblemCategory(val key: String) {
    UnknownBarcode("unknown_barcode"),
    WrongStock("wrong_stock"),
    DataProblem("data_problem"),
    AppError("app_error"),
    Other("other"),
}

data class ProblemReport(
    val category: ProblemCategory,
    val details: String,
    val screen: String? = null,
    val errorCode: String? = null,
    val supportReference: String? = null,
    val lastScan: String? = null,
    val warehouseId: Long? = null,
    val images: List<UploadFile> = emptyList(),
)

sealed interface ProblemReportResult {
    data class Sent(val reference: String) : ProblemReportResult
    data class Invalid(val message: String) : ProblemReportResult
    data object TooMany : ProblemReportResult
    data object SessionExpired : ProblemReportResult
    data object Unreachable : ProblemReportResult
    data class ServerError(val statusCode: Int) : ProblemReportResult
}

interface SupportApi {
    suspend fun reportProblem(token: String, report: ProblemReport): ProblemReportResult
}

@Serializable
private data class SentTicket(val reference: String = "")

object SupportResponses {
    const val IMAGES_FIELD = "images[]"

    private val json = Json { ignoreUnknownKeys = true }

    fun fields(report: ProblemReport): List<Pair<String, String>> = listOfNotNull(
        "category" to report.category.key,
        "details" to report.details.trim(),
        report.screen?.trim()?.takeIf { it.isNotEmpty() }?.let { "screen" to it },
        report.errorCode?.let { "error_code" to it },
        report.supportReference?.let { "support_reference" to it },
        report.lastScan?.trim()?.takeIf { it.isNotEmpty() }?.let { "last_scan" to it },
        report.warehouseId?.let { "warehouse_id" to it.toString() },
    )

    fun parse(statusCode: Int, body: String): ProblemReportResult = when (statusCode) {
        HTTP_CREATED -> runCatching {
            ProblemReportResult.Sent(json.decodeFromJsonElement(SentTicket.serializer(), json.parseToJsonElement(body).jsonObject.getValue("data")).reference)
        }.getOrElse { ProblemReportResult.ServerError(statusCode) }
        HTTP_UNAUTHORIZED -> ProblemReportResult.SessionExpired
        HTTP_UNPROCESSABLE -> ProblemReportResult.Invalid(firstError(body).orEmpty())
        HTTP_TOO_MANY_REQUESTS -> ProblemReportResult.TooMany
        else -> ProblemReportResult.ServerError(statusCode)
    }

    private fun firstError(body: String): String? = runCatching {
        val root = json.parseToJsonElement(body).jsonObject
        (root["errors"] as? JsonObject)?.values?.firstOrNull()?.jsonArray?.firstOrNull()?.jsonPrimitive?.contentOrNull
            ?: root["message"]?.jsonPrimitive?.contentOrNull
    }.getOrNull()
}
