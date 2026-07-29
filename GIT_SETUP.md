# Smart Referral Pro - Git Version Control Integration

## Complete File Structure

```
referral-plugin/
├── .github/
│   └── workflows/
│       └── ci-cd.yml          # GitHub Actions CI/CD pipeline
├── admin/
│   ├── dashboard.php          # Admin dashboard view
│   ├── categories.php         # Category management view
│   ├── accounts.php           # All accounts view
│   ├── records.php            # Referral records view
│   ├── withdrawals.php        # Withdrawal requests view
│   └── settings.php           # Plugin settings view
├── assets/
│   ├── css/
│   │   ├── admin.css          # Admin styles
│   │   └── public.css         # Public/frontend styles
│   └── js/
│       ├── admin.js           # Admin JavaScript
│       └── public.js          # Public/frontend JavaScript
├── deploy/
│   └── config.json.example    # Deployment config template
├── includes/
│   ├── Core.php               # Main plugin initialization
│   ├── Database.php            # Database schema & activation
│   ├── Security.php            # Security utilities
│   ├── ReferralCode.php        # Referral code generation & management
│   ├── Wallet.php              # Wallet & earnings management
│   ├── Checkout.php            # WooCommerce checkout integration
│   ├── Admin.php               # Admin functionality
│   ├── PublicFacing.php        # Public/frontend functionality
│   └── Notifications.php       # Email notifications
├── public/
│   └── account-referrals.php  # My Account referrals tab
├── scripts/
│   └── git-pusher.php         # Git version control CLI tool
├── bin/
│   └── install-wp-tests.sh    # WordPress test suite installer
├── smart-referral-pro.php     # Main plugin file
├── composer.json              # PHP dependencies & scripts
├── package.json               # Node dependencies & scripts
├── phpunit.xml                # PHPUnit configuration
├── phpcs.xml                  # PHP CodeSniffer configuration
├── CHANGELOG.md               # Version changelog
├── README.md                  # Project documentation
├── .gitignore                 # Git ignore rules
├── .gitattributes             # Git export-ignore rules
├── .distignore                # Deployment ignore rules
└── .editorconfig              # Editor configuration
```

## Git Version Control Features

### 1. Git Pusher CLI Tool (`scripts/git-pusher.php`)

A comprehensive CLI tool for managing plugin versions:

| Command | Description |
|---------|-------------|
| `version [patch/minor/major]` | Bump version number across all files |
| `tag` | Create annotated git tag |
| `push [remote]` | Push to remote with tags |
| `status` | Show version, branch, commits, tags |
| `changelog` | Generate changelog from commits |
| `deploy [env]` | Deploy to staging/production via SSH/rsync |
| `rollback [ver]` | Rollback to previous version |
| `release [type]` | Full release workflow (validate → bump → tag → push) |
| `setup` | Install git hooks (pre-commit, post-merge) |
| `validate` | Validate plugin (syntax, files, versions, debug code) |

**Usage Examples:**
```bash
# Full release workflow
php scripts/git-pusher.php release minor

# Deploy to staging
php scripts/git-pusher.php deploy staging

# Check status
php scripts/git-pusher.php status

# Dry run (preview without executing)
php scripts/git-pusher.php release major --dry-run
```

### 2. GitHub Actions CI/CD Pipeline (`.github/workflows/ci-cd.yml`)

**7 Automated Jobs:**

1. **Quality Check**
   - PHP syntax validation
   - PHPCS (WordPress coding standards)
   - PHPStan (static analysis)
   - Secret scanning (TruffleHog)
   - Security audit (Semgrep)

2. **Testing**
   - PHPUnit with MySQL service
   - Code coverage reporting to Codecov

3. **Version Bump**
   - Auto-detect bump type from commit messages
   - Update version in all PHP files
   - Generate changelog
   - Commit and push

4. **Build**
   - Node.js asset compilation
   - CSS/JS minification
   - Distribution package creation

5. **Release**
   - Create GitHub Release with ZIP
   - Auto-generate release notes

6. **Deploy to WordPress.org**
   - SVN deployment via 10up action
   - Asset management

7. **Deploy to Server**
   - SSH/rsync to staging/production
   - Post-deploy WP-CLI commands

### 3. Git Hooks (Installed via `php scripts/git-pusher.php setup`)

**Pre-commit Hook:**
- PHP syntax check on all files
- Debug code detection (var_dump, print_r, die)
- Blocks commits with syntax errors

**Post-merge Hook:**
- Auto-install composer dependencies
- Auto-install npm dependencies

### 4. Development Tools

**Composer Scripts:**
```bash
composer test          # Run PHPUnit tests
composer phpcs         # Check coding standards
composer phpcbf        # Fix coding standards
composer phpstan       # Run static analysis
composer lint          # PHP syntax check
composer validate      # Validate plugin
```

**NPM Scripts:**
```bash
npm run build          # Build minified CSS/JS
npm run watch          # Watch for file changes
npm run release        # Full release workflow
npm run deploy:staging    # Deploy to staging
npm run deploy:production # Deploy to production
```

## Setup Instructions

### 1. Initialize Git Repository

```bash
cd referral-plugin
git init
git add .
git commit -m "Initial commit"
git branch -M main
git remote add origin https://github.com/yourusername/smart-referral-pro.git
git push -u origin main
```

### 2. Install Dependencies

```bash
# PHP dependencies
composer install

# Node dependencies
npm install

# Setup git hooks
php scripts/git-pusher.php setup
```

### 3. Configure Deployment

```bash
# Copy deployment config template
cp deploy/config.json.example deploy/config.json

# Edit with your server details
nano deploy/config.json
```

### 4. Configure GitHub Secrets

Go to **Settings > Secrets and variables > Actions** and add:

| Secret | Description |
|--------|-------------|
| `SVN_USERNAME` | WordPress.org SVN username |
| `SVN_PASSWORD` | WordPress.org SVN password |
| `STAGING_HOST` | Staging server IP/hostname |
| `STAGING_USER` | Staging SSH username |
| `STAGING_SSH_KEY` | Staging SSH private key |
| `STAGING_PATH` | Staging WordPress path |
| `PROD_HOST` | Production server IP/hostname |
| `PROD_USER` | Production SSH username |
| `PROD_SSH_KEY` | Production SSH private key |
| `PROD_PATH` | Production WordPress path |

## Release Workflow

### Option A: Manual Release (CLI)

```bash
# Full automated release
php scripts/git-pusher.php release patch

# This will:
# 1. Validate the plugin
# 2. Bump version number
# 3. Generate changelog
# 4. Create git tag
# 5. Push to origin
# 6. Trigger GitHub Actions
```

### Option B: GitHub Actions Auto-Release

```bash
# Push to main branch triggers auto-version bump
git add .
git commit -m "feat: add new referral feature"
git push origin main

# GitHub Actions will:
# 1. Detect commit type (feat = minor)
# 2. Bump version automatically
# 3. Create tag and release
```

### Option C: Manual Dispatch

Go to **Actions > Smart Referral Pro CI/CD > Run workflow**:
- Select version type (patch/minor/major)
- Select deploy target (none/staging/production)
- Click "Run workflow"

## Security Best Practices

1. **Never commit sensitive data**
   - Use `.gitignore` for config files
   - Use GitHub Secrets for credentials
   - Encrypt payment details with AES-256

2. **Branch protection**
   - Require PR reviews for main branch
   - Require status checks to pass
   - Require signed commits

3. **Deployment security**
   - Use SSH keys (not passwords)
   - Restrict deployment to specific branches
   - Enable two-factor authentication

4. **Code quality**
   - Pre-commit hooks validate code
   - CI runs security scans
   - Static analysis catches bugs early

## Troubleshooting

**Q: Git pusher shows "Not a git repository"**
A: Run `git init` first, then `php scripts/git-pusher.php setup`

**Q: Deployment fails with SSH error**
A: Ensure SSH key is added to server `~/.ssh/authorized_keys`

**Q: WordPress.org deployment fails**
A: Verify SVN credentials in GitHub Secrets

**Q: Version bump doesn't update all files**
A: Check file permissions and ensure files are tracked by git

## Next Steps

1. Create GitHub repository
2. Push initial code
3. Configure GitHub Secrets
4. Run first release: `php scripts/git-pusher.php release patch`
5. Monitor Actions tab for CI/CD status
