# RouteBox — MikroTik WireGuard Setup Guide

This guide explains how to prepare a MikroTik RouterOS 7 device for connection to the RouteBox Admin **MikroTik WireGuard** section.

> The RouteBox integration uses the RouterOS REST API. A container or a separate application on the MikroTik device is not required.

## 1. Requirements

- RouterOS 7.x with WireGuard available.
- A reachable IP address or hostname for the router from the RouteBox server.
- A WireGuard interface already created on the router.
- An IP pool for WireGuard clients if RouteBox will allocate client addresses automatically.
- A dedicated RouterOS user for RouteBox.
- REST API access enabled on the router.

RouteBox reads RouterOS information from `/rest/system/resource` and discovers WireGuard interfaces, IP pools and DNS settings through the REST API.

## 2. Create a dedicated RouteBox user

Do not use the main `admin` account for the integration. Create a dedicated user and group.

For a read-only connection test and discovery:

```routeros
/user group add name=routebox-read policy=read,rest-api
/user add name=routebox group=routebox-read password="CHANGE-THIS-TO-A-LONG-RANDOM-PASSWORD"
```

If RouteBox will later create, modify or disable WireGuard peers and queues, the account will need the corresponding write permissions. Keep permissions as narrow as practical for the deployment.

A custom group is preferable to giving the integration unrestricted `full` access.

## 3. Enable REST API for a temporary HTTP test

For a first connectivity test, HTTP can be used on port 80:

```routeros
/ip service set www disabled=no port=80
```

On RouterOS versions where the plain REST option is available, it is enabled by default unless it has previously been disabled.

### Important

HTTP REST sends Basic Authentication credentials without transport encryption. Use this only for a controlled test path and do not leave it exposed to the public Internet.

After testing, disable HTTP again if it is not required:

```routeros
/ip service set www disabled=yes
```

## 4. Test the REST API from the RouteBox server

Run this on the Linux server running RouteBox. Replace the placeholders:

```bash
curl -v -u 'routebox:YOUR_PASSWORD' \
  'http://ROUTER_IP/rest/system/resource'
```

A successful response is HTTP `200` and contains fields such as:

- `version`
- `uptime`
- `cpu-load`
- `free-memory`
- `total-memory`
- `board-name`
- `architecture-name`

Example shape:

```json
[
  {
    "board-name": "CHR OpenStack Foundation OpenStack Nova",
    "cpu-load": "6",
    "free-memory": "796971008",
    "total-memory": "1073741824",
    "uptime": "14w2d2h50m46s",
    "version": "7.20.2 (stable)"
  }
]
```

A `401 Unauthorized` response normally means the username/password is wrong or the user does not have the required `rest-api` policy.

A timeout means the RouteBox server cannot reach the REST port. Check routing, cloud security groups, RouterOS firewall rules and `/ip service print`.

## 5. Allow only the RouteBox server through the firewall

If the router has a public IP, do not expose REST to the whole Internet.

Replace `ROUTEBOX_SERVER_IP` with the public IP of the RouteBox server.

Example for temporary HTTP testing:

```routeros
/ip firewall filter add chain=input action=accept protocol=tcp dst-port=80 src-address=ROUTEBOX_SERVER_IP comment="RouteBox REST HTTP"
```

Place this rule above a general input-drop rule if necessary.

For production HTTPS, use port 443 instead:

```routeros
/ip firewall filter add chain=input action=accept protocol=tcp dst-port=443 src-address=ROUTEBOX_SERVER_IP comment="RouteBox REST HTTPS"
```

If your router is behind a cloud security group, provider firewall or NAT, allow the same TCP port there as well.

## 6. Production HTTPS setup

For production, prefer `www-ssl` and HTTPS. RouterOS requires a certificate for normal WebFig HTTPS operation.

For a quick self-signed certificate on the router, RouterOS documentation provides the following pattern:

```routeros
/certificate add name=routebox-ca common-name="RouteBox CA" key-usage=key-cert-sign,crl-sign
/certificate sign routebox-ca

/certificate add name=routebox-web common-name=ROUTER_HOSTNAME key-usage=tls-server
/certificate sign routebox-web ca=routebox-ca

/ip service set www-ssl disabled=no port=443 certificate=routebox-web
```

If RouteBox connects by IP address, use the router IP as the certificate identity or, preferably, use a DNS hostname and issue the certificate for that hostname.

A public CA certificate is preferable when practical because clients can validate the certificate without importing a private CA.

### Self-signed certificate note

The RouteBox client currently disables TLS certificate verification so that self-signed RouterOS certificates can be used for the integration. This protects the connection from plaintext credentials but does not provide normal certificate-chain identity verification. For a public production deployment, use a properly issued certificate and restrict the service with firewall rules.

## 7. Verify HTTPS from the RouteBox server

For a self-signed certificate, test with:

```bash
curl -vk -u 'routebox:YOUR_PASSWORD' \
  'https://ROUTER_HOSTNAME/rest/system/resource'
```

For a trusted certificate, use the same command without `-k`:

```bash
curl -v -u 'routebox:YOUR_PASSWORD' \
  'https://ROUTER_HOSTNAME/rest/system/resource'
```

Do not troubleshoot REST through `/ip service api` or port `8728` when using the RouteBox REST integration. RouteBox uses the HTTP/HTTPS REST service and the `/rest/...` URL path.

## 8. WireGuard interface

Confirm that the WireGuard interface exists:

```routeros
/interface wireguard print detail
```

Example:

```text
0 name="wg-routebox" listen-port=51820 ...
```

The RouteBox panel can discover the available WireGuard interfaces automatically after a successful REST connection.

## 9. WireGuard IP pool

If clients receive addresses from a pool, verify the pool:

```routeros
/ip pool print detail
```

Example:

```routeros
/ip pool add name=wg-pool ranges=10.66.0.10-10.66.0.254
```

Use an address range that does not overlap with existing LAN, VPN or customer networks.

## 10. DNS configuration

Check the router DNS configuration:

```routeros
/ip dns print
```

RouteBox can discover the configured DNS server list through REST. If the panel's DNS field is manually specified, use comma-separated values, for example:

```text
1.1.1.1,8.8.8.8
```

## 11. Add the router in RouteBox Admin

Open:

**MikroTik WireGuard → Add MikroTik Server**

Enter:

| Field | Value |
|---|---|
| Server Name | Friendly name such as `USA` |
| IP / Hostname | Router public IP or DNS hostname |
| REST API Port | `80` for temporary HTTP test, `443` for HTTPS |
| API Username | `routebox` |
| API Password | Password created for the RouteBox user |
| VPN Endpoint | Public hostname/IP used by WireGuard clients |
| WireGuard Port | Usually `51820` |
| WireGuard Interface | Example `wg-routebox` |
| IP Pool | Example `wg-pool` |
| DNS Servers | Optional, for example `1.1.1.1,8.8.8.8` |
| HTTPS / TLS | Off for HTTP test; On for HTTPS |

Then click **Test Connection & Save**.

## 12. What RouteBox displays after connection

After a successful connection, the MikroTik server card displays live information discovered from RouterOS:

- Server name
- RouterOS version
- Uptime
- CPU load
- Used / total memory
- REST request latency (`Ping / REST latency`)
- Architecture
- Board / CHR platform
- WireGuard interfaces
- IP pools
- DNS servers
- Connection/discovery status

The latency shown in the panel is the RouteBox server's REST round-trip latency to the router. It is not an ICMP ping measurement.

## 13. Recommended production firewall model

For a public MikroTik CHR, the minimum recommended model is:

1. Allow TCP 443 only from the RouteBox server IP.
2. Keep TCP 80 disabled after testing.
3. Do not expose RouterOS REST to `0.0.0.0/0` unnecessarily.
4. Keep WinBox, SSH and other management services restricted as well.
5. Use a dedicated RouteBox user instead of the primary administrator account.
6. Use a strong, unique password and store it only in RouteBox's encrypted configuration.

Example service restrictions:

```routeros
/ip service set www disabled=yes
/ip service set www-ssl disabled=no port=443
```

Then restrict port 443 in the firewall to the RouteBox server IP.

## 14. Troubleshooting

### `401 Unauthorized`

Check:

```routeros
/user print detail where name="routebox"
/user group print detail where name="routebox-read"
```

The group should include `rest-api` and the permissions needed by the RouteBox operation.

### Connection timeout

From the RouteBox server:

```bash
nc -vz ROUTER_IP 80
nc -vz ROUTER_IP 443
```

Then inspect RouterOS services:

```routeros
/ip service print detail
```

Also check RouterOS firewall and any provider/cloud firewall.

### TLS handshake failure

Check:

```routeros
/ip service print detail where name="www-ssl"
/certificate print detail
```

`www-ssl` must reference a usable certificate. If `certificate=none`, configure a certificate before using HTTPS.

### REST works over HTTP but not HTTPS

This usually means the HTTP service is reachable but the HTTPS service/certificate is not configured correctly. Test HTTP first, then configure `www-ssl` and test again.

### WireGuard interface not found

Run:

```routeros
/interface wireguard print detail
```

If the result is empty, create the WireGuard interface before saving the server in RouteBox.

## 15. Quick test checklist

On MikroTik:

```routeros
/ip service print detail where name="www"
/ip service print detail where name="www-ssl"
/user print detail where name="routebox"
/interface wireguard print detail
/ip pool print detail
/ip dns print
```

On the RouteBox server:

```bash
curl -v -u 'routebox:YOUR_PASSWORD' \
  'http://ROUTER_IP/rest/system/resource'
```

Expected result: HTTP `200` with RouterOS resource information.

Then add the server in RouteBox Admin and use **Test Connection**.

---

## Security reminder

HTTP REST is suitable for controlled testing but should not be used as the permanent public management interface because Basic Authentication credentials are sent over an unencrypted HTTP connection. MikroTik's RouterOS REST documentation recommends HTTPS for secure access and explicitly warns against exposing HTTP REST in production.
