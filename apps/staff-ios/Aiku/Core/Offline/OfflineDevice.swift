import Foundation
import Security

final class OfflineDevice: @unchecked Sendable {
    static let shared = OfflineDevice()

    private let service: String
    private let defaults: UserDefaults
    private let lock = NSLock()
    private var cachedDeviceID: String?

    init(service: String = (Bundle.main.bundleIdentifier ?? "com.inikoo.aiku") + ".offline", defaults: UserDefaults = .standard) {
        self.service = service
        self.defaults = defaults
    }

    var deviceID: String {
        lock.withLock {
            if let cachedDeviceID {
                return cachedDeviceID
            }
            let stored = read(Self.deviceAccount).flatMap { $0.isEmpty ? nil : $0 }
            let deviceID = stored ?? UUID().uuidString.lowercased()
            if stored == nil {
                write(deviceID, to: Self.deviceAccount)
            }
            cachedDeviceID = deviceID
            return deviceID
        }
    }

    func lastSequence(userID: Int) -> Int64 {
        lock.withLock { storedSequence(userID: userID) }
    }

    func reserveSequences(userID: Int, count: Int) -> [Int64] {
        guard count > 0 else { return [] }
        return lock.withLock {
            let last = storedSequence(userID: userID)
            let reserved = (1...count).map { last + Int64($0) }
            write(String(reserved.last ?? last), to: Self.sequencePrefix + String(userID))
            return reserved
        }
    }

    func noticesSent(userID: Int) -> String? {
        defaults.string(forKey: Self.noticesPrefix + String(userID))
    }

    func markNoticesSent(userID: Int, sessionKey: String) {
        defaults.set(sessionKey, forKey: Self.noticesPrefix + String(userID))
    }

    private func storedSequence(userID: Int) -> Int64 {
        read(Self.sequencePrefix + String(userID)).flatMap { Int64($0) } ?? 0
    }

    private func read(_ account: String) -> String? {
        var query = baseQuery(account)
        query[kSecReturnData as String] = true
        query[kSecMatchLimit as String] = kSecMatchLimitOne
        var item: CFTypeRef?
        guard SecItemCopyMatching(query as CFDictionary, &item) == errSecSuccess, let data = item as? Data else {
            return nil
        }
        return String(data: data, encoding: .utf8)
    }

    private func write(_ value: String, to account: String) {
        let data = Data(value.utf8)
        let status = SecItemUpdate(baseQuery(account) as CFDictionary, [kSecValueData as String: data] as CFDictionary)
        guard status == errSecItemNotFound else { return }
        var attributes = baseQuery(account)
        attributes[kSecValueData as String] = data
        attributes[kSecAttrAccessible as String] = kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly
        SecItemAdd(attributes as CFDictionary, nil)
    }

    private func baseQuery(_ account: String) -> [String: Any] {
        [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: account
        ]
    }

    private static let deviceAccount = "device_id"
    private static let sequencePrefix = "last_sequence_"
    private static let noticesPrefix = "aiku.offline.noticesSent."
}
