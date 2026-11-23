# ADR-014 – Global ON DELETE RESTRICT Policy

**Status:** APPROVED  
**Version:** 1.0  
**Date:** 23/11/2026  
**Branch:** feature/ITSMWEB  
**Scope:** CORE, PL1, PL2, PL3, PL4, PL5 and COMMON  
**Boundary:** Design decision only. No DDL, Migration, PHP or database changes.

## 1. Context

The constraint review identified inconsistent or unspecified ON DELETE behavior across PL1, PL2, PL3, PL5 and COMMON. ADR-011 already approved ON DELETE RESTRICT for PL4 only. A system-wide policy is required so that parent deletion cannot silently remove or detach business history, security records, audit evidence or operational records.

## 2. Decision

Apply **ON DELETE RESTRICT** to every actual foreign-key relationship in CORE, PL1, PL2, PL3, PL4, PL5 and COMMON.

- No FK may use ON DELETE CASCADE.
- No FK may use ON DELETE SET NULL.
- No FK may rely on an implicit engine default as a substitute for documenting the policy.
- This policy applies to nullable and NOT NULL FK columns alike. A nullable FK may remain NULL according to its business lifecycle; deleting a referenced parent while a child still references it is blocked.
- ADR-011 remains valid for PL4 and is subsumed by this global policy; its PL4-specific Composite FK and referential-integrity decisions remain unchanged.
- Every FK must explicitly document ON DELETE RESTRICT in the Data Dictionary, physical ERD and Constraint Matrix before Migration.

## 3. Lifecycle and retention rules

### 3.1 Business records and history

- Do not hard-delete business root records or their history as part of normal application workflows.
- Use business status transitions such as CLOSED, CANCELLED, REVOKED, EXPIRED, INACTIVE or RETIRED where the relevant module supports them.
- Keep Audit, approval, assignment, execution, verification, inspection, visit and access history available for traceability.
- A parent row referenced by any retained child/history row cannot be deleted.

### 3.2 Users and actors

- User accounts referenced by business, audit, approval, assignment, export or other records must be disabled/deactivated rather than physically deleted during normal operations.
- Preserve actor snapshots and existing nullable actor-FK semantics. A nullable FK does not authorize automatic SET NULL on parent deletion.
- Any exceptional legal/privacy-driven account erasure requires a separately approved, audited anonymization/retention procedure; it must not be implemented as an ordinary cascading delete.

### 3.3 Catalogs, rooms, policies and versions

- Referenced rooms, roles, permissions, catalogs, checklist versions/items, SLA policies/calendars and other configuration parents must not be physically deleted while referenced.
- Use INACTIVE/RETIRED or create a new immutable version where applicable.
- Published/used immutable versions retain their existing immutability rules.

### 3.4 Security and operational records

- Action Tokens, OTP Challenges/Attempts, Assignment Claim Tokens, Email Queue items/Attempts, Integration Events, Idempotency Keys, Scheduler Runs and Data Exports are also subject to RESTRICT on their actual FK relationships.
- Retention cleanup is a controlled purge operation, not a cascade. It must identify eligible records, honor retention/legal holds, delete dependent rows before referenced parent rows, and record the cleanup outcome where the audit/retention design requires it.
- Examples: delete OTP Attempts before an expired OTP Challenge; Email Queue Attempts before an eligible Email Queue item; Evidence Links before an eligible Evidence File.
- A queue item or token must not be deleted while a retained business/audit requirement still depends on it.

### 3.5 Evidence

- An Evidence File referenced by one or more Entity Evidence Links cannot be deleted until the links are explicitly removed under an approved retention process.
- Evidence linked to retained business records remains protected from deletion.
- Physical object-storage deletion and database metadata deletion must be coordinated; this ADR does not define the storage purge implementation.

## 4. FK versus metadata references

This decision applies only to actual declared database foreign keys.

- `idempotency_keys.entity_type/entity_id` and `integration_events.entity_type/entity_id` remain metadata fields under ADR-012/current design; they are not polymorphic FKs.
- No ON DELETE action is applicable to a field that is not an FK.
- A missing FK discovered during detailed schema review must be documented and resolved separately; this ADR does not silently create new relationships.

## 5. ON UPDATE is not decided here

This ADR decides ON DELETE only. ON UPDATE behavior remains a separate open design item. Primary/foreign key immutability may be recommended, but must not be treated as approved by this ADR.

## 6. Consequences

**Benefits**
- Prevents accidental deletion of parent rows that still have dependent business, security, audit or operational records.
- Gives all modules one predictable referential-integrity policy.
- Makes retention cleanup explicit and ordered rather than implicit through cascade.

**Trade-offs**
- Purging requires explicit child-first procedures.
- User, Room, Catalog and configuration rows may remain physically present longer; lifecycle status is used instead.
- Retention jobs must account for FK dependencies and legal holds.

## 7. Implementation/documentation requirements before Migration

1. Set ON DELETE RESTRICT explicitly for every actual FK in CORE and PL1–PL5/COMMON.
2. Keep FK nullability independent from delete action.
3. Ensure every FK has a supporting index and the referenced key is valid.
4. Document child-first purge dependencies for each transient/retained data family.
5. Review actor/user references, Evidence links and Email Queue retention against this policy.
6. Keep ON UPDATE marked as a separate unresolved decision until approved.
7. Do not create or execute Migration/PHP under this ADR; implementation requires separate explicit authorization.

## 8. Approval

The user explicitly selected **Option A: global ON DELETE RESTRICT** for CORE, PL1, PL2, PL3, PL4, PL5 and COMMON. This ADR records that approval.

## 9. Subsequent decision – ON UPDATE

The former statement in Section 5 that ON UPDATE remained undecided was superseded by the later approved **ADR-015 – Global ON UPDATE RESTRICT and Immutable Technical Identifiers**. ADR-014 remains authoritative for ON DELETE RESTRICT. Read ADR-014 and ADR-015 together for the combined global FK policy: ON DELETE RESTRICT + ON UPDATE RESTRICT.
