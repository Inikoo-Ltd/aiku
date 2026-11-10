import SwiftUI

enum GoodsInDetailTab: String, CaseIterable, Hashable {
    case pending
    case done
    case checklist

    var title: String {
        switch self {
        case .pending: "Pending items"
        case .done: "Done items"
        case .checklist: "Checklist"
        }
    }
}

struct GoodsInDeliveryView: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(ToastCenter.self) private var toasts
    @State private var selectedTab: GoodsInDetailTab?
    @State private var editingItem: GoodsInItem?
    @State private var failingCheck: GoodsInDeliveryCheck?
    @State private var isScanning = false
    @State private var isConfirmingUnmarkReceived = false
    @State private var isConfirmingUnmarkDispatched = false
    @State private var isConfirmingCancel = false
    @State private var isConfirmingPlace = false
    private let store = GoodsInStore.shared

    private var delivery: GoodsInDelivery { store.delivery(reference) }
    private var rights: GoodsInRights { GoodsInRights(session: session) }
    private var actions: Set<GoodsInDeliveryAction> { GoodsInDeliveryAction.available(for: delivery, rights: rights) }

    private var tab: GoodsInDetailTab {
        if let selectedTab { return selectedTab }
        switch delivery.state {
        case .bookedIn, .placed: return .done
        default: return .pending
        }
    }

    var body: some View {
        screen
            .aikuSheet(item: $editingItem) { item in
                GoodsInCheckQuantitySheet(item: item) { quantity in
                    saveChecked(item, quantity: quantity)
                    editingItem = nil
                }
            }
            .aikuSheet(item: $failingCheck) { check in
                GoodsInFailCheckSheet(check: check) { note in
                    store.recordCheck(reference, checkID: check.id, passed: false, note: note, by: session.profile.name)
                    toasts.show("\(check.title) failed", tone: .warn)
                    failingCheck = nil
                } onCancel: {
                    failingCheck = nil
                }
            }
            .fullScreenCover(isPresented: $isScanning) {
                GoodsInCheckScanView(reference: reference) { isScanning = false }
            }
            .aikuConfirm("Unmark as received?", isPresented: $isConfirmingUnmarkReceived, message: "\(reference) goes back to dispatched and its checked quantities are cleared.", confirmTitle: "Unmark", isDestructive: true) {
                store.unmarkAsReceived(reference)
                selectedTab = nil
                toasts.show("\(reference) is back to dispatched")
            }
            .aikuConfirm("Unmark as dispatched?", isPresented: $isConfirmingUnmarkDispatched, message: "\(reference) goes back to the state before dispatch and leaves goods in.", confirmTitle: "Unmark", isDestructive: true) {
                store.unmarkAsDispatched(reference)
                toasts.show("\(reference) unmarked as dispatched")
                router.back()
            }
            .aikuConfirm("Cancel stock delivery?", isPresented: $isConfirmingCancel, message: "\(reference) will be cancelled. This cannot be undone here.", confirmTitle: "Cancel delivery", cancelTitle: "Keep", isDestructive: true) {
                store.cancel(reference)
                toasts.show("\(reference) cancelled")
                router.back()
            }
            .aikuConfirm("Place \(reference)?", isPresented: $isConfirmingPlace, message: "This is its final state and starts costing.", confirmTitle: "Place") {
                store.setAsPlaced(reference)
                toasts.show("\(reference) placed · costing starts")
            }
    }

    @ViewBuilder
    private var screen: some View {
        let kicker = "Stock delivery · \(delivery.sourceName)"
        if hasFooter {
            AppScreen(title: delivery.reference, kicker: kicker) {
                content
            } footer: {
                footer
            }
        } else {
            AppScreen(title: delivery.reference, kicker: kicker) {
                content
            }
        }
    }

    @ViewBuilder
    private var content: some View {
        GoodsInProgressSteps(state: delivery.state)
        switch delivery.state {
        case .arriving:
            arrivingContent
        case .bookingIn:
            GoodsInBookingInContent(
                delivery: delivery,
                canWork: actions.contains(.checkItems),
                onPlace: openPlacement,
                onCheck: { editingItem = $0 },
                onOpenSko: openSko
            )
            if !actions.contains(.checkItems) {
                GoodsInStatusLine(text: GoodsInDeliveryAction.statusLine(for: delivery, rights: rights))
            }
        default:
            tabbedContent
        }
    }

    @ViewBuilder
    private var arrivingContent: some View {
        GoodsInOrdererCard(delivery: delivery)
        GoodsInFactGrid(title: "Delivery", facts: delivery.deliveryFacts)
        GoodsInFactGrid(title: "Items", facts: [
            GoodsInFact("Items", delivery.items.count.grouped),
            GoodsInFact("New SKOs", delivery.newSkoCount.grouped),
            GoodsInFact("Weight", delivery.weight),
            GoodsInFact("Volume", delivery.volume ?? "—")
        ])
        HelperText("Mark it as received when it arrives at goods in. Checking starts after that. Needs goods-in edit rights.")
            .padding(.horizontal, 4)
        actionSections
    }

    @ViewBuilder
    private var tabbedContent: some View {
        let isWorking = [GoodsInDeliveryState.received, .checked, .notReceived].contains(delivery.state)
        if tab == .pending, isWorking {
            GoodsInOrdererCard(delivery: delivery)
            GoodsInFactGrid(title: "Delivery", facts: delivery.deliveryFacts)
            GoodsInFactGrid(title: "Items", facts: itemFacts)
        }
        SegmentedControl(options: GoodsInDetailTab.allCases, selection: Binding(get: { tab }, set: { selectedTab = $0 }), title: \.title)
        switch tab {
        case .pending:
            GoodsInPendingItems(
                delivery: delivery,
                canCheck: actions.contains(.checkItems),
                onScan: { isScanning = true },
                onEdit: { editingItem = $0 }
            )
            actionSections
        case .done:
            GoodsInDoneItems(delivery: delivery, onOpenSko: openSko)
            actionSections
        case .checklist:
            GoodsInChecklist(delivery: delivery)
        }
    }

    private var itemFacts: [GoodsInFact] {
        [
            GoodsInFact("Items", delivery.items.count.grouped),
            GoodsInFact("Received / checked", "\(delivery.checkedCount) of \(delivery.items.count)"),
            GoodsInFact("Placed", delivery.placedItemCount.grouped),
            GoodsInFact("New SKOs", delivery.newSkoCount.grouped),
            GoodsInFact("Under delivered", delivery.underCount.grouped),
            GoodsInFact("Over delivered", delivery.overCount.grouped),
            GoodsInFact("Possible unit mismatch", delivery.unitMismatchCount.grouped),
            GoodsInFact("Weight", delivery.weight)
        ]
    }

    @ViewBuilder
    private var actionSections: some View {
        if actions.contains(.unmarkAsDispatched) {
            SectionHeading("With procurement edit rights only")
            Card {
                ListRow("Unmark as dispatched", subtitle: "Back to the state before dispatch", icon: .undo, iconTone: .neutral) {
                    isConfirmingUnmarkDispatched = true
                }
            }
        }
        if actions.contains(.unmarkAsReceived) {
            SectionHeading("Goods-in supervisor")
            Card {
                ListRow("Unmark as received", subtitle: "Back to dispatched · needs incoming supervisor rights", icon: .undo, iconTone: .neutral) {
                    isConfirmingUnmarkReceived = true
                }
            }
        }
        if actions.contains(.cancel) {
            SectionHeading("With procurement edit rights only")
            Card {
                ListRow("Cancel stock delivery", subtitle: "Only while nothing is placed", icon: .close, iconTone: .danger) {
                    isConfirmingCancel = true
                }
            }
        }
        if actions.contains(.setAsPlaced) {
            SectionHeading("With procurement or accounting edit rights only")
            Card {
                ListRow("Place", subtitle: "Place this stock delivery, this is its final state · starts costing", icon: .check, iconTone: .info) {
                    isConfirmingPlace = true
                }
                .accessibilityIdentifier("goodsIn.setAsPlaced")
            }
        }
        if actions.isEmpty {
            GoodsInStatusLine(text: GoodsInDeliveryAction.statusLine(for: delivery, rights: rights))
        }
    }

    private var hasFooter: Bool {
        switch delivery.state {
        case .arriving:
            return actions.contains(.markAsReceived)
        case .bookingIn:
            return actions.contains(.placeItems) && delivery.nextItemToPlace != nil
        case .bookedIn, .placed:
            return true
        case .received, .checked, .notReceived:
            if tab == .checklist {
                return delivery.nextCheckToDo != nil && rights.canEditGoodsIn
            }
            return actions.contains(.placeItems)
        case .cancelled, .withdrawn:
            return false
        }
    }

    @ViewBuilder
    private var footer: some View {
        switch delivery.state {
        case .arriving:
            AikuButton("Mark as received", icon: .check) {
                store.markAsReceived(reference)
                toasts.show("\(reference) marked as received")
            }
            .accessibilityIdentifier("goodsIn.markAsReceived")
        case .bookingIn:
            if let item = delivery.nextItemToPlace {
                AikuButton("Place \(item.sko)", icon: .locations) { openPlacement(item) }
                    .accessibilityIdentifier("goodsIn.placeNext")
            }
        case .bookedIn, .placed:
            AikuButton("Next stock delivery") { openNextDelivery() }
                .accessibilityIdentifier("goodsIn.nextDelivery")
        default:
            if tab == .checklist, let check = delivery.nextCheckToDo {
                HStack(spacing: 8) {
                    AikuButton("Fail", kind: .danger) { failingCheck = check }
                        .accessibilityIdentifier("goodsIn.check.fail")
                    AikuButton("Pass") {
                        store.recordCheck(reference, checkID: check.id, passed: true, note: "", by: session.profile.name)
                        toasts.show("\(check.title) passed")
                    }
                    .accessibilityIdentifier("goodsIn.check.pass")
                }
            } else {
                AikuButton("Place checked items", icon: .locations, kind: delivery.state == .checked ? .primary : .secondary) {
                    store.startBookingIn(reference)
                    selectedTab = nil
                    toasts.show("Booking in \(reference)")
                }
                .accessibilityIdentifier("goodsIn.placeChecked")
            }
        }
    }

    private func saveChecked(_ item: GoodsInItem, quantity: Int) {
        let before = delivery.state
        store.setChecked(reference, sko: item.sko, quantity: quantity)
        let after = delivery.state
        if after != before, after == .checked || after == .notReceived || after == .bookedIn {
            toasts.show("All items checked · \(reference) is \(after.title)")
        } else {
            toasts.show("\(item.sko) · \(quantity.grouped) of \(item.delivered.grouped) checked")
        }
    }

    private func openPlacement(_ item: GoodsInItem) {
        if item.pickingLocation == nil {
            router.open(GoodsInRoute.placeNewItem(reference: reference, sko: item.sko))
        } else {
            router.open(GoodsInRoute.placeItem(reference: reference, sko: item.sko))
        }
    }

    private func openSko(_ item: GoodsInItem) {
        router.open(InventoryRoute.sko(reference: item.sko))
    }

    private func openNextDelivery() {
        if let next = store.nextDeliveryNeedingWork(after: reference) {
            router.replace(with: GoodsInRoute.stockDelivery(reference: next.reference))
        } else {
            toasts.show("No other stock delivery to work on", tone: .info)
            router.back()
        }
    }
}
