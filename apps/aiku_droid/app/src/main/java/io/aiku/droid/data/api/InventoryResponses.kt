package io.aiku.droid.data.api

import kotlinx.serialization.KSerializer
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.descriptors.PrimitiveKind
import kotlinx.serialization.descriptors.PrimitiveSerialDescriptor
import kotlinx.serialization.descriptors.SerialDescriptor
import kotlinx.serialization.encoding.Decoder
import kotlinx.serialization.encoding.Encoder
import kotlinx.serialization.json.JsonDecoder
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonEncoder
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.addJsonObject
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.decodeFromJsonElement
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.put
import kotlinx.serialization.json.putJsonArray

private const val HTTP_OK = 200

private fun Decoder.lenientNumber(): Double? {
    val element = (this as? JsonDecoder)?.decodeJsonElement() ?: return decodeDouble()
    val primitive = element as? JsonPrimitive ?: return null
    if (primitive is JsonNull) {
        return null
    }
    return primitive.content.trim().toDoubleOrNull()
}

private fun Encoder.encodeNumber(value: Number?) {
    if (this is JsonEncoder) {
        encodeJsonElement(JsonPrimitive(value))
    } else {
        encodeDouble(value?.toDouble() ?: 0.0)
    }
}

object LenientDouble : KSerializer<Double> {
    override val descriptor: SerialDescriptor = PrimitiveSerialDescriptor("io.aiku.droid.LenientDouble", PrimitiveKind.DOUBLE)

    override fun deserialize(decoder: Decoder): Double = decoder.lenientNumber() ?: 0.0

    override fun serialize(encoder: Encoder, value: Double) = encoder.encodeNumber(value)
}

object LenientDoubleOrNull : KSerializer<Double?> {
    override val descriptor: SerialDescriptor = PrimitiveSerialDescriptor("io.aiku.droid.LenientDoubleOrNull", PrimitiveKind.DOUBLE)

    override fun deserialize(decoder: Decoder): Double? = decoder.lenientNumber()

    override fun serialize(encoder: Encoder, value: Double?) = encoder.encodeNumber(value)
}

object LenientInt : KSerializer<Int> {
    override val descriptor: SerialDescriptor = PrimitiveSerialDescriptor("io.aiku.droid.LenientInt", PrimitiveKind.INT)

    override fun deserialize(decoder: Decoder): Int = decoder.lenientNumber()?.toInt() ?: 0

    override fun serialize(encoder: Encoder, value: Int) = encoder.encodeNumber(value)
}

@Serializable
private data class InventoryData<T>(val data: T)

@Serializable
private data class LocationContents(
    val pallets: List<LocationPallet> = emptyList(),
    @SerialName("org_stocks") val orgStocks: List<LocationOrgStock> = emptyList(),
    val can: StockRights? = null,
)

@Serializable
private data class LocationListResponse(val data: List<LocationSummary> = emptyList(), val meta: ListMeta = ListMeta())

@Serializable
private data class MovementListResponse(val data: List<StockMovement> = emptyList(), val meta: ListMeta = ListMeta())

@Serializable
private data class OrgStockListResponse(
    val data: List<OrgStockRow> = emptyList(),
    val meta: ListMeta = ListMeta(),
    val counts: SkoCounts? = null,
    @SerialName("currency_code") val currencyCode: String? = null,
)

@Serializable
private data class WriteResultBlock(val status: String = "", val message: String? = null)

@Serializable
private data class OrgStockWriteResponse(val data: OrgStockDetail, val result: WriteResultBlock = WriteResultBlock())

@Serializable
private data class FamilyListResponse(val data: List<OrgStockFamily> = emptyList(), val meta: ListMeta = ListMeta(), val counts: FamilyCounts? = null)

@Serializable
private data class FamilySkoListResponse(val family: FamilySummary? = null, val data: List<FamilySko> = emptyList(), val meta: ListMeta = ListMeta())

@Serializable
private data class RemovedResponse(val removed: Int? = null)

object InventoryResponses {
    private val json = ClockingResponses.json

    fun areasParameters(search: String?): List<Pair<String, String>> = listOfNotNull(search.trimmedOrNull()?.let { "q" to it })

    fun locationsParameters(query: LocationQuery): List<Pair<String, String>> = buildList {
        query.tab.key?.let { add("occupancy" to it) }
        query.search.trimmedOrNull()?.let { add("q" to it) }
        query.areaId?.let { add("area_id" to it.toString()) }
        query.areaIds.sorted().forEach { add("area_ids[]" to it.toString()) }
        query.statuses.sortedBy { it.ordinal }.forEach { add("statuses[]" to it.key) }
        query.scopes.sortedBy { it.ordinal }.forEach { add("scopes[]" to it.key) }
        query.occupancies.sortedBy { it.ordinal }.forEach { add("occupancies[]" to it.key) }
        query.sort?.let { add("sort" to it.key) }
        add("page" to query.page.toString())
        add("per_page" to query.perPage.toString())
    }

    fun pageParameters(page: Int, perPage: Int): List<Pair<String, String>> = listOf("page" to page.toString(), "per_page" to perPage.toString())

    fun movementParameters(flow: MovementFlow?, page: Int, perPage: Int): List<Pair<String, String>> =
        listOfNotNull(flow?.let { "flow" to it.key }) + pageParameters(page, perPage)

    fun orgStocksParameters(query: SkoQuery): List<Pair<String, String>> = listOfNotNull(
        "bucket" to query.bucket.key,
        query.familyId?.let { "family_id" to it.toString() },
        query.search.trimmedOrNull()?.let { "q" to it },
        query.sort?.let { "sort" to it.key },
        "page" to query.page.toString(),
        "per_page" to query.perPage.toString(),
    )

    fun familiesParameters(query: FamilyQuery): List<Pair<String, String>> = listOfNotNull(
        "state" to query.state.key,
        query.search.trimmedOrNull()?.let { "q" to it },
        "page" to query.page.toString(),
        "per_page" to query.perPage.toString(),
    )

    fun emptyLocationsRemovalBody(enabled: Boolean): JsonObject = buildJsonObject { put("enabled", enabled) }

    fun locationWritePath(write: LocationWrite): String = when (write) {
        is LocationWrite.Audit -> "audit"
        is LocationWrite.MoveAll -> "move-all"
        is LocationWrite.MoveSome -> "move-some"
        is LocationWrite.RemoveOrgStocks -> "remove-org-stocks"
    }

    fun locationWriteBody(write: LocationWrite): JsonObject = when (write) {
        is LocationWrite.Audit -> countsBody(write.counts, unchangedReason = MovementReasonKey.RECOUNT)
        is LocationWrite.MoveAll -> buildJsonObject {
            put("location_id", write.targetLocationId)
            put("remove_after_move", write.removeAfterMove)
            put("reason", MovementReasonKey.TRANSFER)
        }
        is LocationWrite.MoveSome -> buildJsonObject {
            put("location_id", write.targetLocationId)
            putJsonArray("org_stocks") {
                write.items.forEach { item ->
                    addJsonObject {
                        put("org_stock_id", item.orgStockId)
                        put("quantity", GoodsOutResponses.quantityValue(item.quantity))
                        put("remove_after_move", item.removeAfterMove)
                    }
                }
            }
            put("reason", MovementReasonKey.TRANSFER)
        }
        is LocationWrite.RemoveOrgStocks -> buildJsonObject {
            putJsonArray("org_stock_ids") { write.orgStockIds.forEach { add(JsonPrimitive(it)) } }
        }
    }

    fun locationOrgStockUpdateBody(update: LocationOrgStockUpdate): JsonObject = buildJsonObject {
        update.type?.let { put("type", it.key) }
        update.levels.entries.sortedBy { it.key.ordinal }.forEach { (level, value) -> put(level.field, value?.let(GoodsOutResponses::quantityValue)) }
        update.notes?.let { put("notes", it.value.trimmedOrNull()) }
        update.makeDefaultFor.sortedBy { it.ordinal }.forEach { put(it.field, true) }
    }

    fun orgStockWritePath(warehouseId: Long, orgStockId: Long, write: OrgStockWrite): String = when (write) {
        is OrgStockWrite.Audit -> "warehouses/$warehouseId/org-stocks/$orgStockId/audit"
        is OrgStockWrite.Move -> "location-org-stocks/${write.fromLocationOrgStockId}/move"
        is OrgStockWrite.Consume -> "location-org-stocks/${write.locationOrgStockId}/consume"
        is OrgStockWrite.AddLocation -> "warehouses/$warehouseId/org-stocks/$orgStockId/locations"
        is OrgStockWrite.RemoveLocations -> "warehouses/$warehouseId/org-stocks/$orgStockId/remove-locations"
    }

    fun orgStockWriteBody(write: OrgStockWrite): JsonObject = when (write) {
        is OrgStockWrite.Audit -> countsBody(write.counts, unchangedReason = null)
        is OrgStockWrite.Move -> buildJsonObject {
            putJsonArray("targets") {
                write.targets.forEach { target ->
                    addJsonObject {
                        put("location_org_stock_id", target.locationOrgStockId)
                        put("quantity", GoodsOutResponses.quantityValue(target.quantity))
                    }
                }
            }
        }
        is OrgStockWrite.Consume -> buildJsonObject {
            put("quantity", GoodsOutResponses.quantityValue(write.quantity))
            put("note", write.note.trimmedOrNull())
        }
        is OrgStockWrite.AddLocation -> buildJsonObject {
            put("location_id", write.locationId)
            put("type", write.type.key)
        }
        is OrgStockWrite.RemoveLocations -> buildJsonObject {
            putJsonArray("location_org_stock_ids") { write.locationOrgStockIds.forEach { add(JsonPrimitive(it)) } }
        }
    }

    private fun countsBody(counts: List<AuditCount>, unchangedReason: String?): JsonObject = buildJsonObject {
        putJsonArray("counts") {
            counts.forEach { count ->
                addJsonObject {
                    put("location_org_stock_id", count.locationOrgStockId)
                    put("quantity", GoodsOutResponses.quantityValue(count.quantity))
                    put("reason", count.reason ?: unchangedReason)
                    put("note", count.note.trimmedOrNull())
                }
            }
        }
    }

    fun parseOverview(statusCode: Int, body: String): GoodsOutResult<LocationsOverview> = parseData(statusCode, body)

    fun parseEmptyLocationsRemoval(statusCode: Int, body: String): GoodsOutResult<EmptyLocationsRemoval> = parseData(statusCode, body)

    fun parseAreas(statusCode: Int, body: String): GoodsOutResult<List<WarehouseArea>> = parseData(statusCode, body)

    fun parseLocations(statusCode: Int, body: String): GoodsOutResult<LocationPage> = when (statusCode) {
        HTTP_OK -> decode<LocationListResponse>(body)
            ?.let { GoodsOutResult.Loaded(LocationPage(locations = it.data, meta = it.meta)) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseLocation(statusCode: Int, body: String): GoodsOutResult<LocationDetail> = when (statusCode) {
        HTTP_OK -> dataElement(body)?.let(::locationDetail)?.let { GoodsOutResult.Loaded(it) } ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseLocationWrite(statusCode: Int, body: String): GoodsOutResult<LocationWriteOutcome> = when (statusCode) {
        HTTP_OK -> dataElement(body)
            ?.let(::locationDetail)
            ?.let { GoodsOutResult.Loaded(LocationWriteOutcome(location = it, removed = decode<RemovedResponse>(body)?.removed)) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseMovements(statusCode: Int, body: String): GoodsOutResult<MovementPage> = when (statusCode) {
        HTTP_OK -> decode<MovementListResponse>(body)
            ?.let { GoodsOutResult.Loaded(MovementPage(movements = it.data, meta = it.meta)) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseReasons(statusCode: Int, body: String): GoodsOutResult<StockMovementReasons> = when (statusCode) {
        HTTP_OK -> dataElement(body)
            ?.let { data ->
                val reasons = data as? JsonObject
                StockMovementReasons(increase = reasons.reasonList("increase"), decrease = reasons.reasonList("decrease"))
            }
            ?.let { GoodsOutResult.Loaded(it) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseDashboard(statusCode: Int, body: String): GoodsOutResult<InventoryDashboard> = parseData(statusCode, body)

    fun parseOrgStocks(statusCode: Int, body: String): GoodsOutResult<OrgStockPage> = when (statusCode) {
        HTTP_OK -> decode<OrgStockListResponse>(body)
            ?.let { GoodsOutResult.Loaded(OrgStockPage(rows = it.data, meta = it.meta, counts = it.counts, currencyCode = it.currencyCode)) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseOrgStock(statusCode: Int, body: String): GoodsOutResult<OrgStockDetail> = parseData(statusCode, body)

    fun parseOrgStockWrite(statusCode: Int, body: String): GoodsOutResult<OrgStockWriteOutcome> = when (statusCode) {
        HTTP_OK -> decode<OrgStockWriteResponse>(body)
            ?.let {
                GoodsOutResult.Loaded(
                    OrgStockWriteOutcome(orgStock = it.data, status = it.result.status, message = it.result.message?.takeIf(String::isNotBlank)),
                )
            }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseFamilies(statusCode: Int, body: String): GoodsOutResult<FamilyPage> = when (statusCode) {
        HTTP_OK -> decode<FamilyListResponse>(body)
            ?.let { GoodsOutResult.Loaded(FamilyPage(families = it.data, meta = it.meta, counts = it.counts)) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    fun parseFamilySkos(statusCode: Int, body: String): GoodsOutResult<FamilySkoPage> = when (statusCode) {
        HTTP_OK -> decode<FamilySkoListResponse>(body)
            ?.let { GoodsOutResult.Loaded(FamilySkoPage(family = it.family, skos = it.data, meta = it.meta)) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    private fun locationDetail(data: JsonElement): LocationDetail? = runCatching {
        val contents = json.decodeFromJsonElement<LocationContents>(data)
        LocationDetail(
            summary = json.decodeFromJsonElement<LocationSummary>(data),
            pallets = contents.pallets,
            orgStocks = contents.orgStocks,
            can = contents.can,
        )
    }.getOrNull()

    private fun dataElement(body: String): JsonElement? = runCatching { json.parseToJsonElement(body).jsonObject["data"] }.getOrNull()

    private fun JsonObject?.reasonList(key: String): List<MovementReason> {
        val entries = this?.get(key) as? JsonObject ?: return emptyList()
        return entries.mapNotNull { (value, label) ->
            (label as? JsonPrimitive)?.takeUnless { it is JsonNull }?.content?.let { MovementReason(key = value, label = it) }
        }
    }

    private inline fun <reified T> parseData(statusCode: Int, body: String): GoodsOutResult<T> = when (statusCode) {
        HTTP_OK -> decode<InventoryData<T>>(body)
            ?.let { GoodsOutResult.Loaded(it.data) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()

    private fun String?.trimmedOrNull(): String? = this?.trim()?.takeIf { it.isNotEmpty() }
}
