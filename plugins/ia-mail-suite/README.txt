IA Mail Suite 0.1.0

Admin: Settings → IA Mail Suite

What you can do now
- Change From name/email, Reply-To
- Optional SMTP configuration (host/port/encryption/user/pass)
- Edit templates for:
  - Password reset email
  - New user notification (admin/user)
  - Password/email change notifications
  - IA custom templates you can use for other flows (verify, one-off)
- Test send
- Message a user (ID/login/email)

Placeholders
{site_name} {site_url} {user_login} {user_email} {display_name} {reset_url} {verify_url} {ip} {date} {message}
Also supports [site_name] style.

Next versions (planned)
- Template “library” (create unlimited templates)
- Matcher rules UI to override any hardcoded email by subject/body signature
- IA Auth integration (menu placement + verify email override)
