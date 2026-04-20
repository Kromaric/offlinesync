import Foundation
import Network

/// Moniteur de connectivité réseau pour iOS
/// Détecte les changements de connexion (WiFi, Cellular, etc.)
public class ConnectivityMonitor {
    
    private let monitor = NWPathMonitor()
    private let queue = DispatchQueue(label: "com.vendor.offlinesync.connectivity")
    private var onConnectivityChanged: ((Bool) -> Void)?
    private var currentPath: NWPath?
    
    // MARK: - Initialization
    
    public init() {
        // Observer le path actuel
        monitor.pathUpdateHandler = { [weak self] path in
            self?.currentPath = path
        }
    }
    
    deinit {
        stopMonitoring()
    }
    
    // MARK: - Connection Status
    
    /// Vérifier si le device est connecté à Internet
    public func isOnline() -> Bool {
        guard let path = currentPath ?? monitor.currentPath as NWPath? else {
            return false
        }
        return path.status == .satisfied
    }
    
    /// Vérifier si la connexion est en WiFi
    public func isWifi() -> Bool {
        guard let path = currentPath ?? monitor.currentPath as NWPath? else {
            return false
        }
        return path.usesInterfaceType(.wifi)
    }
    
    /// Vérifier si la connexion est en données mobiles
    public func isCellular() -> Bool {
        guard let path = currentPath ?? monitor.currentPath as NWPath? else {
            return false
        }
        return path.usesInterfaceType(.cellular)
    }
    
    /// Vérifier si la connexion est en Ethernet (iPad avec adaptateur)
    public func isEthernet() -> Bool {
        guard let path = currentPath ?? monitor.currentPath as NWPath? else {
            return false
        }
        return path.usesInterfaceType(.wiredEthernet)
    }
    
    /// Obtenir le type de connexion
    public func getConnectionType() -> String {
        if !isOnline() {
            return "offline"
        } else if isWifi() {
            return "wifi"
        } else if isCellular() {
            return "cellular"
        } else if isEthernet() {
            return "ethernet"
        } else {
            return "other"
        }
    }
    
    // MARK: - Monitoring
    
    /// Démarrer le monitoring de connectivité
    ///
    /// - Parameter callback: Fonction appelée quand la connectivité change
    ///                       Reçoit true si online, false si offline
    public func startMonitoring(callback: @escaping (Bool) -> Void) {
        self.onConnectivityChanged = callback
        
        monitor.pathUpdateHandler = { [weak self] path in
            guard let self = self else { return }
            
            self.currentPath = path
            let isOnline = path.status == .satisfied
            
            // Appeler le callback sur le main thread
            DispatchQueue.main.async {
                self.onConnectivityChanged?(isOnline)
            }
        }
        
        monitor.start(queue: queue)
    }
    
    /// Arrêter le monitoring
    public func stopMonitoring() {
        monitor.cancel()
        onConnectivityChanged = nil
    }
    
    // MARK: - Connection Details
    
    /// Obtenir des informations détaillées sur la connexion
    public func getConnectionInfo() -> [String: Any] {
        guard let path = currentPath ?? monitor.currentPath as NWPath? else {
            return [
                "is_online": false,
                "connection_type": "offline"
            ]
        }
        
        var info: [String: Any] = [
            "is_online": path.status == .satisfied,
            "connection_type": getConnectionType(),
            "is_wifi": path.usesInterfaceType(.wifi),
            "is_cellular": path.usesInterfaceType(.cellular),
            "is_ethernet": path.usesInterfaceType(.wiredEthernet),
            "is_expensive": path.isExpensive,
            "is_constrained": path.isConstrained
        ]
        
        // Ajouter les interfaces disponibles
        var availableInterfaces: [String] = []
        for interface in path.availableInterfaces {
            switch interface.type {
            case .wifi:
                availableInterfaces.append("wifi")
            case .cellular:
                availableInterfaces.append("cellular")
            case .wiredEthernet:
                availableInterfaces.append("ethernet")
            case .loopback:
                availableInterfaces.append("loopback")
            case .other:
                availableInterfaces.append("other")
            @unknown default:
                availableInterfaces.append("unknown")
            }
        }
        info["available_interfaces"] = availableInterfaces
        
        return info
    }
    
    /// Vérifier si la connexion est coûteuse (données mobiles limitées)
    public func isExpensiveConnection() -> Bool {
        guard let path = currentPath ?? monitor.currentPath as NWPath? else {
            return false
        }
        return path.isExpensive
    }
    
    /// Vérifier si la connexion est limitée (Low Data Mode activé)
    public func isConstrainedConnection() -> Bool {
        guard let path = currentPath ?? monitor.currentPath as NWPath? else {
            return false
        }
        return path.isConstrained
    }
    
    /// Vérifier si le VPN est actif
    public func supportsIPv4() -> Bool {
        guard let path = currentPath ?? monitor.currentPath as NWPath? else {
            return false
        }
        return path.supportsIPv4
    }
    
    /// Vérifier le support IPv6
    public func supportsIPv6() -> Bool {
        guard let path = currentPath ?? monitor.currentPath as NWPath? else {
            return false
        }
        return path.supportsIPv6
    }
    
    /// Vérifier si le support DNS est disponible
    public func supportsDNS() -> Bool {
        guard let path = currentPath ?? monitor.currentPath as NWPath? else {
            return false
        }
        return path.supportsDNS
    }
}
