# IT Service Catalog

Service Catalog is the master-data layer for IT Monitoring. Service Types own their allowed Service Terms.

Default Service Types:
- Domain
- SSL Certificate
- VPS
- Hosting
- License
- Internet
- Backup
- Email Service
- Website
- Maintenance Contract
- Other

Standard Service Terms:
- 1 month
- 3 months
- 6 months
- 9 months
- 12 months
- 24 months

Alert Policy is intentionally not embedded in Service Type CRUD yet; it will be implemented as a child configuration of Service Type with three percentage-based alert points.
