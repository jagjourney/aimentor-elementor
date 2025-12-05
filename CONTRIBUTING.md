# Contributing to AiMentor Elementor

Thank you for your interest in contributing to AiMentor Elementor! This document provides guidelines and instructions for contributing.

## Getting Started

1. **Fork the repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/YOUR-USERNAME/aimentor-elementor.git
   ```
3. **Set up your development environment**:
   - WordPress 5.0+ installation
   - Elementor plugin (free version minimum)
   - PHP 7.4+

## Development Workflow

### Branches

- `main` — Stable release branch
- `develop` — Development branch for next release
- `feature/*` — Feature branches
- `fix/*` — Bug fix branches

### Making Changes

1. Create a new branch from `develop`:
   ```bash
   git checkout develop
   git checkout -b feature/your-feature-name
   ```

2. Make your changes following our coding standards

3. Test your changes thoroughly

4. Commit with clear messages:
   ```bash
   git commit -m "Add: Brief description of the feature"
   ```

### Commit Message Format

Use these prefixes:
- `Add:` — New features
- `Fix:` — Bug fixes
- `Update:` — Updates to existing features
- `Remove:` — Removed features or code
- `Docs:` — Documentation changes
- `Refactor:` — Code refactoring

## Coding Standards

### PHP

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Use meaningful variable and function names
- Add PHPDoc blocks for functions and classes
- Prefix all functions and classes with `aimentor_` or `AiMentor_`

### JavaScript

- Use vanilla JS or jQuery (already bundled with WordPress)
- Follow WordPress JavaScript coding standards
- Avoid adding external dependencies unless necessary

### CSS

- Use WordPress admin color scheme variables where possible
- Prefix custom classes with `aimentor-`

## Testing

Before submitting a pull request:

1. **Test in multiple browsers** (Chrome, Firefox, Safari, Edge)
2. **Test with different WordPress versions** (minimum 5.0)
3. **Test with Elementor Free and Pro**
4. **Verify PHP compatibility** (7.4, 8.0, 8.1, 8.2)

### Running Tests

```bash
# PHP syntax check
find . -name "*.php" -exec php -l {} \;

# WordPress coding standards (if phpcs installed)
phpcs --standard=WordPress .
```

## Pull Request Process

1. Update documentation if needed
2. Update the changelog in `readme.txt`
3. Ensure all tests pass
4. Submit PR against the `develop` branch
5. Fill out the PR template completely
6. Wait for code review

### PR Requirements

- Clear description of changes
- Reference any related issues
- Screenshots for UI changes
- No unrelated changes bundled in

## Reporting Issues

### Bug Reports

Include:
- WordPress version
- Elementor version
- PHP version
- Browser and version
- Steps to reproduce
- Expected vs actual behavior
- Screenshots if applicable

### Feature Requests

Include:
- Clear description of the feature
- Use case / problem it solves
- Potential implementation approach (optional)

## Code of Conduct

- Be respectful and inclusive
- Provide constructive feedback
- Help others learn and grow
- Focus on the code, not the person

## Questions?

- Open an issue for questions
- Check existing issues and documentation first

---

Thank you for contributing to AiMentor Elementor!
