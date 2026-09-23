# PC Audit User Tool

The user-side collector is part of the `kpi_dashboard` repository and must be kept in the `feature/admin-foundation` branch.

## Workflow

1. User enters Audit Code, Department and Full Name.
2. The script validates the Audit Code through the PC Audit API.
3. Customer and Branch are resolved by the server.
4. The collector gathers the local Windows inventory.
5. The collector sends the complete JSON payload to the API over HTTPS.
6. The web application stores the audit and provides search/detail/export.

No Excel module is required on the user's PC. Excel is generated centrally by the web application.

## Source of truth

The existing `Get_System_Info.ps1` is the collector specification. The migration to API must preserve all collected groups and the complete raw payload.

## Current status

This directory is the integration point for the user-side collector. Before production packaging, compare every collector field against the existing `Get_System_Info.ps1` and run an end-to-end test against the PC Audit API.
