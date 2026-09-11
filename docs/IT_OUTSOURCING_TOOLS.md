# IT Outsourcing Tools

## Mục tiêu
Bộ công cụ hỗ trợ IT Outsourcing kiểm kê và kiểm tra nhanh Internet Assets, Domain, Website, SSL/TLS, DNS, Email, IP/Hosting và các dịch vụ liên quan.

## Nguyên tắc
- Ưu tiên 100% miễn phí và open-source.
- Không phụ thuộc API thương mại trong core path.
- Batch-friendly cho danh sách domain.
- Không thực hiện intrusive scanning mặc định.
- Kết quả phải có timestamp, trạng thái, lỗi và thời gian phản hồi.

## Nhóm chức năng

### 1. Domain
- Domain availability/existence
- Registration date
- Expiration date
- Days remaining
- Registrar
- Domain status
- Name servers
- DNSSEC
- RDAP lookup
- WHOIS fallback khi cần

### 2. DNS
- A / AAAA
- CNAME
- NS
- MX
- TXT
- SOA
- CAA
- SPF record
- DMARC record
- DKIM record discovery khi có selector
- DNSSEC status
- DNS provider inference

### 3. SSL/TLS
- Certificate present/absent
- Subject
- SAN
- Issuer
- SSL vendor / CA
- Valid from / valid to
- Days remaining
- TLS version
- Cipher / protocol information where available
- Certificate chain
- Signature algorithm
- Key type / size
- Hostname verification
- Expiry warning levels

### 4. Website / HTTP
- HTTP reachability
- HTTPS reachability
- Status code
- Redirect chain
- Final URL
- Response time
- Content-Type
- Server header
- HSTS
- Common security headers
- Basic website online/offline classification

### 5. Email
- MX provider detection
- SPF validation
- DMARC validation
- DKIM presence checks for configured selectors
- MTA-STS
- TLS-RPT
- Email service inference (Microsoft 365, Google Workspace, etc.)

### 6. IP / Hosting
- IPv4 / IPv6
- PTR / reverse DNS
- ASN
- Network / organization
- Country / region where available
- Hosting / VPS / cloud provider inference
- CDN / WAF detection
- Reverse lookup relationships

### 7. Service inventory
- Identify service records from DNS
- Common service hostnames supplied by customer
- Website / API / VPN / portal / mail / autodiscover classification
- Service availability by HTTP/TCP connectivity

### 8. Reporting
- Domain-level result
- Asset-level result
- Batch scan summary
- Warning/critical counts
- Export CSV/Excel later
- Audit history

## Suggested architecture

```
IT Outsourcing Toolkit
├── Domain Inspector
├── DNS Inspector
├── SSL Inspector
├── Website Inspector
├── Email Inspector
├── IP/Hosting Inspector
├── Service Inventory
└── Reporting
```

## Safety boundary
Core checks should use normal DNS resolution, RDAP/WHOIS, TLS handshakes and ordinary HTTP/HTTPS requests. Port/service discovery must be limited to explicitly authorized assets and common service ports; no broad Internet scanning by default.
