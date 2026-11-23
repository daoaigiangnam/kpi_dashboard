# ADR-015 – Global ON UPDATE RESTRICT and Immutable Technical Identifiers

**Status:** APPROVED  
**Version:** 1.0  
**Date:** 23/11/2026  
**Branch:** feature/ITSMWEB  
**Scope:** CORE, PL1, PL2, PL3, PL4, PL5 and COMMON  
**Related:** ADR-001, ADR-011, ADR-014  
**Boundary:** Design decision only. No DDL, Migration, PHP or database changes.

## 1. Context

ADR-014 approved ON DELETE RESTRICT for every actual FK in CORE, PL1–PL5 and COMMON, while leaving ON UPDATE as a separate decision. The user has explicitly selected Option A: global ON UPDATE RESTRICT.

The model uses technical primary keys, foreign keys, composite foreign keys, immutable history, Audit records, Business Entity references and snapshots. Changing a referenced technical identifier after creation would create unnecessary risk to referential integrity and historical traceability.

## 2. Decision

Apply **ON UPDATE RESTRICT** to every actual foreign-key relationship in CORE, PL1, PL2, PL3, PL4, PL5 and COMMON.

- No FK may use ON UPDATE CASCADE.
- No FK may use ON UPDATE SET NULL.
- No FK may rely on an implicit engine default as a substitute for documenting the policy.
- A referenced parent key cannot be updated while dependent child rows reference it.
- Technical primary identifiers (including IDs used as FK targets) are immutable after record creation.
- Foreign-key values may be set during the original insert or through a valid business operation where the relationship is nullable/optional and the schema permits it. This does not authorize changing a referenced parent's primary identifier.
- Composite FK target columns and their parent UNIQUE keys are subject to the same immutability rule.
- This policy applies only to actual declared FKs. Metadata pairs such as entity_type/entity_id in idempotency_keys and integration_events remain non-FK metadata under the current design.

## 3. Business data correction versus technical identity

- Correcting a business attribute (name, email, description, location, status, label or other mutable business field) is allowed under the relevant module's authorization, validation and Audit rules.
- Correcting a technical ID is not an ordinary business edit.
- If a relationship was assigned incorrectly, use an explicit, authorized correction/relink workflow that validates lifecycle and permissions, writes Audit evidence, and preserves prior history. Do not solve it by changing a parent PK or enabling cascading updates.
- If a natural/business code must change, keep the technical PK stable; record the code change using the relevant business history/snapshot mechanism.
- Existing snapshots remain historical facts and are not rewritten merely because a current name/email/catalog label changes.

## 4. Scope and exceptions

- The rule applies to PK values referenced by FKs, including Business Entity IDs, root record IDs, room/user/catalog/version IDs, PL4 composite-key targets and PL5 composite-key targets.
- It does not prohibit updates to non-key business columns.
- It does not define a new correction workflow or authorize direct database edits.
- Exceptional key migration, data repair or legacy import must be treated as a separately planned and audited maintenance operation with explicit approval; it must not be implemented as normal application behavior and must not silently bypass this policy.

## 5. Consequences

**Benefits**
- Stable technical identity across the full lifecycle of business records.
- Lower risk to Audit, Evidence, SLA, history, composite FK and cross-module relationships.
- Clear distinction between mutable business attributes and immutable technical identity.

**Trade-offs**
- Incorrectly assigned IDs/relationships require an explicit correction process rather than a cascading key update.
- Import and data-repair procedures must preserve or deliberately map identifiers before dependent records are created.

## 6. Documentation and implementation requirements before Migration

1. Document ON UPDATE RESTRICT explicitly for every actual FK in CORE, PL1–PL5 and COMMON.
2. Ensure each FK has explicit nullability, referenced key, supporting index, ON DELETE RESTRICT per ADR-014 and ON UPDATE RESTRICT per this ADR.
3. Preserve parent UNIQUE keys and column order required by Composite FKs.
4. Keep technical PKs stable; do not expose mutable business codes as FK targets unless separately approved.
5. Review all correction/relink use cases so they do not require changing a referenced PK.
6. Update ADR-014's open ON UPDATE statement by reference to this later decision; ADR-014 remains authoritative for ON DELETE.
7. Do not create or execute DDL/Migration/PHP under this ADR; implementation requires separate explicit authorization.

## 7. Approval

The user explicitly selected **Option A: global ON UPDATE RESTRICT and immutable technical identifiers** for CORE, PL1, PL2, PL3, PL4, PL5 and COMMON. This ADR records that approval.
