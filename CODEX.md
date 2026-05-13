# Codex Working Conditions

These project rules should be followed when Codex creates or edits files in this repository.

## Branch Workflow

- Create a new task-specific Git branch before making changes.
- Use clear branch names such as `feature-auth-api`, `fix-login-rate-limit`, or `docs-readme-update`.
- If branch creation is blocked by permissions, request approval and continue only after the branch is created.

## Editing Rules

- Inspect the current project structure before creating new files.
- Place new files in the correct existing module, controller, service, route, migration, or documentation folder.
- Do not create outdated or duplicate paths.
- Preserve the current Laravel architecture and naming conventions.
- Add comments only for important business rules, security decisions, or non-obvious logic.
- Do not remove useful comments unless they are inaccurate.

## Database Rules

- Keep string IDs where the frontend contract depends on them, such as `usr_001`.
- The canonical scholarship admin role code is `adminscholarship` (fix typo consistency across frontend/backend).
- Keep players and preschool students as data records, not system users.
- Ensure migrations run from top to bottom without foreign key errors.
- Update seeders whenever schema columns change.
- Avoid adding scaffold/demo tables that are not part of the HFCCF system.

## API Rules

- Use consistent JSON responses with `success`, `message`, and `data`.
- Protect authenticated routes with bearer-token middleware.
- Apply rate limiting to public auth endpoints and general API routes.
- Keep frontend/API mapper compatibility in mind before changing response fields.

## Verification Rules

- Run the relevant checks after changes.
- For backend route or API work, run `php artisan route:list`.
- For migration changes, run `php artisan migrate:status`.
- For tests, run `.\vendor\bin\phpunit.bat` when available.
- If a requested check is unavailable, state that clearly in the final response.

## Commit Rules

- After creating or editing files, stage only the files related to the task.
- Commit the task with a concise conventional commit message.
- Do not include unrelated dirty worktree changes in the commit.
- Do not amend commits unless explicitly requested.



run codex resume 019e1f17-b352-7302-9ea8-6e4f1f1ee4bb
