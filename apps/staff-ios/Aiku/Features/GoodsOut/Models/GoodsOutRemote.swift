import Foundation

enum GoodsOutSource: Equatable, Sendable {
    case sample
    case remote
}

enum GoodsOutActionKey: String, Sendable {
    case startPicking = "start_picking"
    case resumePicking = "resume_picking"
    case takeOver = "take_over"
    case setAsPicked = "set_as_picked"
    case setAsPacked = "set_as_packed"
    case putBackIncompleteSets = "put_back_incomplete_sets"
    case autoFinishWaiting = "auto_finish_waiting"
    case backToPicking = "back_to_picking"
    case undoSetAsPicked = "undo_set_as_picked"
    case changeTrolley = "change_trolley"
    case changePickedBay = "change_picked_bay"
    case assignPicker = "assign_picker"
    case removePicker = "remove_picker"
    case changePicker = "change_picker"
    case startPacking = "start_packing"
    case undoPacking = "undo_packing"
    case unpack
    case addShipment = "add_shipment"
    case getExternalShipment = "get_external_shipment"
    case finaliseAndDispatch = "finalise_and_dispatch"
    case finalise
    case dispatch
    case cancel
    case changePacker = "change_packer"
    case editDeliveryAddress = "edit_delivery_address"
}

struct GoodsOutAvailableAction: Hashable, Sendable {
    let key: String
    let label: String
    var disabledReason: String?
}

struct GoodsOutPickingSettings: Hashable, Sendable {
    var allowScanToPick = false
    var allowWaiting = false
    var allowPickerSetNotPicked = false
    var allowPickerChooseLocation = false
    var allowScanToPack = false
}

struct GoodsOutRemoteDetails: Hashable, Sendable {
    var isDetailed = false
    var stateKey = ""
    var stateLabel = ""
    var actions: [GoodsOutAvailableAction] = []
    var settings = GoodsOutPickingSettings()
    var isEditable = false
    var canHandle = false
    var isTakenOver = false
    var allowActions = false
    var readOnlyNotice: String?
    var warning: String?
    var isDropshipping = false
    var isPriority = false
    var isCustomerVip = false
    var waitingCount = 0
    var canAddToPickingSession = false
    var trolleyIDs: [Int] = []
    var pickedBayIDs: [Int] = []

    var canWorkLines: Bool { stateKey == "handling" && allowActions }
}

struct GoodsOutLinePicking: Identifiable, Hashable, Sendable {
    let id: Int
    var locationCode: String?
    var locationOrgStockID: Int?
    var quantity: Double
    var quantityLabel: String?
    var isNotPicked = false
    var reasonLabel: String?
}

enum GoodsOutActionOutcome: Equatable, Sendable {
    case done(message: String?)
    case nothingChanged(message: String?)
    case failed(message: String)

    var isDone: Bool {
        if case .done = self { return true }
        return false
    }
}

struct GoodsOutPersonActivity: Identifiable, Hashable, Sendable {
    let id: Int
    let name: String
    let subtitle: String
    var sessionReference: String?
    var sessionID: Int?
    var orders = 0
}

struct GoodsOutService: Sendable {
    var deliveryNotes: @Sendable (Int, GoodsOutListRequest) async throws -> GoodsOutDeliveryNotesPageDTO
    var counts: @Sendable (Int, String?) async throws -> GoodsOutCountsDTO
    var deliveryNote: @Sendable (Int) async throws -> GoodsOutDeliveryNoteDTO
    var perform: @Sendable (Int, GoodsOutNoteAction) async throws -> GoodsOutActionResponseDTO
    var trolleys: @Sendable (Int) async throws -> [GoodsOutTrolleyDTO]
    var pickedBays: @Sendable (Int) async throws -> [GoodsOutPickedBayDTO]
    var pickers: @Sendable (Int) async throws -> [GoodsOutPickerDTO]
    var dispatchPersonnel: @Sendable (Int, GoodsOutDispatchRole) async throws -> [GoodsOutDispatchPersonDTO]
    var write: (@Sendable (Int, GoodsOutNoteAction) async throws -> GoodsOutActionResponseDTO)? = nil
    var shippers: (@Sendable (Int) async throws -> GoodsOutShippersDTO)? = nil
    var document: (@Sendable (Int, GoodsOutDocument) async throws -> Data)? = nil
    var orgStock: (@Sendable (Int, Int) async throws -> MobileOrgStockDetail)? = nil
    var setPickingDefaults: (@Sendable (Int, MobileLocationOrgStockPickingDefaults) async throws -> Void)? = nil

    static func live(_ client: APIClient) -> GoodsOutService {
        live { client }
    }

    static let shared = live { APIClient.shared }

    static func live(_ client: @escaping @Sendable () -> APIClient) -> GoodsOutService {
        GoodsOutService(
            deliveryNotes: { try await client().goodsOutDeliveryNotes(warehouseID: $0, request: $1) },
            counts: { try await client().goodsOutCounts(warehouseID: $0, picker: $1) },
            deliveryNote: { try await client().goodsOutDeliveryNote(id: $0) },
            perform: { try await client().goodsOutPerform(noteID: $0, $1) },
            trolleys: { try await client().goodsOutTrolleys(warehouseID: $0) },
            pickedBays: { try await client().goodsOutPickedBays(warehouseID: $0) },
            pickers: { try await client().goodsOutPickers(warehouseID: $0) },
            dispatchPersonnel: { try await client().goodsOutDispatchPersonnel(warehouseID: $0, role: $1) },
            write: { try await client().goodsOutWrite(noteID: $0, $1) },
            shippers: { try await client().goodsOutShippers(noteID: $0) },
            document: { try await client().goodsOutDocument(noteID: $0, $1) },
            orgStock: { try await client().fetchOrgStock(warehouseID: $0, id: $1) },
            setPickingDefaults: { try await client().setPickingDefaults(linkID: $0, defaults: $1) }
        )
    }
}

enum GoodsOutMapper {
    static func listRequest(for query: GoodsOutListQuery, page: Int, perPage: Int = 50, today: Date, timeZone: TimeZone = GoodsOutDateFormat.timeZone) -> GoodsOutListRequest {
        var request = GoodsOutListRequest(page: page, perPage: perPage)
        request.bucket = query.apiBucket
        if query.filter.channels.count == 1, let channel = query.filter.channels.first {
            request.channel = channel.apiKey
        }
        if query.filter.pickers.count == 1, let picker = query.filter.pickers.first {
            request.picker = pickerKey(picker)
        }
        if query.filter.range.isSet {
            request.from = query.filter.range.from.map { ListDates.apiDay($0) }
            request.to = query.filter.range.to.map { ListDates.apiDay($0) }
        } else if query.filter.dates.count == 1, let date = query.filter.dates.first, let range = dateRange(date, today: today, timeZone: timeZone) {
            request.from = range.from
            request.to = range.to
        }
        request.sort = query.filter.sort?.apiKey
        let search = query.search.trimmingCharacters(in: .whitespacesAndNewlines)
        request.search = search.isEmpty ? nil : search
        return request
    }

    static func pickerKey(_ filter: GoodsOutPickerFilter) -> String {
        switch filter {
        case .me: "me"
        case .noPicker: "none"
        case .otherPickers: "others"
        }
    }

    static func dateRange(_ filter: GoodsOutDateFilter, today: Date, timeZone: TimeZone) -> (from: String, to: String)? {
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = timeZone
        let formatter = DateFormatter()
        formatter.calendar = calendar
        formatter.timeZone = timeZone
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd"
        func day(_ offset: Int) -> String {
            formatter.string(from: calendar.date(byAdding: .day, value: offset, to: today) ?? today)
        }
        switch filter {
        case .today: return (day(0), day(0))
        case .yesterday: return (day(-1), day(-1))
        case .last7Days: return (day(-6), day(0))
        case .overdue: return nil
        }
    }

    static func date(_ text: String?) -> Date? {
        guard let text, !text.isEmpty else { return nil }
        let withFraction = ISO8601DateFormatter()
        withFraction.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return ISO8601DateFormatter().date(from: text) ?? withFraction.date(from: text)
    }

    static func age(of date: Date, now: Date, timeZone: TimeZone = GoodsOutDateFormat.timeZone) -> GoodsOutNoteAge {
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = timeZone
        let days = calendar.dateComponents([.day], from: calendar.startOfDay(for: date), to: calendar.startOfDay(for: now)).day ?? 0
        switch days {
        case ...0: return .today
        case 1: return .yesterday
        case 2...6: return .thisWeek
        default: return .older
        }
    }

    static func joinedNames(_ refs: [GoodsOutIDName]?) -> String? {
        let names = (refs ?? []).compactMap(\.name).filter { !$0.isEmpty }
        return names.isEmpty ? nil : names.joined(separator: ", ")
    }

    static func pickerName(_ picker: GoodsOutIDName?, currentUserID: Int?, currentUser: String) -> String? {
        guard let picker else { return nil }
        if picker.id == currentUserID { return currentUser }
        return picker.name ?? "Picker \(picker.id)"
    }

    static func note(from row: GoodsOutDeliveryNoteRowDTO, currentUserID: Int?, currentUser: String, now: Date) -> GoodsOutDeliveryNote {
        let created = date(row.date) ?? now
        let channel = GoodsOutChannel(apiKey: row.channel) ?? GoodsOutChannel(apiKey: row.shop?.type) ?? .wholesale
        let items = row.numberItems ?? 0
        let weight = row.estimatedWeightKg ?? 0
        var remote = GoodsOutRemoteDetails()
        remote.stateKey = row.state
        remote.stateLabel = row.stateLabel ?? ""
        remote.isDropshipping = row.shop?.type == "dropshipping"
        remote.isPriority = row.isPriority ?? false
        remote.isCustomerVip = row.isCustomerVip ?? false
        remote.waitingCount = (row.waitingWarehouseCount ?? 0) + (row.waitingCrmCount ?? 0)
        remote.canAddToPickingSession = row.canAddToPickingSession ?? false
        remote.trolleyIDs = (row.trolleys ?? []).map(\.id)
        remote.pickedBayIDs = (row.pickedBays ?? []).map(\.id)
        var note = GoodsOutDeliveryNote(
            reference: row.reference,
            channel: channel,
            platform: nil,
            customer: row.customerName ?? "",
            customerNumber: "",
            contactName: "",
            email: "",
            phone: "",
            deliveryAddress: GoodsOutAddress(company: "", street: "", town: "", postcode: "", country: ""),
            shipper: (row.isCollection ?? false) ? .collection : nil,
            state: GoodsOutDeliveryNoteState(apiState: row.state) ?? .todo,
            picker: pickerName(row.picker, currentUserID: currentUserID, currentUser: currentUser),
            trolley: joinedNames(row.trolleys),
            pickedBay: joinedNames(row.pickedBays),
            lines: [],
            createdAt: created,
            age: age(of: created, now: now),
            estimate: "",
            weightKg: weight,
            skoCount: 0
        )
        note.listDate = ListDates.recordDate(row.date)
        note.listItemCount = items
        note.apiID = row.id
        note.pickerID = row.picker?.id
        note.packerSameAsPicker = row.packer == nil || row.packer?.id == row.picker?.id
        note.remote = remote
        return note
    }

    static func note(from dto: GoodsOutDeliveryNoteDTO, currentUserID: Int?, currentUser: String, now: Date) -> GoodsOutDeliveryNote {
        let timeline = dto.timeline ?? []
        let created = timeline.first { $0.state == "unassigned" }.flatMap { date($0.at) } ?? now
        let channel = GoodsOutChannel(apiKey: dto.channel) ?? GoodsOutChannel(apiKey: dto.shop?.type) ?? .wholesale
        let summary = dto.summary
        let fields = dto.deliveryAddress?.fields
        let settings = dto.effectiveSettings
        var remote = GoodsOutRemoteDetails()
        remote.isDetailed = true
        remote.stateKey = dto.state
        remote.stateLabel = dto.stateLabel ?? ""
        remote.actions = (dto.availableActions ?? []).map { GoodsOutAvailableAction(key: $0.key, label: $0.label ?? $0.key, disabledReason: $0.disabledReason.flatMap { $0.isEmpty ? nil : $0 }) }
        remote.settings = GoodsOutPickingSettings(
            allowScanToPick: settings?.allowScanToPick ?? false,
            allowWaiting: settings?.allowWaiting ?? false,
            allowPickerSetNotPicked: settings?.allowPickerSetNotPicked ?? false,
            allowPickerChooseLocation: settings?.allowPickerChooseLocation ?? false,
            allowScanToPack: settings?.allowScanToPack ?? false
        )
        remote.isEditable = dto.isEditable ?? false
        remote.canHandle = dto.canHandle ?? false
        remote.isTakenOver = dto.isTakenOver ?? false
        remote.allowActions = dto.allowActions ?? false
        remote.readOnlyNotice = dto.readOnlyNotice.flatMap { $0.isEmpty ? nil : $0 }
        remote.warning = dto.warning.flatMap { $0.isEmpty ? nil : $0 }
        remote.isDropshipping = dto.shop?.type == "dropshipping"
        remote.isCustomerVip = dto.customer?.isVip ?? false
        remote.waitingCount = (dto.waitingWarehouseCount ?? 0) + (dto.waitingCrmCount ?? 0)
        remote.trolleyIDs = (dto.trolleys ?? []).map(\.id)
        remote.pickedBayIDs = (dto.pickedBays ?? []).map(\.id)

        var stateDates: [GoodsOutDeliveryNoteState: Date] = [:]
        for entry in timeline where entry.state != "unassigned" {
            if let state = GoodsOutDeliveryNoteState(apiState: entry.state), let at = date(entry.at) {
                stateDates[state] = at
            }
        }

        var note = GoodsOutDeliveryNote(
            reference: dto.reference,
            channel: channel,
            platform: dto.platform?.name.flatMap { $0.isEmpty ? nil : $0 },
            customer: dto.customer?.name ?? "",
            customerNumber: dto.customer?.reference ?? "",
            contactName: fields?.contactName ?? dto.customer?.contactName ?? "",
            email: fields?.email ?? dto.customer?.email ?? "",
            phone: fields?.phone ?? dto.customer?.phone ?? "",
            deliveryAddress: GoodsOutAddress(
                company: fields?.companyName ?? "",
                street: [fields?.addressLine1, fields?.addressLine2].compactMap { $0 }.filter { !$0.isEmpty }.joined(separator: ", "),
                town: fields?.locality ?? "",
                postcode: fields?.postalCode ?? "",
                country: fields?.countryCode ?? dto.deliveryAddress?.countryCode ?? ""
            ),
            shipper: (dto.isCollection ?? false) ? .collection : nil,
            state: GoodsOutDeliveryNoteState(apiState: dto.state) ?? .todo,
            picker: pickerName(dto.picker, currentUserID: currentUserID, currentUser: currentUser),
            trolley: joinedNames(dto.trolleys),
            pickedBay: joinedNames(dto.pickedBays),
            lines: (dto.lines ?? []).map(line(from:)),
            shippingLabelNote: nonEmpty(dto.notes?.shipping?.text),
            customerInstructions: nonEmpty(dto.notes?.customer?.text),
            warehouseNote: nonEmpty(dto.notes?.warehouse?.text),
            createdAt: created,
            stateDates: stateDates,
            age: age(of: created, now: now),
            estimate: estimate(summary),
            weightKg: summary?.estimatedWeightKg ?? 0,
            skoCount: Int((summary?.numberSkos ?? 0).rounded())
        )
        note.apiID = dto.id
        note.pickerID = dto.picker?.id
        note.packerSameAsPicker = dto.packer == nil || dto.packer?.id == dto.picker?.id
        let addressLines = (dto.deliveryAddress?.lines ?? []).filter { !$0.isEmpty }
        note.addressText = addressLines.isEmpty ? nil : addressLines.joined(separator: ", ")
        note.incompleteSet = incompleteSet(dto.incompleteSets ?? [])
        note.remote = remote
        applyPacking(dto, to: &note, currentUserID: currentUserID, currentUser: currentUser)
        return note
    }

    static func nonEmpty(_ text: String?) -> String? {
        guard let text = text?.trimmingCharacters(in: .whitespacesAndNewlines), !text.isEmpty else { return nil }
        return text
    }

    static func estimate(_ summary: GoodsOutSummaryDTO?) -> String {
        var parts: [String] = []
        if let picking = summary?.estimatedPickingMinutes, picking > 0 {
            parts.append("~\(max(1, Int(picking.rounded()))) min picking")
        }
        if let packing = summary?.estimatedPackingMinutes, packing > 0 {
            parts.append("~\(max(1, Int(packing.rounded()))) min packing")
        }
        return parts.isEmpty ? "No estimate" : parts.joined(separator: ", ")
    }

    static func quantity(_ value: Double?) -> Int {
        Int((value ?? 0).rounded())
    }

    static func notPickedReason(_ key: String?) -> GoodsOutNotPickedReason {
        switch key {
        case GoodsOutNotPickedReasonKey.cancelledByCustomer.rawValue: .cancelledByCustomer
        case GoodsOutNotPickedReasonKey.cancelledByWarehouse.rawValue: .cancelledByWarehouse
        case GoodsOutNotPickedReasonKey.notApplicable.rawValue: .notApplicable
        default: .outOfStock
        }
    }

    static func reasonKey(_ reason: GoodsOutNotPickedReason) -> GoodsOutNotPickedReasonKey {
        switch reason {
        case .outOfStock: .outOfStock
        case .cancelledByCustomer: .cancelledByCustomer
        case .cancelledByWarehouse: .cancelledByWarehouse
        case .notApplicable: .notApplicable
        }
    }

    static func line(from dto: GoodsOutLineDTO) -> GoodsOutLine {
        let locations = (dto.locations ?? []).map { location in
            GoodsOutStockLocation(
                code: location.code ?? "",
                kind: location.type.map { $0.capitalized } ?? "",
                stock: Int((location.stock ?? 0).rounded(.down)),
                locationOrgStockID: location.locationOrgStockId,
                stockLabel: FractionGlyphs.apply(location.stockLabel)
            )
        }
        let pickings = (dto.pickings ?? []).map { picking in
            GoodsOutLinePicking(
                id: picking.id,
                locationCode: picking.locationCode,
                locationOrgStockID: picking.locationOrgStockId,
                quantity: picking.quantity ?? 0,
                quantityLabel: picking.quantityLabel,
                isNotPicked: picking.isNotPicked,
                reasonLabel: picking.notPickedReasonLabel
            )
        }
        let primary = locations.first?.code ?? dto.warehouseArea?.text ?? ""
        let pickedFrom = pickings.first { !$0.isNotPicked && $0.locationCode != nil && $0.locationCode != primary }?.locationCode
        var line = GoodsOutLine(
            sko: dto.orgStock?.code ?? "Line \(dto.id)",
            name: dto.orgStock?.name ?? "",
            location: primary,
            required: quantity(dto.quantityRequired),
            picked: quantity(dto.quantityPicked),
            pickedFrom: pickedFrom,
            locationStock: locations.first?.stock
        )
        if (dto.quantityWaitingWarehouse ?? 0) > 0 {
            line.outcome = .waiting(.warehouse, note: "")
        } else if (dto.quantityWaitingCrm ?? 0) > 0 {
            line.outcome = .waiting(.customerService, note: "")
        } else if (dto.quantityNotPicked ?? 0) > 0 {
            let reason = (dto.pickings ?? []).last { $0.isNotPicked }?.notPickedReason
            line.outcome = .notPicked(notPickedReason(reason))
        }
        line.apiID = dto.id
        line.stockLocations = locations
        line.pickings = pickings
        line.quantityToPick = dto.quantityToPick
        line.quantityWaitingWarehouse = dto.quantityWaitingWarehouse ?? 0
        line.noteToPickers = dto.noteToPickers?.text
        line.orgStockID = dto.orgStock?.id
        line.barcode = dto.orgStock?.barcode.flatMap(nonEmpty)
        line.packing = linePacking(dto)
        line.packedInto = packedInto(dto)
        return line
    }

    static func incompleteSet(_ parts: [GoodsOutIncompleteSetPartDTO]) -> GoodsOutIncompleteSet? {
        guard !parts.isEmpty else { return nil }
        return GoodsOutIncompleteSet(
            name: "Incomplete set",
            parts: parts.map { part in
                GoodsOutSetPart(
                    sko: part.code ?? "",
                    name: part.name ?? "",
                    location: (part.locations ?? []).joined(separator: ", "),
                    picked: quantity(part.quantity),
                    required: max(1, quantity(part.quantity))
                )
            }
        )
    }

    static func backlogRows(_ counts: GoodsOutCountsDTO) -> [GoodsOutBacklogRow] {
        counts.channels.compactMap { channel in
            guard let mapped = GoodsOutChannel(apiKey: channel.channel), (channel.buckets["in_warehouse"] ?? 0) > 0 else { return nil }
            return GoodsOutBacklogRow(channel: mapped, counts: stateCounts(channel.buckets, combiningQueued: true))
        }
    }

    static func stateCounts(_ buckets: [String: Int], combiningQueued: Bool) -> [GoodsOutDeliveryNoteState: Int] {
        var counts: [GoodsOutDeliveryNoteState: Int] = [:]
        for state in GoodsOutDeliveryNoteState.listTabs {
            guard let bucket = state.apiBucket else { continue }
            counts[state] = buckets[bucket] ?? 0
        }
        if combiningQueued {
            counts[.todo, default: 0] += buckets["queued"] ?? 0
            counts[.dispatched] = nil
        }
        return counts
    }

    static func trolleys(_ dtos: [GoodsOutTrolleyDTO]) -> [GoodsOutTrolley] {
        dtos.map { dto in
            let heldBy = dto.currentPickingSession.map { "Picking session \($0.reference ?? "\($0.id)")" }
            return GoodsOutTrolley(code: dto.name, heldBy: heldBy, apiID: dto.id, heldByNoteReference: dto.currentDeliveryNote?.reference)
        }
    }

    static func pickedBays(_ dtos: [GoodsOutPickedBayDTO]) -> [GoodsOutPickedBay] {
        dtos.map { dto in
            GoodsOutPickedBay(
                name: dto.code,
                otherNotes: dto.numberDeliveryNotes ?? dto.deliveryNotes?.count ?? 0,
                apiID: dto.id,
                noteReferences: (dto.deliveryNotes ?? []).compactMap(\.reference)
            )
        }
    }

    static func pickers(_ dtos: [GoodsOutPickerDTO], currentUserID: Int?, currentUser: String) -> [GoodsOutPickerProfile] {
        dtos.map { dto in
            let name = dto.userId == currentUserID ? currentUser : (nonEmpty(dto.name) ?? nonEmpty(dto.alias) ?? "Picker \(dto.userId)")
            return GoodsOutPickerProfile(name: name, userID: dto.userId)
        }
    }

    static func activities(_ people: [GoodsOutDispatchPersonDTO], role: GoodsOutDispatchRole) -> [GoodsOutPersonActivity] {
        people.map { person in
            let orders = person.deliveryNotes?.count ?? 0
            let trolleys = person.trolleys?.count ?? 0
            let session = person.pickingSessions?.first?.reference
            let sessionID = person.pickingSessions?.first?.id
            var parts: [String] = []
            if let session {
                parts.append("Picking session \(session)")
            }
            let verb = role == .pickers ? "\(orders) \(orders == 1 ? "order" : "orders")" : "\(orders) \(orders == 1 ? "order" : "orders") to pack"
            parts.append(verb)
            if role == .pickers {
                parts.append("\(trolleys) \(trolleys == 1 ? "trolley" : "trolleys")")
            }
            return GoodsOutPersonActivity(
                id: person.user.id,
                name: person.user.name ?? "Staff \(person.user.id)",
                subtitle: parts.joined(separator: " · "),
                sessionReference: session,
                sessionID: sessionID,
                orders: orders
            )
        }
    }

    static func message(for error: Error) -> String {
        if let apiError = error as? APIError {
            switch apiError {
            case .offline: return "You need an internet connection to do this. Check the connection and try again."
            case .unauthorized: return "Your session has ended. Sign in again."
            default: return apiError.localizedDescription
            }
        }
        if let formError = error as? FormValidationError {
            return formError.message
        }
        if error is DecodingError {
            return "Aiku sent something the app could not read."
        }
        return error.localizedDescription
    }
}

extension GoodsOutLine {
    var defaultStockLocation: GoodsOutStockLocation? {
        stockLocations.first { !$0.isEmpty } ?? stockLocations.first
    }

    var remainingQuantity: Double { quantityToPick ?? Double(remaining) }


    func waitingAction(kind: GoodsOutWaitingKind, note: String?) -> GoodsOutNoteAction? {
        guard let apiID else { return nil }
        let quantity = kind == .warehouse ? remainingQuantity + quantityWaitingWarehouse : remainingQuantity
        return .waiting(line: apiID, kind: kind, quantity: quantity, note: note)
    }
}

extension GoodsOutDeliveryNote {
    var canWorkLines: Bool {
        guard let remote else { return state == .picking }
        return remote.canWorkLines
    }

    var canScanToPick: Bool {
        guard let remote else { return state == .picking }
        return remote.canWorkLines && remote.settings.allowScanToPick && !remote.isDropshipping
    }

    var chatRoute: (kind: String, id: Int)? {
        apiID.map { ("delivery_note", $0) }
    }
}

extension GoodsOutMapper {
    static let insertStates: Set<String> = ["handling", "picked", "packing"]

    static func applyPacking(_ dto: GoodsOutDeliveryNoteDTO, to note: inout GoodsOutDeliveryNote, currentUserID: Int?, currentUser: String) {
        note.parcels = parcels(dto.parcels ?? [])
        note.shipments = shipments(dto.shipments ?? [], parcelCount: max(1, note.parcels.count))
        note.leaflets = leaflets(dto.inserts)
        note.packingMaterials = (dto.consumables ?? []).compactMap { consumable in
            guard let code = nonEmpty(consumable.code) else { return nil }
            return "\(GoodsOutLinePacking.text(consumable.quantity ?? 1)) × \(code)"
        }
        let shipping = dto.shipping
        var details = GoodsOutNotePackingDetails()
        details.packerID = dto.packer?.id
        details.packerName = pickerName(dto.packer, currentUserID: currentUserID, currentUser: currentUser)
        details.isEditable = dto.isEditable ?? false
        details.parcelsEditable = dto.parcelsEditable ?? false
        details.hasBoxPackingList = dto.boxPackingList?.required ?? (dto.boxPackingList != nil)
        details.numberBoxes = dto.boxPackingList?.numberBoxes
        details.missingBoxesMessage = nonEmpty(dto.boxPackingList?.missingMessage)
        details.packagingName = nonEmpty(dto.packaging?.current?.name)
        details.insertsLabel = nonEmpty(dto.inserts?.label)
        details.canPrintAllInserts = dto.inserts?.canPrintAll ?? false
        details.showsInserts = dto.inserts != nil && insertStates.contains(dto.state)
        details.canPrintLabels = shipping?.canPrintLabels ?? false
        details.directive = GoodsOutShipperDirective(
            lockedShipperID: shipping?.lockedShipperId,
            preferredShipperID: shipping?.preferredShipperId,
            lockedBy: shipping?.lockedBy,
            canOverrideLock: shipping?.canOverrideLock ?? false,
            trackingChannel: shipping?.trackingChannel
        )
        details.isShippingByExternal = dto.isShippingByExternal ?? false
        details.externalEngine = nonEmpty(shipping?.external?.engineLabel) ?? dto.platform?.name.flatMap(nonEmpty)
        details.hasGiftMessage = dto.hasGiftMessage ?? false
        details.address = GoodsOutAddressForm(dto.deliveryAddress?.fields, fallbackCountry: dto.deliveryAddress?.countryCode)
        details.canEditContact = dto.canEditContact ?? false
        var editable: Set<String> = []
        if dto.notes?.warehouse?.editable == true { editable.insert(GoodsOutNoteField.warehouse.rawValue) }
        if dto.notes?.shipping?.editable == true { editable.insert(GoodsOutNoteField.shipping.rawValue) }
        details.editableNotes = editable
        details.returnReference = nonEmpty(dto.returnDeliveryNote?.reference)
        details.returnStateLabel = nonEmpty(dto.returnDeliveryNote?.stateLabel)
        note.packingDetails = details
    }

    static func parcels(_ dtos: [GoodsOutParcelDTO]) -> [GoodsOutParcel] {
        dtos.enumerated().map { index, dto in
            let sizes = (dto.dimensionsCm ?? []).map { $0.map(centimetres) ?? nil }
            func size(_ position: Int) -> Int? { sizes.indices.contains(position) ? sizes[position] : nil }
            return GoodsOutParcel(number: index + 1, weightKg: dto.weightKg ?? 0, length: size(0), width: size(1), height: size(2), boxes: dto.boxes)
        }
    }

    static func centimetres(_ value: Double) -> Int? {
        guard value > 0 else { return nil }
        return max(1, Int(value.rounded()))
    }

    static func shipments(_ dtos: [GoodsOutShipmentDTO], parcelCount: Int) -> [GoodsOutShipment] {
        dtos.map { dto in
            let label: GoodsOutShipmentLabel
            switch dto.label?.kind {
            case "download" where nonEmpty(dto.label?.url) != nil: label = .download
            case "external": label = dto.label?.url.flatMap(nonEmpty).flatMap(URL.init(string:)).map(GoodsOutShipmentLabel.external) ?? .none
            default: label = .none
            }
            let numbers = dto.trackingNumbers
            var shipment = GoodsOutShipment(
                id: dto.id,
                shipper: nonEmpty(dto.shipper) ?? "Shipment",
                tracking: numbers.first ?? "",
                parcelCount: parcelCount,
                cost: nil,
                source: .api,
                labelPrinted: false
            )
            shipment.trackingNumbers = numbers
            shipment.trackingURLs = dto.trackingUrls ?? []
            shipment.isPrintable = dto.isPrintable ?? false
            shipment.label = label
            shipment.canDelete = dto.canDelete ?? false
            shipment.isRemote = true
            return shipment
        }
    }

    static func leaflets(_ inserts: GoodsOutInsertsDTO?) -> [GoodsOutLeaflet] {
        (inserts?.leaflets ?? []).map { dto in
            GoodsOutLeaflet(
                id: "insert-\(dto.id)",
                isPrinted: dto.state == "printed" || dto.state == "included",
                apiID: dto.id,
                name: nonEmpty(dto.name),
                copies: max(1, dto.copies ?? 1),
                stateLabel: nonEmpty(dto.stateLabel),
                canOpenPdf: dto.canOpenPdf ?? false
            )
        }
    }

    static func linePacking(_ dto: GoodsOutLineDTO) -> GoodsOutLinePacking {
        GoodsOutLinePacking(
            quantityPicked: dto.quantityPicked ?? 0,
            quantityPacked: dto.quantityPacked ?? 0,
            quantityToPack: dto.quantityToPack ?? 0,
            pickedLabel: FractionGlyphs.apply(nonEmpty(dto.quantityPickedLabel)),
            packedLabel: FractionGlyphs.apply(nonEmpty(dto.quantityPackedLabel)),
            toPackLabel: FractionGlyphs.apply(nonEmpty(dto.quantityToPackLabel)),
            isDonePacking: (dto.isDonePacking ?? false) || (dto.isPacked ?? false),
            boxes: (dto.boxes ?? []).map { GoodsOutLineBox(box: $0.box, quantity: $0.quantity ?? 0) },
            noteToPackers: dto.noteToPackers?.text
        )
    }

    static func packedInto(_ dto: GoodsOutLineDTO) -> [Int: Int] {
        var boxes: [Int: Int] = [:]
        for box in dto.boxes ?? [] where (box.quantity ?? 0) > 0 {
            boxes[box.box, default: 0] += quantity(box.quantity)
        }
        return boxes
    }

    static func shipperList(_ dto: GoodsOutShippersDTO) -> GoodsOutShipperList {
        GoodsOutShipperList(
            shippers: dto.data.map { shipper in
                GoodsOutShipperChoice(
                    id: shipper.id,
                    name: nonEmpty(shipper.name) ?? nonEmpty(shipper.code) ?? "Shipper \(shipper.id)",
                    isAPI: shipper.type == "api",
                    needsTracking: shipper.needsTracking ?? false,
                    isLockedOut: shipper.isLockedOut ?? false,
                    code: shipper.code ?? ""
                )
            },
            directive: GoodsOutShipperDirective(
                lockedShipperID: dto.directive?.lockedShipperId,
                preferredShipperID: dto.directive?.preferredShipperId,
                lockedBy: dto.directive?.lockedBy,
                canOverrideLock: dto.directive?.canOverrideLock ?? false,
                trackingChannel: dto.directive?.trackingChannel
            )
        )
    }
}
