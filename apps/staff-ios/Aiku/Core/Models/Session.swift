import Foundation
import Observation

struct StaffProfile: Equatable, Sendable {
    var name: String
    var username: String
    var email: String? = nil
    var jobPositions: [String] = []
    var role: String
    var organisation: String
    var warehouseName: String
    var warehouseCode: String

    var firstName: String {
        name.split(separator: " ").first.map(String.init) ?? name
    }

    var initials: String {
        name.split(separator: " ").prefix(2).compactMap(\.first).map(String.init).joined().uppercased()
    }

    static let sample = StaffProfile(
        name: "Alex Morgan",
        username: "alex.morgan",
        role: "Warehouse staff",
        organisation: "AW UK",
        warehouseName: "Main Warehouse",
        warehouseCode: "WH1"
    )
}

enum AccessLevel: String, Sendable {
    case all
    case staff
}

@MainActor
@Observable
final class Session {
    var profile: StaffProfile
    var access: AccessLevel
    var assignedCount: Int
    var unreadCount: Int

    init(profile: StaffProfile = .sample, access: AccessLevel = .all, assignedCount: Int = 6, unreadCount: Int = 3) {
        self.profile = profile
        self.access = access
        self.assignedCount = assignedCount
        self.unreadCount = unreadCount
    }

    var hasWarehouseAccess: Bool { access == .all }
    var hasFulfilmentAccess: Bool { access == .all }
    var canScan: Bool { hasWarehouseAccess || hasFulfilmentAccess }

    func apply(_ response: ProfileResponse) {
        let employee = response.employee?.first
        profile.name = [response.contactName, employee?.contactName, response.username]
            .compactMap { $0?.trimmingCharacters(in: .whitespacesAndNewlines) }
            .first { !$0.isEmpty } ?? response.username
        profile.username = response.username
        profile.email = response.email ?? employee?.email
        profile.jobPositions = response.displayedJobPositions
        if let position = profile.jobPositions.first {
            profile.role = position
        } else if let jobTitle = employee?.jobTitle, !jobTitle.isEmpty {
            profile.role = jobTitle
        } else {
            profile.role = "Staff"
        }
    }

    var warehouseLabel: String { "\(profile.warehouseName) · \(profile.warehouseCode)" }
    var contextLabel: String { "\(profile.organisation) · \(profile.warehouseName)" }
    var fullContextLabel: String { "\(profile.organisation) · \(warehouseLabel)" }
}
