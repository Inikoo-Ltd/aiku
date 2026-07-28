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
    mkdir -p /plaza2
    cp devops/vswitch/exports /etc/exports
    exportfs -ra
    ufw allow from 10.0.1.0/24 to any port 2049 proto tcp
    ufw deny 2049

## 4. helio, boro, litio: NFS clients

    apt install -y nfs-common
    mkdir -p /plaza2
    echo '10.0.1.4:/plaza2 /plaza2 nfs4 defaults,_netdev,noatime,nofail 0 0' >> /etc/fstab
    systemctl daemon-reload
    mount /plaza2

## 5. neon: Postgres for nightowl

Postgres already runs on neon. Add a database + role for nightowl. Two
separate consumers need access, on different interfaces:

- helio, boro, litio — private, over the vSwitch (10.0.1.0/24)
- the NightOwl SaaS dashboard — public, from its single static IP
  `178.156.227.16`, to read telemetry

    sudo -u postgres psql -c "CREATE ROLE nightowl LOGIN PASSWORD 'CHANGE_ME';"
    sudo -u postgres psql -c "CREATE DATABASE nightowl OWNER nightowl;"

Find the config dir (`sudo -u postgres psql -c 'SHOW config_file;'`), then in
`postgresql.conf` add 10.0.1.4 and neon's public IP to `listen_addresses`
(keep localhost too):

    listen_addresses = 'localhost,10.0.1.4,157.180.99.45'

In `pg_hba.conf`, add one line per consumer — do not use `0.0.0.0/0`:

    host    nightowl    nightowl    10.0.1.0/24         scram-sha-256
    host    nightowl    nightowl    178.156.227.16/32   scram-sha-256

Then:

    ufw allow from 10.0.1.0/24 to any port 5432 proto tcp
    ufw allow from 178.156.227.16/32 to any port 5432 proto tcp
    ufw deny 5432
    systemctl restart postgresql

On helio, boro, litio set in `.env` (or `devops/server.env`):

    NIGHTOWL_DB_HOST=10.0.1.4
    NIGHTOWL_DB_PORT=5432
    NIGHTOWL_DB_DATABASE=nightowl
    NIGHTOWL_DB_USERNAME=nightowl
    NIGHTOWL_DB_PASSWORD=CHANGE_ME

Verify from any of the three (over the VLAN, not the public IP):

    psql "postgresql://nightowl:CHANGE_ME@10.0.1.4:5432/nightowl" -c 'select 1;'

## Notes

- The Storage Box cannot join a vSwitch. Serve NFS from neon's local RAID10
  disk; use the Storage Box for backups.
- Keep 2049 closed on public interfaces; only the vSwitch subnet may mount.
- Same rule for 5432: `ufw allow from 10.0.1.0/24 ... ; ufw deny 5432` — never
  open Postgres on the public interface.
