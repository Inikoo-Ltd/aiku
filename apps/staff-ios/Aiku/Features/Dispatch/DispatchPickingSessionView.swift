import SwiftUI

struct DispatchPickingSessionView: View {
    let sessionID: Int
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @Environment(AuthStore.self) private var authStore
    @State private var store: PickingSessionStore
    @State private var sheet: DispatchSessionSheet?
    @State private var sheetProblem: String?
    @State private var isConfirmingBack = false
    @State private var removing: Set<Int>?

    init(sessionID: Int) {
        self.sessionID = sessionID
        _store = State(initialValue: PickingSessionsStore.shared.store(for: sessionID))
    }

    var body: some View {
        Group {
            if let item = store.session {
                screen(item)
            } else {
                AppScreen(title: "Picking session", kicker: "Loading") {
                    if let problem = store.loadError {
                        DispatchLoadProblem(title: "Couldn’t load this picking session", message: problem) { await store.load() }
                    } else {
                        DispatchSkeletonList(rows: 2)
                        DispatchSkeletonList(rows: 4)
                    }
                }
            }
        }
        .task { await store.load() }
    }

    private func screen(_ item: MobilePickingSession) -> some View {
        AppScreen(
            title: item.reference,
            kicker: L10n.format("Picking session · %@", item.stateTitle),
            actions: [HeaderAction(.chat, label: "Ask CRM") {
                router.open(ShellRoute.staffChat(kind: DispatchChat.pickingSessionKind, contextID: item.id, title: item.reference))
            }]
        ) {
            if let problem = store.loadError {
                DispatchLoadProblem(title: "Couldn’t refresh this picking session", message: problem) { await store.load() }
            }
            summary(item)
            trolleys(item)
            if let next = item.nextOpenPick(after: nil) {
                DispatchScanCard(
                    title: "Pick by location",
                    detail: "Take it from this location",
                    highlight: next.title,
                    footnote: next.detail,
                    identifier: "dispatch.session.next"
                ) {
                    router.open(DispatchRoute.pickingSessionPickID(id: item.id))
                }
            }
            if item.sessionState == .handlingBlocked {
                InlineAlert(tone: .warn, icon: .pause, title: "Waiting", message: "Picked goods wait in a picked bay until the missing products arrive.")
            }
            notes(item)
            DispatchNote("Trolleys belong to the whole session. The session moves on by itself: picking finishes when every line is handled, packing when every delivery note is packed, and the trolleys are then freed.")
        } footer: {
            footer(item)
        }
        .screenRefreshable { await store.load() }
        .aikuSheet(item: $sheet) { shown in
            sheetContent(shown, session: item)
        }
        .aikuConfirm(
            L10n.format("Send %@ back to picking?", item.reference),
            isPresented: $isConfirmingBack,
            message: L10n.format("%lld delivery notes waiting. Each waiting delivery note goes back to picking, so its waiting lines can be picked again. Goods already picked stay picked.", item.waitingDeliveryNoteCount),
            confirmTitle: item.action(PickingSessionActionKey.backToPicking)?.label ?? "Back to picking"
        ) {
            Task { await run(.backToPicking) }
        }
    }

    private func summary(_ item: MobilePickingSession) -> some View {
        Card(padding: 14, spacing: 12) {
            HStack(spacing: 12) {
                IconTile(.person, tone: .info)
                VStack(alignment: .leading, spacing: 2) {
                    Text("Picker")
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.text3)
                    Text(PickingSessionFormat.personName(item.picker, currentUserID: authStore.userID) ?? L10n.text("No picker yet"))
                        .font(.aiku(15, .semibold))
                        .foregroundStyle(AikuColor.text)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                StatusChip(item.stateTitle, tone: item.tone)
            }
            HStack(spacing: 8) {
                stat("\(item.numberDeliveryNotes)", "delivery notes")
                stat("\(item.numberItems)", "items")
                stat("\(item.numberTrolleys)", "trolleys")
                if item.isPickingOver {
                    stat("\(PickingSessionFormat.percent(item.packingPercentage))%", "packed")
                } else {
                    stat("\(PickingSessionFormat.percent(item.pickingPercentage))%", "picked")
                }
            }
        }
    }

    private func stat(_ value: String, _ label: String) -> some View {
        VStack(spacing: 2) {
            Text(value)
                .font(.aiku(18, .bold))
                .foregroundStyle(AikuColor.text)
            Text(L10n.text(label))
                .font(.aiku(12))
                .foregroundStyle(AikuColor.text3)
                .lineLimit(1)
                .minimumScaleFactor(0.8)
        }
        .frame(maxWidth: .infinity)
        .accessibilityElement(children: .combine)
    }

    @ViewBuilder
    private func trolleys(_ item: MobilePickingSession) -> some View {
        if !item.trolleys.isEmpty || item.canChangeTrolleys {
            SectionHeading("Trolleys")
            Card {
                ForEach(Array(item.trolleys.enumerated()), id: \.element.id) { index, trolley in
                    if index > 0 { RowDivider() }
                    ListRow(trolley.name ?? "#\(trolley.id)", subtitle: "Trolley for the whole session", icon: .trolley, iconTone: .info) {
                        if item.canChangeTrolleys {
                            LinkButton("Remove") { open(.detachTrolley(trolley)) }
                        }
                    }
                }
                if item.canChangeTrolleys {
                    if !item.trolleys.isEmpty { RowDivider() }
                    ListRow(
                        item.trolleys.isEmpty ? "No trolley" : "Another trolley",
                        subtitle: "Only free trolleys can be added",
                        icon: .trolley,
                        iconTone: .neutral
                    ) {
                        LinkButton("Add") { open(.attachTrolley) }
                            .accessibilityIdentifier("dispatch.session.addTrolley")
                    }
                }
            }
        }
    }

    @ViewBuilder
    private func notes(_ item: MobilePickingSession) -> some View {
        SectionHeading("Delivery notes", detail: L10n.format("%lld delivery notes", item.deliveryNotes.count))
        if item.deliveryNotes.isEmpty {
            HelperText("This session has no delivery notes.")
        } else {
            CardList(item.deliveryNotes) { note in
                let isChosen = removing?.contains(note.id)
                ListRow(
                    note.reference,
                    subtitle: note.subtitle(isPacking: item.isPickingOver),
                    chips: [Chip(note.stateTitle, note.stateTone)],
                    action: {
                        if let removing {
                            self.removing = removing.symmetricDifference([note.id])
                        } else {
                            router.open(GoodsOutRoute.deliveryNoteID(id: note.id))
                        }
                    }
                ) {
                    if let isChosen {
                        IconTile(isChosen ? .checkCircle : .deliveryNote, tone: isChosen ? .danger : .neutral)
                    } else {
                        IconTile(.deliveryNote, tone: .info)
                    }
                } trailing: {
                    EmptyView()
                }
                .accessibilityAddTraits(isChosen == true ? .isSelected : [])
            }
        }
    }

    @ViewBuilder
    private func footer(_ item: MobilePickingSession) -> some View {
        if let chosen = removing {
            if chosen.isEmpty {
                HelperText("Select delivery notes to remove. Removed delivery notes leave the session and go back to To do.")
            } else {
                AikuButton(
                    L10n.format("Remove %lld delivery notes", chosen.count),
                    look: PickingSessionActionKey.look(for: PickingSessionActionKey.removeDeliveryNotes),
                    isLoading: store.working == .removeDeliveryNotes(Array(chosen))
                ) {
                    open(.removeDeliveryNotes(item.deliveryNotes.map(\.id).filter(chosen.contains)))
                }
            }
            AikuButton("Cancel", kind: .tertiary) { removing = nil }
        } else {
            let actions = item.footerActions
            if actions.isEmpty {
                HelperText(item.canWork ? L10n.format("%@. There is nothing to do on this session in the app right now.", item.stateTitle) : "You can see this session, but you can’t work in this warehouse.")
            } else {
                let primaryKey = actions.first { !$0.isDisabled }?.key
                ForEach(actions, id: \.key) { action in
                    if let reason = action.disabledReason, !reason.isEmpty {
                        HelperText("\(action.label ?? action.key): \(reason)")
                    } else {
                        AikuButton(
                            action.label ?? action.key,
                            look: PickingSessionActionKey.look(for: action.key).inFooter(isFirst: action.key == primaryKey),
                            isLoading: action.key == PickingSessionActionKey.startPicking && store.working == .startPicking
                        ) {
                            perform(action.key, session: item)
                        }
                        .accessibilityIdentifier("dispatch.session.\(action.key)")
                    }
                }
            }
        }
    }

    private func perform(_ key: String, session item: MobilePickingSession) {
        switch key {
        case PickingSessionActionKey.startPicking:
            Task { await run(.startPicking) }
        case PickingSessionActionKey.backToPicking:
            isConfirmingBack = true
        case PickingSessionActionKey.addDeliveryNotes:
            open(.addDeliveryNotes)
        case PickingSessionActionKey.removeDeliveryNotes:
            removing = []
        case PickingSessionActionKey.scanPick:
            router.open(DispatchRoute.pickingSessionPickID(id: item.id))
        case PickingSessionActionKey.scanPack:
            router.open(DispatchRoute.pickingSessionPackID(id: item.id))
        default:
            break
        }
    }

    private func open(_ shown: DispatchSessionSheet) {
        sheetProblem = nil
        sheet = shown
    }

    private func run(_ write: PickingSessionWrite) async {
        switch await store.act(write) {
        case .success(let message):
            sheet = nil
            removing = nil
            if let message { toasts.show(message) }
        case .failure(let failure):
            if sheet != nil {
                sheetProblem = failure.message
            } else {
                toasts.show(failure.message, tone: .danger)
            }
        }
    }

    @ViewBuilder
    private func sheetContent(_ shown: DispatchSessionSheet, session item: MobilePickingSession) -> some View {
        switch shown {
        case .attachTrolley:
            DispatchTrolleyChoiceSheet(store: store, problem: sheetProblem, onChoose: { trolleyID in
                Task { await run(.attachTrolley(trolleyID)) }
            }, onClose: { sheet = nil })
        case .detachTrolley(let trolley):
            SheetScaffold(title: removeTrolleyTitle(trolley), subtitle: L10n.format("Picking session %@", item.reference)) {
                HelperText("The trolley becomes free for another delivery note or session.")
                if let sheetProblem { FieldError(sheetProblem) }
            } footer: {
                AikuButton("Remove the trolley", look: AikuButtonLook(.caution), isLoading: store.isWorking) {
                    Task { await run(.detachTrolley(trolley.id)) }
                }
                AikuButton("Cancel", kind: .tertiary) { sheet = nil }
            }
        case .removeDeliveryNotes(let ids):
            SheetScaffold(title: L10n.format("Remove %lld delivery notes", ids.count), subtitle: L10n.format("Picking session %@", item.reference)) {
                HelperText("Removed delivery notes leave the session and go back to To do.")
                if let sheetProblem { FieldError(sheetProblem) }
            } footer: {
                AikuButton(
                    L10n.format("Remove %lld delivery notes", ids.count),
                    look: PickingSessionActionKey.look(for: PickingSessionActionKey.removeDeliveryNotes).forConfirm,
                    isLoading: store.working == .removeDeliveryNotes(ids)
                ) {
                    Task { await run(.removeDeliveryNotes(ids)) }
                }
                .accessibilityIdentifier("dispatch.session.confirmRemoveNotes")
                AikuButton("Cancel", kind: .tertiary) { sheet = nil }
            }
        case .addDeliveryNotes:
            DispatchChooseDeliveryNotesSheet(mode: .add(store)) { _ in sheet = nil }
        }
    }

    private func removeTrolleyTitle(_ trolley: MobileSessionRef) -> String {
        guard let name = trolley.name else { return L10n.text("Remove the trolley from this session?") }
        return L10n.format("Remove %@ from this session?", name)
    }
}

enum DispatchChat {
    static let pickingSessionKind = "picking_session"
}

enum DispatchSessionSheet: Identifiable {
    case attachTrolley
    case detachTrolley(MobileSessionRef)
    case removeDeliveryNotes([Int])
    case addDeliveryNotes

    var id: String {
        switch self {
        case .attachTrolley: "attach"
        case .detachTrolley(let trolley): "detach.\(trolley.id)"
        case .removeDeliveryNotes(let ids): "remove.\(ids.map(String.init).joined(separator: ","))"
        case .addDeliveryNotes: "add"
        }
    }
}

struct DispatchTrolleyChoiceSheet: View {
    let store: PickingSessionStore
    let problem: String?
    let onChoose: (Int) -> Void
    let onClose: () -> Void
    @State private var warehouseStore = WarehouseStore.shared
    @State private var chosenID: Int?

    var body: some View {
        SheetScaffold(title: "Add a trolley", subtitle: store.session.map { L10n.format("Picking session %@", $0.reference) }) {
            if let error = store.trolleysError {
                DispatchLoadProblem(title: "Couldn’t load the trolleys", message: error) { await load() }
            } else if store.isLoadingTrolleys && store.trolleyChoices.isEmpty {
                DispatchSkeletonList(rows: 3)
            } else if store.trolleyChoices.isEmpty {
                EmptyState(icon: .trolley, title: "No free trolleys", message: "Every trolley in this warehouse is in use.")
            } else {
                ScrollView {
                    CardList(store.trolleyChoices) { trolley in
                        ListRow(trolley.name, subtitle: trolley.holderSubtitle, action: { chosenID = trolley.id }) {
                            IconTile(chosenID == trolley.id ? .checkCircle : .trolley, tone: chosenID == trolley.id ? .success : .neutral)
                        } trailing: {
                            EmptyView()
                        }
                        .accessibilityAddTraits(chosenID == trolley.id ? .isSelected : [])
                    }
                }
                .frame(maxHeight: 360)
            }
            if let problem { FieldError(problem) }
        } footer: {
            AikuButton(chosenTitle, isLoading: store.isWorking) {
                if let chosenID { onChoose(chosenID) }
            }
            .disabled(chosenID == nil)
            AikuButton("Cancel", kind: .tertiary, action: onClose)
        }
        .task { await load() }
    }

    private var chosenTitle: String {
        guard let name = store.trolleyChoices.first(where: { $0.id == chosenID })?.name else { return "Choose a trolley" }
        return L10n.format("Use %@", name)
    }

    private func load() async {
        guard let warehouseID = warehouseStore.selectedID else { return }
        await store.loadTrolleyChoices(warehouseID: warehouseID)
    }
}
