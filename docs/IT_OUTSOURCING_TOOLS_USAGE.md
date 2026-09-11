# IT Outsourcing Tools – Usage Guide

## Single Asset Audit

`POST /admin/it-tools/audit`

Request JSON:

```json
{
  "domain": "example.com",
  "wan_ip": "203.0.113.10",
  "dkim_selectors": "selector1,selector2"
}
```

The audit combines:
- Domain/RDAP
- DNS records and provider inference
- SSL/TLS certificate details
- HTTP/HTTPS availability and response time
- Email MX/SPF/DKIM/DMARC/MTA-STS/TLS-RPT
- IP/PTR/RDAP information

## Bulk Audit

`POST /admin/it-tools/bulk-audit`

Request JSON:

```json
{
  "items": [
    {"domain": "example.com", "wan_ip": "203.0.113.10"},
    {"domain": "example.vn", "wan_ip": null}
  ]
}
```

The current application-level limit is 100 assets per request. Larger inventories should be split into multiple batches or moved to a queue worker in a later phase.

## Free/Open-source design

The checker core does not require commercial APIs. It uses ordinary DNS resolution, RDAP/WHOIS-compatible lookups, TLS handshakes, and normal HTTP/HTTPS requests.

## Safety boundary

Only audit assets the operator is authorized to assess. The core module does not perform broad Internet port scanning or intrusive vulnerability testing.
