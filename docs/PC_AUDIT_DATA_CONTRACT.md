# PC Audit Data Contract

## Purpose

The PowerShell collector is the source of truth for the PC Audit payload. The API must preserve the complete payload in `pc_audits.raw_payload` and persist searchable/repeating groups into their dedicated tables.

## User-supplied fields

- `code`: Audit Code created by administration.
- `department`: free text entered by the user.
- `employee_name`: free text entered by the user.

Customer and branch are resolved server-side from the Audit Code and are never trusted from the client payload.

## Collector groups

The payload is expected to preserve these groups:

- computer identity: username, domain, computer name, manufacturer, model, serial number, asset tag
- mainboard / BIOS
- CPU
- memory modules
- storage / partitions
- monitors
- GPU
- battery
- Windows / OS information
- Windows Update
- last boot / uptime
- network adapters: LAN, Wi-Fi, WWAN/modem where detected
- network addressing: IPv4, gateway, MAC, DNS, DHCP, connection status, link speed
- antivirus / Defender
- BitLocker
- Firewall profiles
- TPM
- Secure Boot
- Windows activation
- Office / Microsoft 365 activation
- installed software

## Storage rule

The original collector payload is always retained. Repeating data is additionally normalized for search and reporting. This avoids losing fields when the collector evolves.

## Export rule

A single-machine export must contain the complete audit record. A multi-machine export must contain a complete detail set for every selected machine, not one summary row per machine.
