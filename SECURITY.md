# Security Policy

## Supported Versions

We release security updates for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

### How to Report

Send vulnerability reports to: **security@nextscout.com**

Include in your report:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)
- Your contact information

### What to Expect

1. **Acknowledgment**: Within 48 hours
2. **Initial Assessment**: Within 5 business days
3. **Status Updates**: Every 7 days
4. **Resolution Timeline**: Varies by severity
   - Critical: 7 days
   - High: 14 days
   - Medium: 30 days
   - Low: 90 days

### Disclosure Policy

- We follow responsible disclosure
- We will notify you before public disclosure
- We credit reporters (unless you prefer anonymity)
- Security advisories published on GitHub

## Security Measures

### Current Protections

- **Authentication**: Laravel Sanctum token-based auth
- **Input Validation**: All endpoints validate input
- **SQL Injection**: Eloquent ORM (prepared statements)
- **XSS Protection**: Input sanitization middleware
- **CSRF**: Laravel CSRF tokens
- **Rate Limiting**: 60 req/min API, 5 req/min auth
- **Password Hashing**: Bcrypt
- **HTTPS**: Required in production
- **Dependency Scanning**: GitHub Dependabot
- **Secret Scanning**: Gitleaks in CI/CD
- **Code Analysis**: PHPStan, CodeQL

### Environment Security

**Production Checklist**:
- [ ] `APP_DEBUG=false`
- [ ] Strong `APP_KEY`
- [ ] HTTPS enforced
- [ ] Secure cookie flags enabled
- [ ] CORS properly configured
- [ ] Database credentials secured
- [ ] No secrets in code/logs
- [ ] Regular dependency updates

### Known Limitations

- Push notifications not yet implemented
- WebSocket connections not yet secured
- Admin panel authorization in development

## Security Best Practices

### For Developers

1. **Never commit secrets**
   - Use `.env` for credentials
   - Add sensitive files to `.gitignore`
   - Review commits before pushing

2. **Validate all input**
   - Use Form Requests
   - Sanitize user data
   - Validate file uploads

3. **Use authorization**
   - Check permissions in controllers
   - Use Laravel Policies
   - Validate ownership

4. **Handle errors safely**
   - Don't expose stack traces in production
   - Log errors securely
   - Return generic error messages

5. **Keep dependencies updated**
   - Run `composer audit` regularly
   - Review Dependabot PRs
   - Update Laravel framework

### For Deployment

1. **Secure servers**
   - Use firewalls
   - Disable unused services
   - Keep OS updated
   - Use SSH keys

2. **Secure databases**
   - Use strong passwords
   - Restrict network access
   - Enable encryption at rest
   - Regular backups

3. **Monitor logs**
   - Check for suspicious activity
   - Set up alerts
   - Rotate logs regularly

4. **Use HTTPS**
   - Valid SSL certificate
   - Redirect HTTP to HTTPS
   - HSTS headers

## Vulnerability Types

We are particularly interested in:

- Authentication/Authorization bypass
- SQL Injection
- XSS (Cross-Site Scripting)
- CSRF (Cross-Site Request Forgery)
- Remote Code Execution
- Sensitive data exposure
- API abuse/rate limit bypass
- Payment processing vulnerabilities

## Bug Bounty

We currently do not offer a paid bug bounty program, but we do:
- Credit security researchers in our security advisories
- Offer public recognition
- Provide early access to new features

## Contact

- **Security Email**: security@nextscout.com
- **GPG Key**: Available on request
- **Response Time**: 48 hours

## Security Advisories

View past security advisories:
https://github.com/scoutprojekt0-sys/scout_api/security/advisories

---

**Last Updated**: March 8, 2026
