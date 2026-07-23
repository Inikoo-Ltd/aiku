# Hetzner vSwitch + NFS (neon serves helio, boro, litio)

Private VLAN network over Hetzner Robot vSwitch. The physical switch network
(10.0.0.x) stays untouched; the vSwitch uses 10.0.1.0/24 so neon (not on the
physical switch) can reach the other boxes.

IP plan mirrors the physical switch's last octet: litio=10.0.1.1,
helio=10.0.1.2, boro=10.0.1.3, neon=10.0.1.4.
VLAN id 4000, MTU 1400 (Hetzner vSwitch requirement).

## 1. Robot panel (one time)

Robot -> Servers -> vSwitches -> Create vSwitch, VLAN id 4000.
Add helio, boro, litio, neon to it.

## 2. Per server: VLAN interface

Find the uplink NIC (`ip -br link`, e.g. enp0s31f6), then copy the host's
netplan snippet, replacing {{UPLINK_NIC}}:

    sed 's/{{UPLINK_NIC}}/enp0s31f6/' devops/vswitch/netplan/60-vswitch-<host>.yaml \
      > /etc/netplan/60-vswitch.yaml
    chmod 600 /etc/netplan/60-vswitch.yaml
    netplan apply

Verify (from any host, payload sized for MTU 1400):

    ping -M do -s 1372 10.0.1.4

## 3. neon: NFS server

    apt install -y nfs-kernel-server
    mkdir -p /srv/nfs/shared
    cp devops/vswitch/exports /etc/exports
    exportfs -ra
    ufw allow from 10.0.1.0/24 to any port 2049 proto tcp
    ufw deny 2049

## 4. helio, boro, litio: NFS clients

    apt install -y nfs-common
    mkdir -p /mnt/shared
    echo '10.0.1.4:/srv/nfs/shared /mnt/shared nfs4 defaults,_netdev,noatime,nofail 0 0' >> /etc/fstab
    systemctl daemon-reload
    mount /mnt/shared

## Notes

- The Storage Box cannot join a vSwitch. Serve NFS from neon's local RAID10
  disk; use the Storage Box for backups.
- Keep 2049 closed on public interfaces; only the vSwitch subnet may mount.
