import SwiftUI

struct DispatchPickingSessionsView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var kind: DispatchSessionKind = .dropshipping
    private let store = DispatchStore.shared

    var body: some View {
        let sessions = store.sessions(of: kind)
        AppScreen(title: "Picking sessions", kicker: "Goods out · \(session.warehouseLabel)") {
            ModuleTabs(.goodsOut, current: .pickingSessions)
        } content: {
            SegmentedControl(options: DispatchSessionKind.allCases, selection: $kind, title: \.title)
            if sessions.isEmpty {
                EmptyState(icon: .trolley, title: "No \(kind.title.lowercased()) picking sessions")
            } else {
                CardList(sessions) { item in
                    ListRow(
                        item.reference,
                        subtitle: item.listSubtitle,
                        chips: [Chip(item.state.title, item.state.tone)],
                        action: {
                            if item.state == .pickingFinished {
                                router.open(DispatchRoute.pickingSessionPack(reference: item.reference))
                            } else {
                                router.open(DispatchRoute.pickingSession(reference: item.reference))
                            }
                        }
                    )
                    .accessibilityIdentifier("dispatch.session.\(item.reference)")
                }
            }
            DispatchNote("A picking session groups many delivery notes for one picker. Fulfilment returns have their own sessions.")
        }
    }
}
