package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.decodeFromJsonElement

private const val HTTP_OK = 200

@Serializable
private data class BadgesResponse(val data: WorkBadges)

@Serializable
private data class MyWorkResponse(val data: List<JsonElement> = emptyList(), val counts: MyWorkCounts? = null)

@Serializable
private data class MyWorkWireRow(
    val kind: String = "",
    val id: Long? = null,
    val reference: String = "",
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val step: String = "",
    @SerialName("step_label") val stepLabel: String = "",
    @SerialName("type_group") val typeGroup: String? = null,
    @Serializable(with = LenientIntOrNull::class) val items: Int? = null,
    val customer: String? = null,
    val trolleys: List<String?> = emptyList(),
    val since: String? = null,
)

object MyWorkResponses {
    private val json = ClockingResponses.json

    fun parseBadges(statusCode: Int, body: String): GoodsOutResult<WorkBadges> = when (statusCode) {
        HTTP_OK -> decode<BadgesResponse>(body)
            ?.let { GoodsOutResult.Loaded(it.data) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseMyWork(statusCode: Int, body: String): GoodsOutResult<MyWorkPage> = when (statusCode) {
        HTTP_OK -> decode<MyWorkResponse>(body)
            ?.let { GoodsOutResult.Loaded(MyWorkPage(rows = it.data.mapNotNull(::rowOf), counts = it.counts)) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    private fun rowOf(element: JsonElement): MyWorkRow? {
        val wire = runCatching { json.decodeFromJsonElement<MyWorkWireRow>(element) }.getOrNull() ?: return null
        val kind = MyWorkKind.ofKey(wire.kind) ?: return null
        val id = wire.id ?: return null
        return MyWorkRow(
            kind = kind,
            id = id,
            reference = wire.reference,
            state = wire.state,
            stateLabel = wire.stateLabel,
            step = wire.step,
            stepLabel = wire.stepLabel,
            typeGroup = wire.typeGroup.trimmedOrNull(),
            items = wire.items,
            customer = wire.customer.trimmedOrNull(),
            trolleys = wire.trolleys.mapNotNull { it.trimmedOrNull() },
            since = wire.since.trimmedOrNull(),
        )
    }

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()

    private fun String?.trimmedOrNull(): String? = this?.trim()?.takeIf { it.isNotEmpty() }
}
