import SwiftUI

struct SystemDestination: View {
    let route: SystemRoute

    var body: some View {
        switch route {
        case .syncQueue: SystemSyncQueueView()
        case .conflicts: SystemConflictsView()
        case .conflict(let reference): SystemConflictResolveView(reference: reference)
        case .statesGallery: SystemStatesGalleryView()
        case .onlineOnlyAction: SystemOnlineOnlyView()
        case .offline: SystemOfflineView()
        case .sessionExpired: SystemSessionExpiredView()
        case .noAccess: SystemNoAccessView()
        case .notFound: SystemNotFoundView()
        case .serverError: SystemServerErrorView()
        case .cameraOff: SystemCameraOffView()
        case .emptySearch: SystemEmptySearchView()
        case .emptyTasks: SystemEmptyTasksView()
        case .loadingDashboard: SystemLoadingDashboardView()
        case .actionFailed: SystemActionFailedView()
        }
    }
}
