import Foundation
import Security

protocol TokenStoring: Sendable {
    func load() -> String?
    func save(_ token: String, persist: Bool)
    func clear()
}

final class KeychainTokenStore: TokenStoring, @unchecked Sendable {
    static let shared = KeychainTokenStore()

    private let service: String
    private let account = "mobile-token"
    private let lock = NSLock()
    private var memoryToken: String?

    init(service: String = (Bundle.main.bundleIdentifier ?? "com.inikoo.aiku") + ".auth") {
        self.service = service
    }

    func load() -> String? {
        lock.lock()
        defer { lock.unlock() }
        if let memoryToken {
            return memoryToken
        }
        var query = baseQuery
        query[kSecReturnData as String] = true
        query[kSecMatchLimit as String] = kSecMatchLimitOne
        var item: CFTypeRef?
        guard SecItemCopyMatching(query as CFDictionary, &item) == errSecSuccess,
              let data = item as? Data,
              let token = String(data: data, encoding: .utf8) else {
            return nil
        }
        memoryToken = token
        return token
    }

    func save(_ token: String, persist: Bool) {
        lock.lock()
        defer { lock.unlock() }
        memoryToken = token
        SecItemDelete(baseQuery as CFDictionary)
        guard persist else { return }
        var attributes = baseQuery
        attributes[kSecValueData as String] = Data(token.utf8)
        attributes[kSecAttrAccessible as String] = kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly
        SecItemAdd(attributes as CFDictionary, nil)
    }

    func clear() {
        lock.lock()
        defer { lock.unlock() }
        memoryToken = nil
        SecItemDelete(baseQuery as CFDictionary)
    }

    private var baseQuery: [String: Any] {
        [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: account
        ]
    }
}

final class InMemoryTokenStore: TokenStoring, @unchecked Sendable {
    private let lock = NSLock()
    private var token: String?
    private(set) var persistedToken: String?

    init(token: String? = nil) {
        self.token = token
        persistedToken = token
    }

    func load() -> String? {
        lock.withLock { token }
    }

    func save(_ token: String, persist: Bool) {
        lock.withLock {
            self.token = token
            persistedToken = persist ? token : nil
        }
    }

    func clear() {
        lock.withLock {
            token = nil
            persistedToken = nil
        }
    }
}
