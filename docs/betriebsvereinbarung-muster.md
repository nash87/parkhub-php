# Betriebsvereinbarung: Parkplatzmanagementsystem ParkHub

**Muster — rechtliche Prüfung erforderlich**
Dieses Dokument ist ein Muster und ersetzt keine rechtliche Beratung.
Vor Unterzeichnung ist eine Prüfung durch einen Fachanwalt für Arbeitsrecht erforderlich.

---

## DE: Betriebsvereinbarung

zwischen

**[Arbeitgeber]** (nachfolgend: Arbeitgeber)

und

**[Betriebsrat]** (nachfolgend: Betriebsrat)

wird folgende Betriebsvereinbarung gemäß § 87 Abs. 1 Nr. 6 BetrVG geschlossen:

---

### § 1 Gegenstand

Diese Betriebsvereinbarung regelt den Einsatz des digitalen Parkplatzmanagementsystems ParkHub
(nachfolgend: System) im Betrieb des Arbeitgebers, einschließlich der damit verbundenen
Datenerhebung, -verarbeitung und algorithmischen Stellplatzzuweisung.

---

### § 2 Erhobene Daten und Aufbewahrungsfristen

Das System verarbeitet ausschließlich die nachfolgend aufgeführten Datenkategorien.
Die maximalen Aufbewahrungsfristen dürfen ohne Zustimmung des Betriebsrats nicht verlängert werden.

| Datenkategorie | Zweck | Standardfrist | Gesetzliches Minimum |
|---|---|---|---|
| `operational_presence` — Anwesenheitsdaten | Betriebliche Parkplatzverwaltung | 30 Tage | — |
| `booking_history` — Buchungshistorie | Rechnungsstellung und Nutzerservice | 90 Tage | — |
| `security_audit_log` — Sicherheitsprotokoll | Systemintegrität und Datenschutz-Compliance | 180 Tage | — |
| `hr_labour` — Arbeitsrechtliche Daten | Nachweis gemäß BGB § 195 | 1095 Tage (3 Jahre) | 1095 Tage (gesetzlich) |
| `anpr_raw` — ANPR-Rohdaten (Kennzeichen) | Automatische Einfahrtssteuerung | 3 Tage | — |
| `ev_session` — Ladesitzungsdaten | Abrechnung und Betrieb | 30 Tage | — |
| `billing_fiscal` — Rechnungs- und Zahlungsdaten | Steuerlicher Nachweis gemäß HGB § 257 (GoBD) | 2922 Tage (8 Jahre) | 2922 Tage (gesetzlich) |

Die aktuell gültigen Fristen sind jederzeit maschinell abrufbar unter:
`GET /api/v1/admin/transparency/data-collection` (Administratorzugang erforderlich).

---

### § 3 Verbot der verdeckten Überwachung

Eine verdeckte oder anlasslose Überwachung des Verhaltens oder der Leistung von Arbeitnehmern
durch das System ist ausgeschlossen.

Das System ist technisch so konfiguriert, dass:

1. keine individuelle Verhaltens- oder Leistungsauswertung stattfindet,
2. Standortdaten (ANPR) ausschließlich zur Zugangssteuerung, nicht zur Bewegungsprotokollierung verwendet werden,
3. keine Echtzeitüberwachung einzelner Personen erfolgt.

Der technische Parameter `no_covert_monitoring: true` in der maschinellen Offenlegung
(`GET /api/v1/admin/transparency/data-collection`) bestätigt diese Konfiguration dauerhaft.

---

### § 4 Kein Leistungs- oder Verhaltensmonitoring

Die durch das System erhobenen Daten werden nicht verwendet, um die individuelle Leistung
oder das individuelle Verhalten von Arbeitnehmern zu bewerten, zu kontrollieren oder zu beurteilen.

Aggregierte, k-anonymisierte Fairness-Berichte (§ 5) sind hiervon ausgenommen, sofern keine
Rückschlüsse auf Einzelpersonen möglich sind.

---

### § 5 Fairness-Bericht und Mitbestimmungsrecht

Der Betriebsrat erhält Zugang zum Fairness-Bericht des Systems über:
`GET /api/v1/admin/fairness/report?from=YYYY-MM-DD&to=YYYY-MM-DD`

Der Bericht enthält ausschließlich aggregierte Kennzahlen:

- Gesamtzahl der algorithmischen Stellplatzzuweisungen im gewählten Zeitraum
- Verteilung der Zuteilungshäufigkeit nach Nutzersegmenten (Buckets: 0 / 1–2 / 3–5 / 6+)
- Ablehnungsgründe nach Kategorie
- Verhältnis Buchungen zu Zuteilungen
- Gini-Koeffizient als Ungleichheitsmaß über die Zuteilungsverteilung

**k-Anonymität:** Jedes Bucket mit weniger als 5 Nutzern wird automatisch in die
Sammelkategorie „other (<5)" überführt. Individuelle Nutzerdaten werden nie zurückgegeben.

**Formel Gini-Koeffizient** (dokumentiert im Quellcode):
Sortiere Zuteilungszähler x₁ ≤ x₂ ≤ … ≤ xₙ aufsteigend, vergib Rang i = 1…n.
G = (2 · Σ(i · xᵢ) − (n+1) · Σ(xᵢ)) / (n · Σ(xᵢ))
Ergibt 0,0 bei vollständiger Gleichverteilung, (n-1)/n bei totaler Konzentration.

Der Betriebsrat kann diesen Bericht vierteljährlich anfordern und bei Abweichungen
von der vereinbarten Fairness-Zielvorgabe (Gini ≤ [individuell zu vereinbaren])
eine Anpassung der Zuteilungsparameter verlangen.

---

### § 6 Algorithmische Zuteilung — Transparenz

Das System nutzt zwei Zuteilungsverfahren:

- **Gewichtetes Scoring** (`RecommendationServed`): Empfehlungsbasierte Zuteilung mit
  konfigurierbaren Gewichtungsparametern.
- **Exact-Cover-Solver** (`ExactCoverAllocationServed`): Kombinatorische Optimierung
  zur konfliktfreien Stellplatzzuweisung.

Der aktuelle Betriebsmodus ist abrufbar unter:
`GET /api/v1/admin/aiact/transparency-mode`

Sofern die EU-KI-Verordnung Art. 50 (ab 2026-08-02) Anwendung findet, trägt jede
algorithmische Entscheidungsantwort einen `automated_decision`-Hinweis.

Der Betriebsmodus kann auf `fifo_only` (deterministische FIFO-Warteschlange, kein
Algorithmus) umgestellt werden, wenn der Betriebsrat dies zur Wahrung der Mitbestimmungsrechte
für erforderlich hält.

---

### § 7 Änderungen und Erweiterungen

Wesentliche Änderungen am System, insbesondere:

- Einführung neuer Datenkategorien,
- Verlängerung von Aufbewahrungsfristen über die in § 2 genannten Werte,
- Einführung neuer algorithmischer Verfahren zur Zuteilung oder Auswertung,

bedürfen der vorherigen Zustimmung des Betriebsrats gemäß § 87 Abs. 1 Nr. 6 BetrVG.

---

### § 8 Inkrafttreten und Kündigung

Diese Betriebsvereinbarung tritt mit der Unterzeichnung beider Parteien in Kraft.
Sie kann mit einer Frist von drei Monaten zum Monatsende gekündigt werden.

---

**[Ort, Datum]**

\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_  
[Arbeitgeber]

\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_  
[Betriebsrat]

---
---

## EN: Works Agreement (Betriebsvereinbarung) — Template

**Template — legal review required**
This document is a template and does not constitute legal advice.
Review by a specialist employment lawyer is required before signing.

---

This Works Agreement is entered into pursuant to § 87 para. 1 no. 6 BetrVG (Works
Constitution Act) between:

**[Employer]** (hereinafter: Employer)

and

**[Works Council / Betriebsrat]** (hereinafter: Works Council)

---

### Section 1: Subject Matter

This Works Agreement governs the deployment of the ParkHub digital parking management
system (hereinafter: System) and covers data collection, processing, and algorithmic
parking allocation within the establishment.

---

### Section 2: Data Categories and Retention Periods

The System processes exclusively the data categories listed below. Maximum retention
periods may not be extended without the consent of the Works Council.

| Data Category | Purpose | Default Period | Statutory Minimum |
|---|---|---|---|
| `operational_presence` | Operational parking lot management | 30 days | — |
| `booking_history` | Billing and user service | 90 days | — |
| `security_audit_log` | System integrity and compliance | 180 days | — |
| `hr_labour` | Statutory records per BGB § 195 | 1095 days (3 years) | 1095 days (statutory) |
| `anpr_raw` | Automated number plate recognition | 3 days | — |
| `ev_session` | EV charging billing and operation | 30 days | — |
| `billing_fiscal` | Fiscal records per HGB § 257 (GoBD) | 2922 days (8 years) | 2922 days (statutory) |

Current effective periods are machine-readable at:
`GET /api/v1/admin/transparency/data-collection` (admin access required).

---

### Section 3: Prohibition of Covert Monitoring

Covert or unsolicited monitoring of employee behaviour or performance by the System
is excluded.

The System is technically configured such that:

1. no individual behaviour or performance evaluation takes place,
2. location data (ANPR) is used solely for access control, not for movement profiling,
3. no real-time surveillance of individuals occurs.

The technical flag `no_covert_monitoring: true` in the machine-readable disclosure
(`GET /api/v1/admin/transparency/data-collection`) permanently attests this configuration.

---

### Section 4: No Performance or Behaviour Monitoring

Data collected by the System shall not be used to evaluate, monitor, or assess the
individual performance or behaviour of employees.

Aggregated, k-anonymised fairness reports (Section 5) are exempt, provided no inference
about individuals is possible.

---

### Section 5: Fairness Report and Co-Determination Right

The Works Council has access to the System's fairness report via:
`GET /api/v1/admin/fairness/report?from=YYYY-MM-DD&to=YYYY-MM-DD`

The report contains only aggregate metrics:

- Total algorithmic allocations in the selected period
- Distribution of allocation frequency across user segments (buckets: 0 / 1–2 / 3–5 / 6+)
- Denial reasons by category
- Booking-to-allocation ratio
- Gini coefficient as an inequality measure over the allocation distribution

**k-Anonymity:** Any bucket with fewer than 5 users is automatically folded into the
catch-all category "other (<5)". Individual user data is never returned.

**Gini coefficient formula** (documented in source code):
Sort allocation counts x₁ ≤ x₂ ≤ … ≤ xₙ ascending, assign rank i = 1…n.
G = (2 · Σ(i · xᵢ) − (n+1) · Σ(xᵢ)) / (n · Σ(xᵢ))
Returns 0.0 for perfect equality, (n-1)/n for total concentration.

The Works Council may request this report quarterly and may request an adjustment of
allocation parameters if the agreed fairness target (Gini ≤ [to be agreed]) is exceeded.

---

### Section 6: Algorithmic Allocation — Transparency

The System uses two allocation methods:

- **Weighted scoring** (`RecommendationServed`): Recommendation-based allocation with
  configurable weighting parameters.
- **Exact-Cover Solver** (`ExactCoverAllocationServed`): Combinatorial optimisation for
  conflict-free parking assignment.

The current operating mode is accessible at:
`GET /api/v1/admin/aiact/transparency-mode`

Where EU AI Act Article 50 (applicable from 2026-08-02) applies, each algorithmic
decision response carries an `automated_decision` transparency notice.

The operating mode may be switched to `fifo_only` (deterministic FIFO queue, no
algorithm) if the Works Council considers this necessary to safeguard co-determination rights.

---

### Section 7: Changes and Extensions

Material changes to the System — in particular:

- introduction of new data categories,
- extension of retention periods beyond those specified in Section 2,
- introduction of new algorithmic methods for allocation or evaluation,

require prior consent from the Works Council pursuant to § 87 para. 1 no. 6 BetrVG.

---

### Section 8: Entry into Force and Termination

This Works Agreement enters into force upon signature by both parties.
It may be terminated with three months' notice to the end of a calendar month.

---

**[Place, Date]**

\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_  
[Employer]

\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_  
[Works Council]
