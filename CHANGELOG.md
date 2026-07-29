# Changelog

All notable changes to the Smart Referral Pro plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-07-28
### Added
- Initial release of Smart Referral Pro
- Admin dashboard for managing referral categories and percentage earnings
- Account page tab showing unique referral codes and referral records
- Wallet system with coupon conversion and withdrawal options
- Checkout referral code input with query variable support
- Admin view for overseeing all accounts, sales, and earnings
- One-time referral enforcement (new users can only be referred once)
- First-purchase-only referral validation
- Fraud detection and security measures
- Git version control integration with automated CI/CD pipeline

### Security
- Nonce verification on all AJAX endpoints
- Capability checks for admin actions
- SQL injection prevention with prepared statements
- XSS prevention with proper escaping
- Rate limiting for API endpoints
- Data encryption for sensitive payment details
- Secure cookie handling with HttpOnly and SameSite

[Unreleased]: https://github.com/yourusername/smart-referral-pro/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/yourusername/smart-referral-pro/releases/tag/v1.0.0
