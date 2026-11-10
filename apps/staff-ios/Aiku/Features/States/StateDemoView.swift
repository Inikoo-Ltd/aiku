import SwiftUI

enum StateDemo: String, CaseIterable, Identifiable, Hashable {
    case offline
    case sessionExpired
    case noAccess
    case notFound
    case serverError
    case cameraBlocked
    case emptySearch
    case emptyTasks
    case loadingDashboard
    case actionFailed
    case editConflict

    var id: String { rawValue }

    var title: String {
        switch self {
        case .offline: "S01 · Offline"
        case .sessionExpired: "S02 · Session expired"
        case .noAccess: "S03 · No access"
        case .notFound: "S04 · Not found"
        case .serverError: "S05 · Server error"
        case .cameraBlocked: "S06 · Camera blocked"
        case .emptySearch: "S07 · Empty · search"
        case .emptyTasks: "S08 · Empty · no tasks"
        case .loadingDashboard: "S09 · Loading · skeleton"
        case .actionFailed: "S10 · Error · action failed"
        case .editConflict: "S11 · Error · edit conflict"
        }
    }
}

struct StateDemoView: View {
    let demo: StateDemo

    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @Environment(\.signOut) private var signOut

    @State private var searchQuery = "rosemary oil 50ml"
    @State private var isMoveSheetPresented = true

    var body: some View {
        switch demo {
        case .offline:
            OfflineScreen(
                queuedActions: ["Picked 6 × SKU-00733", "Moved 10 × SKU-00412", "Adjustment −2 SKU-00953"],
                onRetry: { toasts.show(.offline, "Still offline · will sync later") },
                onKeepWorking: router.back,
                onViewQueue: { toasts.show(.info, "Sync queue is not built yet") }
            )
        case .sessionExpired:
            SessionExpiredScreen { signOut() }
        case .noAccess:
            NoAccessScreen(
                warehouse: "Warehouse 2",
                module: "Goods in",
                permission: "incoming.view",
                onBack: { router.select(.dashboard) },
                onChangeWarehouse: { toasts.show(.info, "Warehouse picker is not built yet") }
            )
        case .notFound:
            BarcodeNotFoundScreen(
                code: "5060123456789",
                onScanAgain: { router.scan() },
                onTypeCode: { toasts.show(.info, "Search is not built yet") },
                onReport: { toasts.show(.success, "Unknown barcode reported") }
            )
        case .serverError:
            ServerErrorScreen(
                statusCode: 500,
                reference: "7F3A-2C19",
                time: "09:41",
                onRetry: { toasts.show(.error, "Still no response from Aiku", action: ToastAction(title: "Retry") {}) },
                onReport: { toasts.show(.success, "Problem reported") }
            )
        case .cameraBlocked:
            CameraBlockedScreen(
                context: "Goods in · SD-1093",
                onUseScanner: { router.open(.scannerDevice(deliveryNote: "DN-24817")) },
                onTypeCode: { toasts.show(.info, "Search is not built yet") }
            )
        case .emptySearch:
            emptySearch
        case .emptyTasks:
            EmptyTasksScreen(
                warehouse: "\(SampleData.warehouse.name) · \(SampleData.warehouse.code)",
                onPickFromQueue: { router.select(.goodsOut) },
                onBack: { router.select(.dashboard) }
            )
        case .loadingDashboard:
            DashboardLoadingView(warehouseName: SampleData.warehouse.name)
        case .actionFailed:
            actionFailed
        case .editConflict:
            EditConflictScreen(
                reference: "DN-24817",
                editor: "Sam P.",
                summary: "picked 2 items and changed the picker while you were offline.",
                changes: [
                    ConflictChange(title: "SKU-00733", detail: "Picked 3 → 5 of 6"),
                    ConflictChange(title: "Picker", detail: "Alex Morgan → Sam P."),
                ],
                onReload: { toasts.show(.success, "DN-24817 reloaded") },
                onBackToList: { router.select(.goodsOut) }
            )
        }
    }

    private var emptySearch: some View {
        VStack(spacing: 0) {
            ScreenHeader(title: "Search", trailing: .none) {
                SearchBar(text: $searchQuery, placeholder: "SKU, location or delivery") { router.scan() }
            }
            ScrollView {
                EmptySearchResults(
                    query: searchQuery,
                    suggestions: ["rosemary", "SKU-0", "A-03"],
                    onSuggestion: { searchQuery = $0 },
                    onScan: { router.scan() }
                )
            }
        }
        .aikuScreen()
    }

    private var actionFailed: some View {
        VStack(spacing: 0) {
            ScreenHeader(title: "SKU-00733", eyebrow: "SKU", trailing: .none)
            Spacer()
            ActionButton(title: "Show failed transfer again", kind: .secondary) { isMoveSheetPresented = true }
                .padding(16)
        }
        .aikuScreen()
        .sheet(isPresented: $isMoveSheetPresented) {
            StockMoveFailedSheet(
                failure: StockMoveFailure(sku: "SKU-00733", fromLocation: "A-03-02", availableInLocation: 30, toLocation: "B-01-04", requestedQuantity: 36)
            ) { quantity in
                isMoveSheetPresented = false
                toasts.show(.success, "\(quantity) units moved to B-01-04", action: ToastAction(title: "Undo") {})
            }
        }
    }
}
