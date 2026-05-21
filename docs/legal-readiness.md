# Operator Legal Readiness Hub

ParkHub PHP ships technical controls, templates, API surfaces, and release checks
that can support German, EU, and international legal-readiness work. This hub
organizes the evidence an operator should review before launch. It is not legal
advice and is not a legal conclusion for any specific deployment.

Use this page as the audit index. The operator remains responsible for the live
service, enabled modules, processors, regions, legal texts, citations, retention
settings, launch approvals, and final human signoff.

## Required External Review

Before production use, the operator should have qualified counsel review:

- final legal texts and citations;
- deployment-specific configuration, hosting regions, and processor contracts;
- enabled modules, third-party integrations, and AI/ML features;
- retention, deletion, export, audit-log, and backup settings;
- accessibility, consumer, and sector-specific obligations for the actual
  business model and jurisdiction.

`fop legal catalog` can be used as a reference-only catalog of obligations and
internal evidence pointers. It is not legal advice, does not verify citations,
and does not replace attorney review, citation verification, human signoff,
deployment-specific configuration review, or final legal judgment.

## Evidence Map

| Operator question | Primary evidence |
| --- | --- |
| What personal-data flows and legal bases are documented? | [GDPR guide](GDPR.md), [Compliance Matrix](COMPLIANCE.md), [VVT template](../legal/vvt-template.md) |
| What public legal texts are available as starting points? | [Privacy template](PRIVACY-TEMPLATE.md), [Impressum template](IMPRESSUM-TEMPLATE.md), [legal templates](../legal/) |
| What admin compliance APIs can be audited? | [API docs](API.md), `GET /v1/admin/compliance/report`, `GET /v1/admin/compliance/data-map`, `GET /v1/admin/compliance/audit-export` |
| What release checks protect the legal posture? | [Release checklist](release-checklist.md), `scripts/tests/test-legal-readiness-wording.sh`, `scripts/tests/test-legal-openapi-contract.sh` |
| What security controls should be reviewed with privacy obligations? | [Security model](SECURITY.md), audit log export, module review, processor list |
| What per-deployment record captures launch signoff? | [Deployment readiness record](deployment-readiness-record.md) for jurisdiction, business context, enabled modules, processors, CI/CD evidence, legal review, and final human go-live decision |
| How do Rust and PHP stay aligned? | [Legal readiness parity](legal-readiness-parity.md) compares hubs, release gates, module/plugin review policy, and operator boundaries across both runtimes |

## German Readiness Review

- **Provider identification:** adapt and publish the Impressum; verify the
  public route and API route after deployment.
- **Privacy notice:** adapt the Datenschutz/privacy template to the actual
  controller, processing purposes, recipients, retention periods, transfers, and
  data-subject contact paths.
- **Processor agreements:** review AVV/DPA status for hosting, SMTP, payment,
  backups, support, analytics, AI, and any integration with system or data
  access.
- **Records of processing:** maintain the VVT/Record of Processing Activities
  for the actual deployment.
- **TTDSG/local storage:** re-check cookie and localStorage analysis whenever
  optional modules introduce non-essential storage or tracking.
- **Retention:** align booking, payment, audit-log, backup, session, and upload
  retention with the operator's obligations and documented policy.
- **BDSG/DSB:** evaluate DSB appointment duties for the operator's staff count,
  monitoring profile, and business context.
- **BFSG/EAA:** review accessibility scope for consumer-facing deployments and
  keep the accessibility statement current.

## EU And International Review

- **GDPR / UK GDPR / nDSG / LGPD:** confirm data-subject rights, export,
  deletion/anonymization, breach response, international transfers, and
  controller contact details for the actual deployment.
- **CCPA / CPRA:** adapt the privacy notice for California-specific
  disclosures where the operator is in scope.
- **NIS2:** complete a scope assessment for operators in essential or important
  entity categories.
- **EU AI Act:** add transparency notices and human-review procedures if AI/ML
  features, profiling, or third-party AI processors are enabled.
- **Cross-border processors:** document transfer mechanisms, regions, and
  sub-processor evidence for every configured external provider.

## Module And Plugin Review

Security-sensitive or legally sensitive modules should remain disabled until an
operator records:

- purpose and legal basis for the module;
- personal-data categories, recipients, retention, and transfer impact;
- AVV/DPA and sub-processor status;
- privacy notice and template updates;
- audit-log coverage and export path;
- rollback plan and launch owner;
- counsel review status;
- final human signoff status.

This applies especially to authentication, payments, RBAC, webhooks,
audit-export, multi-tenant mode, notifications, AI/ML features, analytics,
third-party integrations, and any plugin that changes data recipients,
retention, public exposure, billing, or automated decisions.

## Release Review Flow

Before tagging or deploying a release that touches legal, privacy, module,
plugin, export, erasure, or audit surfaces:

1. Update the affected legal templates, GDPR guide, Compliance Matrix, and API
   docs.
2. Run `scripts/tests/test-legal-readiness-wording.sh`.
3. Run `scripts/tests/test-legal-openapi-contract.sh`.
4. Complete or update [deployment-readiness-record.md](deployment-readiness-record.md)
   before production use, business use, or customer-facing evaluation.
5. Review [legal-readiness-parity.md](legal-readiness-parity.md) when a change
   should stay aligned across Rust and PHP.
6. Review the legal-readiness section in [release-checklist.md](release-checklist.md).
7. Record unresolved deployment decisions in the release notes or operator
   handoff.

## Wording Guardrails

Use readiness, support, obligation, and review language. Do not present ParkHub
or a live deployment as having a final legal status. Operator-facing copy should
state that legal texts, citations, configuration, providers, and launch process
still require qualified review and human signoff for the specific deployment.
