# Dynamic Student Assessment & Reporting Module

## Purpose

This document defines the enterprise architecture for the dynamic student assessment module that integrates into the existing Laravel backend and Vue frontend. It is designed for:

- NGO and school assessment workflows
- Yearly form redesign without schema changes
- Dynamic scoring and risk categorization
- Printable official reports in Khmer and English
- Auditability, historical tracking, and role-based control

This is not a greenfield design. It extends the current system and preserves the existing user, student, role, permission, and preschool-domain architecture.

## Current Implementation Status

The repository already contains:

- Assessment module routes under `/api/assessment`
- Dynamic form builder tables
- Scoring tables
- Submission tables
- Print/export tables
- Frontend assessment pages and components
- RBAC middleware already used across the app

The next hardening steps are:

- Normalize the service layer to match the schema consistently
- Centralize audit/version snapshot creation
- Add queue-backed export generation
- Finalize template rendering placeholders
- Align submission scoring with reusable formula rules

## Module Boundaries

### Backend

Responsibilities:

- Persist templates, versions, sections, questions, options, submissions, scores, print templates, exports, and audit logs
- Enforce permissions and workflow transitions
- Calculate scores and risk levels
- Generate printable and exportable artifacts
- Track history and user actions

### Frontend

Responsibilities:

- Builder UI for forms and print layouts
- Wizard-based assessment entry
- Submission review and approval UI
- Dashboard, reports, and audit views
- Responsive Khmer-friendly layouts

## Database Schema

### 1. `assessment_question_types`

Purpose:

- Reference list of supported question renderers

Key fields:

- `id` PK
- `key` unique
- `label`, `label_kh`
- `renderer`
- `has_options`, `has_scoring`, `has_matrix`, `is_file`
- `settings_schema` JSON
- `is_active`, `sort_order`

Indexes:

- `key` unique
- `is_active`, `sort_order`

### 2. `assessment_form_templates`

Purpose:

- Top-level assessment form definition

Key fields:

- `id` PK
- `uuid` unique
- `code` unique
- `name`, `name_kh`
- `description`, `description_kh`
- `category`
- `module`
- `status` draft/published/archived
- `is_locked`
- `settings` JSON
- `created_by`, `updated_by`
- `deleted_at`

Foreign keys:

- `created_by -> users.id`
- `updated_by -> users.id`

Indexes:

- `module`, `status`
- `category`
- `status`

### 3. `assessment_form_versions`

Purpose:

- Immutable snapshot of a published form

Key fields:

- `id` PK
- `template_id` FK
- `version_number`
- `label`
- `snapshot` LONGTEXT or JSON-encoded payload
- `change_summary`
- `published_at`
- `published_by`
- `is_current`

Foreign keys:

- `template_id -> assessment_form_templates.id`
- `published_by -> users.id`

Indexes:

- unique `(template_id, version_number)`
- `(template_id, is_current)`

### 4. `assessment_form_sections`

Purpose:

- Logical sections inside a form template

Key fields:

- `id` PK
- `template_id` FK
- `parent_id` self-FK for nested sections
- `code`
- `title`, `title_kh`
- `description`, `description_kh`
- `sort_order`
- `is_repeatable`
- `max_repeats`
- `print_visible`
- `scoring_weight`
- `settings` JSON
- `deleted_at`

Indexes:

- `template_id`
- `parent_id`

### 5. `assessment_questions`

Purpose:

- Individual questions in a section

Key fields:

- `id` PK
- `uuid` unique
- `section_id` FK
- `template_id` FK
- `question_type_id` FK
- `parent_question_id` self-FK
- `code`
- `label`, `label_kh`
- `help_text`, `help_text_kh`
- `placeholder`, `placeholder_kh`
- `sort_order`
- `is_required`
- `is_scored`
- `max_score`
- `scoring_weight`
- `print_visible`
- `validation_rules` JSON
- `conditional_logic` JSON
- `calculation_formula`
- `settings` JSON
- `deleted_at`

Indexes:

- `(section_id, sort_order)`
- `template_id`
- `code`
- `parent_question_id`

### 6. `assessment_question_options`

Purpose:

- Option values for radio, checkbox, dropdown, rating, and rubric questions

Key fields:

- `id` PK
- `question_id` FK
- `label`, `label_kh`
- `value`
- `score_value`
- `risk_tag`
- `color_code`
- `sort_order`
- `is_other`
- `settings` JSON
- `deleted_at`

Indexes:

- `question_id`

### 7. `assessment_matrix_rows`

Purpose:

- Row definitions for matrix/grid questions

Key fields:

- `id` PK
- `question_id` FK
- `label`, `label_kh`
- `sort_order`

Indexes:

- `question_id`

### 8. `assessment_scoring_rules`

Purpose:

- Dynamic scoring formulas and scope rules

Key fields:

- `id` PK
- `template_id` FK
- `scope` template/section/question
- `scope_id`
- `rule_type` sum/weighted/percentage/formula/manual
- `formula`
- `max_score`
- `pass_score`
- `settings` JSON

Indexes:

- `(scope, scope_id)`
- `template_id`

### 9. `assessment_risk_levels`

Purpose:

- Template-specific risk bands

Key fields:

- `id` PK
- `template_id` FK
- `label`, `label_kh`
- `key`
- `min_score`
- `max_score`
- `color_code`
- `sort_order`
- `description`
- `recommendations`

Indexes:

- `(template_id, sort_order)`

### 10. `assessment_submissions`

Purpose:

- One assessment instance for one student and one form version

Key fields:

- `id` PK
- `uuid` unique
- `template_id` FK
- `version_id` FK
- `student_id` FK
- `assessor_id` FK
- `reviewer_id` FK nullable
- `approver_id` FK nullable
- `status` draft/submitted/under_review/approved/rejected/archived
- `submitted_at`
- `reviewed_at`
- `approved_at`
- `rejected_at`
- `rejection_note`
- `location_data` JSON
- `device_info` JSON
- `ip_address`
- `total_score`
- `max_score`
- `score_percent`
- `risk_level_id` FK nullable
- `risk_override`
- `risk_note`
- `meta` JSON
- `deleted_at`

Foreign keys:

- `template_id -> assessment_form_templates.id`
- `version_id -> assessment_form_versions.id`
- `student_id -> preschool_students.id`
- `assessor_id -> users.id`
- `reviewer_id -> users.id`
- `approver_id -> users.id`
- `risk_level_id -> assessment_risk_levels.id`

Indexes:

- `student_id`
- `template_id`
- `status`
- `assessor_id`
- `submitted_at`
- `(student_id, template_id)`
- `(student_id, status)`

### 11. `assessment_answers`

Purpose:

- Stored answers for each submission and question repeat instance

Key fields:

- `id` PK
- `submission_id` FK
- `question_id` FK
- `question_code`
- `repeat_index`
- `answer_text`
- `answer_date`
- `answer_number`
- `answer_options` JSON
- `answer_matrix` JSON
- `answer_file`
- `answer_gps` JSON
- `score_value`
- `is_skipped`

Indexes:

- unique `(submission_id, question_id, repeat_index)`
- `submission_id`
- `question_id`

### 12. `assessment_submission_scores`

Purpose:

- Derived scores at section and template level

Key fields:

- `id` PK
- `submission_id` FK
- `scope` section/template
- `scope_id`
- `raw_score`
- `max_score`
- `weighted_score`
- `percentage`
- `risk_level_id` FK nullable
- `override_score`
- `override_by` FK nullable
- `override_note`

Indexes:

- `(submission_id, scope)`

### 13. `assessment_submission_history`

Purpose:

- Immutable workflow trail

Key fields:

- `id` PK
- `submission_id` FK
- `from_status`
- `to_status`
- `changed_by` FK
- `note`
- `created_at`

Indexes:

- `submission_id`

### 14. `assessment_attachments`

Purpose:

- File uploads and images linked to submissions/questions

Key fields:

- `id` PK
- `submission_id` FK
- `question_id` FK nullable
- `file_name`
- `file_path`
- `file_type`
- `file_size`
- `disk`
- `uploaded_by` FK
- `deleted_at`

Indexes:

- `submission_id`
- `question_id`

### 15. `assessment_print_templates`

Purpose:

- Dynamic printable layout definitions

Key fields:

- `id` PK
- `uuid` unique
- `form_template_id` FK
- `name`, `name_kh`
- `format` pdf/excel/html
- `page_size`
- `orientation`
- `margin_top`, `margin_right`, `margin_bottom`, `margin_left`
- `font_family`
- `font_size`
- `header_html`
- `footer_html`
- `watermark_text`
- `show_logo`
- `logo_path`
- `show_qr_code`
- `show_watermark`
- `blocks` LONGTEXT or JSON string
- `styles`
- `is_default`
- `status`
- `created_by`, `updated_by`
- `deleted_at`

### 16. `assessment_export_logs`

Purpose:

- Track generated files, batch exports, and expiry

Key fields:

- `id` PK
- `uuid` unique
- `initiated_by` FK
- `export_type` pdf/excel/zip/html
- `scope` single/batch/report
- `submission_ids` JSON
- `print_template_id` FK nullable
- `status` queued/processing/completed/failed
- `file_path`
- `file_size`
- `error_message`
- `started_at`
- `completed_at`
- `expires_at`
- `meta` JSON

Indexes:

- `status`
- `initiated_by`
- `expires_at`

### 17. `assessment_audit_logs`

Purpose:

- Immutable activity log for all form/submission/export operations

Key fields:

- `id` PK
- `user_id` FK
- `action`
- `entity_type`
- `entity_id`
- `entity_label`
- `old_value` LONGTEXT
- `new_value` LONGTEXT
- `ip_address`
- `user_agent`
- `meta` JSON
- `created_at`

Indexes:

- `(entity_type, entity_id)`
- `user_id`
- `action`
- `created_at`

## Relationships

High-level relationships:

- One `assessment_form_template` has many `assessment_form_versions`
- One `assessment_form_template` has many `assessment_form_sections`
- One `assessment_form_section` has many `assessment_questions`
- One `assessment_question` has many `assessment_question_options`
- One `assessment_question` has many `assessment_matrix_rows`
- One `assessment_form_template` has many `assessment_scoring_rules`
- One `assessment_form_template` has many `assessment_risk_levels`
- One `assessment_form_template` has many `assessment_print_templates`
- One `assessment_form_template` has many `assessment_submissions`
- One `assessment_submission` has many `assessment_answers`
- One `assessment_submission` has many `assessment_submission_scores`
- One `assessment_submission` has many `assessment_submission_history` rows
- One `assessment_submission` has many `assessment_attachments`
- One `assessment_submission` belongs to one `assessment_risk_level`
- One `assessment_export_log` may reference one print template
- One `assessment_audit_log` belongs to one user and one tracked entity

## Versioning Strategy

Versioning is snapshot-based:

1. Draft form is edited in mutable tables.
2. Publish action creates an immutable record in `assessment_form_versions`.
3. Snapshot stores full sections, questions, options, and scoring state.
4. Submitted assessments bind to a specific `version_id`.
5. Later form edits create a new version instead of mutating the published snapshot.

Rules:

- Published versions are never edited in place
- Archived templates remain queryable for historical submissions
- New year redesigns are implemented by duplicating a template and publishing a new version

## Audit Strategy

Audit logging should capture:

- Template create/update/publish/archive/duplicate actions
- Section/question/option reorder changes
- Scoring rule changes
- Submission create/update/submit/review/approve/reject actions
- Print template create/update/publish actions
- Export generation and download actions

Recommended audit payload:

- `user_id`
- `action`
- `entity_type`
- `entity_id`
- `entity_label`
- `old_value`
- `new_value`
- `ip_address`
- `user_agent`
- `meta`

Guidelines:

- Write audit records inside the same transaction as the domain change
- Store immutable snapshots, not references to mutable live objects
- Do not overwrite audit entries

## API Architecture

### REST resource groups

- `/api/assessment/question-types`
- `/api/assessment/forms`
- `/api/assessment/forms/{id}/sections`
- `/api/assessment/forms/{id}/questions`
- `/api/assessment/forms/{id}/scoring`
- `/api/assessment/print-templates`
- `/api/assessment/submissions`
- `/api/assessment/reports/*`
- `/api/assessment/audit-logs`

### Required middleware

- `auth:sanctum`
- `throttle:api`
- `permission:*`
- `guardian.portal` for guardian-only routes

### Validation strategy

- Validate top-level request structure in form requests
- Validate nested question/answer payloads with array rules
- Validate file uploads by mime, size, dimensions, and disk target
- Reject unknown question types or unsupported renderer combinations

### Response shape

Recommended standard:

```json
{
  "success": true,
  "message": "Operation completed.",
  "data": {},
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 100,
    "totalPages": 5
  }
}
```

### Pagination strategy

- Use cursor pagination only for high-volume event logs if needed
- Use standard page/per-page pagination for templates, submissions, and reports
- Keep a consistent meta block across endpoints

### Filtering/search strategy

- `search`
- `module`
- `status`
- `student_id`
- `form_template_id`
- `date_from`
- `date_to`
- `risk_level_id`
- `assigned_to`
- `sort_by`
- `sort_direction`

## Backend Folder Structure

Recommended module layout:

```text
app/
  Http/
    Controllers/Api/Assessment/
    Requests/Assessment/
    Resources/Assessment/
    Middleware/
  Models/
  Services/
    Assessment/
      AssessmentFormService.php
      AssessmentScoringService.php
      AssessmentSubmissionService.php
      AssessmentPrintService.php
      AssessmentExportService.php
      AssessmentAuditService.php
  Jobs/
    Assessment/
  Events/
    Assessment/
  Listeners/
    Assessment/
  Support/
    Assessment/
database/
  migrations/
  seeders/
tests/
  Feature/Assessment/
  Unit/Assessment/
```

## Frontend Folder Structure

Recommended module layout:

```text
src/modules/assessment/
  components/
    form-builder/
    questions/
    wizard/
    scoring/
    print-designer/
    reports/
    shared/
  composables/
  pages/
  routes/
  services/
  stores/
  types/
  utils/
```

## UX / UI Recommendations

### Global principles

- Keep the UI minimal and operational
- Make Khmer labels first-class, not decorative
- Favor dense-but-readable tables for staff workflows
- Use stepper/wizard patterns for submissions
- Use fixed sidebars only on desktop; collapse on tablet/mobile

### Form Builder layout

Left sidebar:

- Sections tree
- Question library
- Drag/drop controls

Center canvas:

- Live form preview
- Section/question ordering
- Conditional visibility preview

Right sidebar:

- Question settings
- Validation rules
- Scoring settings
- Conditional logic
- Print visibility

### Print Template Designer layout

- Top bar: template metadata and publish controls
- Left panel: printable blocks library
- Center canvas: page preview
- Right panel: block properties and placeholder insertion
- Bottom tabs: PDF preview, Excel preview, HTML preview

## Form Builder Workflow

1. Create template draft
2. Add sections
3. Add questions from library
4. Configure options, scoring, validation, and visibility
5. Reorder sections and questions
6. Preview live form
7. Save draft
8. Publish version
9. Archive prior versions if required

## Assessment Workflow

1. Select student
2. Select published form version
3. Fill answers
4. Auto-save draft
5. Resume later
6. Review answers
7. Submit
8. Supervisor review
9. Approve or reject
10. Export final report

## Scoring Engine Design

Scoring should support:

- Option-level score values
- Section weights
- Template totals
- Formula-based computation
- Percentage conversion
- Manual override
- Risk band mapping

Recommended calculation order:

1. Evaluate question-level values
2. Sum section raw scores
3. Apply section weights
4. Compute template total and max
5. Compute percentage
6. Resolve risk level
7. Persist submission score breakdown

Pseudo-structure:

```json
{
  "template_total": 87,
  "template_max": 120,
  "percentage": 72.5,
  "risk_level": "medium",
  "sections": [
    {
      "section_id": 1,
      "raw_score": 22,
      "weighted_score": 24.2,
      "risk_level": "low"
    }
  ]
}
```

## Print / PDF / Excel Engine

### Rendering strategy

- Store layout as JSON blocks, not hardcoded HTML
- Use placeholders like `{{student_name}}`, `{{total_score}}`, `{{risk_level}}`
- Resolve placeholders at render time from submission + student + school + template context

### Output targets

- PDF for official reports
- XLSX for bulk export and statistical analysis
- Print-friendly HTML for browser preview

### Khmer Unicode handling

- Use Khmer-capable fonts in PDF generation
- Normalize line-height and fallback fonts
- Test pagination with Khmer text and mixed English/Khmer content

### Export pipeline

- Create export log row with `queued`
- Dispatch queued job
- Generate file in background
- Store file path in object storage
- Mark log as completed or failed
- Set expiration for downloadable artifacts

## Sample JSON Structures

### Form template snapshot

```json
{
  "id": 12,
  "code": "child-wellbeing-2026",
  "name": "Child Wellbeing Assessment 2026",
  "status": "published",
  "module": "preschool",
  "version": 3,
  "sections": [
    {
      "id": 101,
      "title": "Family Background",
      "sort_order": 1,
      "questions": [
        {
          "id": 1001,
          "type": "dropdown",
          "label": "Guardian occupation",
          "print_visible": true,
          "options": [
            { "value": "farmer", "label": "Farmer", "score_value": 2 },
            { "value": "other", "label": "Other", "score_value": 0 }
          ]
        }
      ]
    }
  ]
}
```

### Submission payload

```json
{
  "form_template_id": 12,
  "version_id": 3,
  "student_id": 501,
  "answers": [
    {
      "question_id": 1001,
      "repeat_index": 0,
      "answer_options": ["farmer"]
    }
  ],
  "meta": {
    "device": "tablet",
    "locale": "kh",
    "autosaved": true
  }
}
```

### Print template blocks

```json
{
  "page": {
    "size": "A4",
    "orientation": "portrait",
    "margins": { "top": 20, "right": 20, "bottom": 20, "left": 20 }
  },
  "blocks": [
    { "type": "header", "content": "{{organization_name}}" },
    { "type": "title", "content": "Student Assessment Report" },
    { "type": "table", "source": "sections" },
    { "type": "score-summary", "source": "scores" },
    { "type": "signatures", "fields": ["prepared_by", "reviewed_by", "approved_by"] }
  ]
}
```

## Dashboard Module Ideas

- Total students assessed
- Total submissions
- Draft vs submitted vs approved
- Risk level distribution
- Gender distribution
- Province distribution
- School comparison
- Year-over-year comparisons
- Section scoring heatmap
- Export history trend
- Reviewer workload

## Security Strategy

- Use permission middleware for all mutating endpoints
- Enforce strict file upload validation
- Sanitize renderable HTML blocks and placeholder content
- Escape text in printable templates unless explicitly marked safe
- Rate limit export and submission endpoints
- Keep export files behind authenticated download routes
- Log all review and approval actions

## Performance Strategy

- Paginate all list endpoints
- Cache question type reference data
- Queue export generation
- Avoid reloading whole templates unless necessary
- Use indexed filters for submissions and audit logs
- Precompute score breakdowns on submit/review
- Separate live draft autosave from publish and finalize actions

## Enterprise Best Practices

- Never mutate published versions in place
- Keep template snapshots immutable
- Store historical data as append-only where possible
- Use one canonical JSON schema for form snapshots
- Keep frontend component contracts stable
- Centralize authorization rules
- Use queued jobs for slow rendering/export tasks
- Validate every nested payload and every file upload

## Recommended Next Implementation Steps

1. Normalize service and model names so controller, migration, and payload fields match exactly.
2. Introduce a dedicated assessment audit service and call it from all mutating flows.
3. Add queued export generation for PDF/XLSX/ZIP.
4. Finalize placeholder rendering for print templates.
5. Add full-featured form builder tests for publish, duplicate, reorder, and version snapshot integrity.
6. Add submission autosave and resume behavior on the frontend wizard.
7. Wire role/permission checks to the existing RBAC middleware instead of local role checks where possible.
