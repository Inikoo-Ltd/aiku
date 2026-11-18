package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.decodeFromJsonElement

private const val HTTP_OK = 200

@Serializable
private data class SearchResponse(val data: SearchWireResults)

@Serializable
private data class SearchWireResults(
    val query: String? = null,
    val types: List<String?> = emptyList(),
    val exact: JsonElement? = null,
    val groups: List<JsonElement> = emptyList(),
)

@Serializable
private data class SearchWireGroup(
    val type: String? = null,
    val label: String? = null,
    @SerialName("has_more") val hasMore: Boolean = false,
    val rows: List<JsonElement> = emptyList(),
)

@Serializable
private data class SearchWireRow(
    val type: String? = null,
    val id: Long? = null,
    val code: String? = null,
    val title: String? = null,
    val subtitle: String? = null,
    val state: String? = null,
    @SerialName("state_label") val stateLabel: String? = null,
    @Serializable(with = LenientDoubleOrNull::class) val quantity: Double? = null,
    @SerialName("image_url") val imageUrl: String? = null,
    @SerialName("type_group") val typeGroup: String? = null,
)

object SearchResponses {
    private val json = ClockingResponses.json

    fun parseSearch(statusCode: Int, body: String): GoodsOutResult<SearchResults> = when (statusCode) {
        HTTP_OK -> decode<SearchResponse>(body)
            ?.let { GoodsOutResult.Loaded(resultsOf(it.data)) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    private fun resultsOf(wire: SearchWireResults): SearchResults = SearchResults(
        query = wire.query.orEmpty(),
        types = wire.types.mapNotNull { SearchType.ofKey(it) }.distinct(),
        exact = wire.exact?.let(::rowOf),
        groups = wire.groups.mapNotNull(::groupOf),
    )

    private fun groupOf(element: JsonElement): SearchGroup? {
        val wire = runCatching { json.decodeFromJsonElement<SearchWireGroup>(element) }.getOrNull() ?: return null
        val type = SearchType.ofKey(wire.type) ?: return null
        val rows = wire.rows.mapNotNull(::rowOf)
        if (rows.isEmpty()) {
            return null
        }
        return SearchGroup(type = type, label = wire.label.trimmedOrNull().orEmpty(), hasMore = wire.hasMore, rows = rows)
    }

    private fun rowOf(element: JsonElement): SearchRow? {
        val wire = runCatching { json.decodeFromJsonElement<SearchWireRow>(element) }.getOrNull() ?: return null
        val type = SearchType.ofRowType(wire.type) ?: return null
        val id = wire.id ?: return null
        val code = wire.code.trimmedOrNull().orEmpty()
        return SearchRow(
            type = type,
            id = id,
            code = code,
            title = wire.title.trimmedOrNull() ?: code,
            subtitle = wire.subtitle.trimmedOrNull().orEmpty(),
            state = wire.state.trimmedOrNull().orEmpty(),
            stateLabel = wire.stateLabel.trimmedOrNull().orEmpty(),
            quantity = wire.quantity,
            imageUrl = wire.imageUrl.trimmedOrNull(),
            typeGroup = wire.typeGroup.trimmedOrNull(),
        )
    }

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()

    private fun String?.trimmedOrNull(): String? = this?.trim()?.takeIf { it.isNotEmpty() }
}
