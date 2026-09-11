# IT Outsourcing Tools

Bộ công cụ IT Outsourcing tích hợp vào Admin Foundation.

## Core modules
- Domain Audit: RDAP/WHOIS fallback, expiry, registrar, status, nameserver.
- DNS Audit: A/AAAA/CNAME/NS/MX/TXT/SOA/CAA, SPF, DMARC, DNSSEC.
- SSL/TLS Audit: certificate, issuer/CA, SAN, validity, days remaining, TLS protocol.
- Website Audit: HTTP/HTTPS status, redirects, final URL, response time, headers, HSTS.
- IP Audit: IPv4/IPv6, PTR, ASN, network/organization, provider inference.
- Email Audit: MX provider, SPF, DMARC, MTA-STS, TLS-RPT.
- Service Inventory: customer-supplied service hostnames such as web/api/vpn/portal/mail.
- Batch Audit: bulk domain input and consolidated reporting.
- Reporting: JSON/CSV/Excel-ready result structure.

## Design principles
- Free/open-source core path.
- No commercial API dependency for core checks.
- Reuse existing Laravel authentication and permission middleware.
- No broad or intrusive Internet scanning by default.
- Every result includes status, checked_at, response time where applicable, and error information.
