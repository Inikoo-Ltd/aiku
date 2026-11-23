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

    static let notLoaded = StaffProfile(name: "", username: "", role: "", organisation: "", warehouseName: "", warehouseCode: "")
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
    var hasWarehouseContext = false
    var modules: ModuleAccess = .everything

    init(profile: StaffProfile = .notLoaded, access: AccessLevel = .all) {
        self.profile = profile
        self.access = access
    }

    var hasProfile: Bool { !profile.name.isEmpty }

    func forgetProfile() {
        profile = .notLoaded
        hasWarehouseContext = false
    }

    var visibleModules: ModuleAccess { access == .all ? modules : .nothing }
    var hasWarehouseAccess: Bool { visibleModules.hasWarehouseModules }
    var hasFulfilmentAccess: Bool { visibleModules.fulfilment }
    var canScan: Bool { visibleModules.canScan }

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

    func apply(_ response: MobileProfileResponse) {
        let name = response.name?.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
        profile.name = name.isEmpty ? response.username : name
        profile.username = response.username
        profile.jobPositions = response.displayedJobPositions
        if let position = profile.jobPositions.first {
            profile.role = position
        } else if let jobTitle = response.jobTitle, !jobTitle.isEmpty {
            profile.role = jobTitle
        } else {
            profile.role = "Staff"
        }
        if !hasWarehouseContext, let organisation = response.organisation?.name {
            profile.organisation = organisation
        }
    }

    func apply(warehouse name: String, code: String, organisation: String) {
        profile.warehouseName = name
        profile.warehouseCode = code
        profile.organisation = organisation
        hasWarehouseContext = true
    }

    var warehouseLabel: String { Self.joined([profile.warehouseName, profile.warehouseCode]) }
    var contextLabel: String { Self.joined([profile.organisation, profile.warehouseName]) }
    var fullContextLabel: String { Self.joined([profile.organisation, profile.warehouseName, profile.warehouseCode]) }

    private static func joined(_ parts: [String]) -> String {
        parts.filter { !$0.isEmpty }.joined(separator: " · ")
    }
}
