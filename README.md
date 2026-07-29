# Smart Referral Pro

[![CI/CD](https://github.com/yourusername/smart-referral-pro/actions/workflows/ci-cd.yml/badge.svg)](https://github.com/yourusername/smart-referral-pro/actions/workflows/ci-cd.yml)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-8892BF.svg)](https://php.net/)
[![WordPress Version](https://img.shields.io/badge/wordpress-%3E%3D6.0-blue.svg)](https://wordpress.org/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Advanced referral system for WooCommerce with wallet management, coupon generation, and comprehensive admin dashboard.

## Features

- **Referral Categories** - Create custom categories with different commission rates
- **Unique Referral Codes** - Auto-generated memorable codes from user names
- **Wallet System** - Track earnings with pending/approved status
- **Coupon Conversion** - Convert earnings to WooCommerce discount coupons
- **Withdrawal System** - Request payouts via PayPal, Bank Transfer, or Stripe
- **One-Time Referral** - Prevents duplicate referrals and self-referrals
- **First Purchase Only** - Referral discounts apply only on first order
- **Query Variable Support** - `?ref=CODE` automatically applies at checkout
- **Fraud Detection** - Rate limiting and suspicious activity monitoring
- **Admin Dashboard** - Complete overview of accounts, sales, and earnings

## Requirements

- PHP >= 8.0
- WordPress >= 6.0
- WooCommerce >= 8.0

## Installation

### From GitHub Release

1. Download the latest release ZIP from [Releases](https://github.com/yourusername/smart-referral-pro/releases)
2. Upload to WordPress via **Plugins > Add New > Upload**
3. Activate the plugin

### From Source

```bash
git clone https://github.com/yourusername/smart-referral-pro.git
cd smart-referral-pro
composer install
npm install
npm run build
```

## Development

### Setup

```bash
# Clone repository
git clone https://github.com/yourusername/smart-referral-pro.git
cd smart-referral-pro

# Install dependencies
composer install
npm install

# Setup git hooks
php scripts/git-pusher.php setup
```

### Version Control Commands

```bash
# Check status
php scripts/git-pusher.php status

# Validate plugin
php scripts/git-pusher.php validate

# Bump version (patch/minor/major)
php scripts/git-pusher.php version minor

# Full release workflow
php scripts/git-pusher.php release patch

# Deploy to staging
php scripts/git-pusher.php deploy staging

# Deploy to production
php scripts/git-pusher.php deploy production
```

### NPM Scripts

```bash
npm run build          # Build minified assets
npm run watch          # Watch for changes
npm run test           # Run PHPUnit tests
npm run lint           # Run PHP syntax check
npm run release        # Full release workflow
```

### Composer Scripts

```bash
composer test          # Run PHPUnit tests
composer phpcs         # Check coding standards
composer phpcbf        # Fix coding standards
composer phpstan       # Run static analysis
composer lint          # Check PHP syntax
composer validate      # Validate plugin
```

## Git Workflow

### Branch Strategy

- `main` - Production-ready code
- `develop` - Integration branch for features
- `feature/*` - Feature branches
- `hotfix/*` - Emergency fixes

### Release Process

1. **Manual Release (CLI)**
   ```bash
   php scripts/git-pusher.php release patch
   ```

2. **Automated Release (GitHub Actions)**
   - Push to `main` triggers auto-version bump
   - Tags are created automatically
   - GitHub Release is published with ZIP
   - Optional: Deploys to WordPress.org SVN

### CI/CD Pipeline

The GitHub Actions workflow includes:

1. **Code Quality**
   - PHP syntax check
   - PHPCS (WordPress coding standards)
   - PHPStan (static analysis)
   - Secret scanning (TruffleHog)
   - Security audit (Semgrep)

2. **Testing**
   - PHPUnit tests with MySQL
   - Code coverage reporting

3. **Build**
   - Asset minification
   - Distribution package creation

4. **Deploy**
   - GitHub Release creation
   - WordPress.org SVN deployment
   - Server deployment via SSH

## Security

- All AJAX endpoints verify nonces
- Admin actions require `manage_options` capability
- Prepared statements prevent SQL injection
- Input sanitization and output escaping
- Rate limiting on public endpoints
- AES-256 encryption for payment details
- Secure cookie attributes (HttpOnly, SameSite)

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'feat: add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Commit Message Convention

We follow [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation changes
- `style:` Code style changes
- `refactor:` Code refactoring
- `perf:` Performance improvements
- `test:` Test changes
- `chore:` Build/tooling changes

## License

GPL-2.0+ - See [LICENSE](LICENSE) for details.

## Support

- [Documentation](https://docs.smartreferral.pro)
- [Issue Tracker](https://github.com/yourusername/smart-referral-pro/issues)
- [Discussions](https://github.com/yourusername/smart-referral-pro/discussions)
