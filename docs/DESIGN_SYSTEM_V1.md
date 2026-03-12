# Design System v1

This document defines the v1 token and component rules for auth surfaces.

## Tokens

Defined in `resources/css/app.css` under `:root`.

- Color tokens:
  - `--ds-color-primary`, `--ds-color-primary-strong`
  - `--ds-color-success`, `--ds-color-warning`, `--ds-color-danger`
  - `--ds-color-bg`, `--ds-color-surface`, `--ds-color-text`, `--ds-color-text-muted`, `--ds-color-border`
- Typography:
  - `--ds-font-h1`, `--ds-font-h2`, `--ds-font-h3`, `--ds-font-body`, `--ds-font-caption`
- Spacing (4/8 system):
  - `--ds-space-1` to `--ds-space-8`

## Component Rules

### Buttons

- Base class: `.ds-btn`
- Variants:
  - Primary: `.ds-btn-primary`
  - Secondary: `.ds-btn-secondary`
  - Ghost: `.ds-btn-ghost`
- States:
  - Disabled/Loading: `.ds-btn-disabled` + `disabled`

### Inputs

- Base class: `.ds-input`
- Error state: `.ds-input-error`
- Label/help text:
  - `.ds-label`
  - `.ds-help`
  - `.ds-help-error`

### Feedback

- Base alert: `.ds-alert`
- Variants:
  - Success: `.ds-alert-success`
  - Warning: `.ds-alert-warning`
  - Danger: `.ds-alert-danger`

## Auth Usage Example

Demo page is available at:

- `/auth/design-demo`

View file:

- `resources/views/auth-design-demo.blade.php`

This page includes sample layouts for:

- Login
- Register
- Reset password
