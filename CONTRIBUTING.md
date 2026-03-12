# Contributing to Scout API

Thank you for considering contributing to Scout API! This document outlines the process and guidelines.

## Getting Started

1. **Fork the repository**
2. **Clone your fork**:
   ```bash
   git clone https://github.com/YOUR_USERNAME/scout_api.git
   cd scout_api
   ```
3. **Install dependencies**:
   ```bash
   composer install
   npm install
   ```
4. **Create a branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```

## Development Workflow

### Local Setup

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

### Code Style

We use Laravel Pint for code formatting:

```bash
./vendor/bin/pint
```

Before committing, ensure your code passes:

```bash
./vendor/bin/pint --test
```

### Testing

Run the test suite:

```bash
php artisan test
```

Add tests for new features:
- Feature tests: `tests/Feature/`
- Unit tests: `tests/Unit/`

### Static Analysis

We use PHPStan for static analysis:

```bash
composer analyse
```

## Pull Request Process

1. **Update documentation** if you change APIs or add features
2. **Run all quality checks**:
   ```bash
   ./vendor/bin/pint --test
   composer test
   composer analyse
   ```
3. **Write clear commit messages**:
   - Use present tense: "Add feature" not "Added feature"
   - Reference issues: "Fix #123: Add validation"
   - Follow format: `type: description`
     - `feat:` New feature
     - `fix:` Bug fix
     - `docs:` Documentation
     - `test:` Tests
     - `refactor:` Code refactoring
     - `chore:` Maintenance

4. **Create Pull Request**:
   - Use clear title and description
   - Link related issues
   - Add screenshots for UI changes
   - Request review from maintainers

## Code Review Guidelines

### As a Contributor
- Be open to feedback
- Respond to comments promptly
- Make requested changes
- Keep PR scope focused

### As a Reviewer
- Be respectful and constructive
- Explain the "why" behind suggestions
- Approve when requirements are met

## Coding Standards

### PHP
- Follow PSR-12 coding standards
- Use type hints
- Write PHPDoc comments
- Keep methods small and focused

### Database
- Use migrations for schema changes
- Add indexes for frequently queried fields
- Use foreign keys for relationships
- Seed demo data for testing

### API Design
- Follow RESTful conventions
- Use proper HTTP status codes
- Return consistent JSON responses
- Document all endpoints

### Security
- Never commit `.env` files
- Never commit secrets or API keys
- Validate all user input
- Use prepared statements (Eloquent does this)
- Implement proper authorization

## Issue Reporting

### Bug Reports

Include:
- Clear description
- Steps to reproduce
- Expected vs actual behavior
- Environment details (PHP version, OS, etc.)
- Error messages/logs
- Screenshots if applicable

### Feature Requests

Include:
- Use case description
- Proposed solution
- Alternative solutions considered
- Impact on existing features

## Branch Naming

- `feature/` - New features
- `fix/` - Bug fixes
- `docs/` - Documentation
- `refactor/` - Code refactoring
- `test/` - Test additions

Example: `feature/stripe-integration`

## Commit Guidelines

Good commit messages:
```
feat: add Stripe payment integration
fix: resolve null pointer in user profile
docs: update API endpoint documentation
test: add billing controller tests
```

Bad commit messages:
```
update
fix bug
changes
wip
```

## Documentation

Update documentation when:
- Adding new API endpoints
- Changing existing endpoints
- Adding environment variables
- Changing configuration
- Adding dependencies

Files to update:
- `docs/API.md` - API endpoints
- `docs/DEPLOYMENT.md` - Deployment steps
- `README.md` - General info
- `.env.example` - New env vars

## Testing Requirements

All PRs must:
- Pass all existing tests
- Add tests for new features
- Maintain or improve code coverage
- Pass static analysis (PHPStan)
- Pass code style checks (Pint)

## Community

- Be respectful and inclusive
- Help other contributors
- Share knowledge
- Follow our Code of Conduct

## Questions?

- Open a GitHub Discussion
- Check existing issues
- Review documentation
- Contact maintainers

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

Thank you for contributing to Scout API! 🚀
