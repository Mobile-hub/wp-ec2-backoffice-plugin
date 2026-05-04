# Security Policy

## Reporting a Vulnerability

If you discover a security issue in this repository, please do **not** open a public GitHub issue with exploit details.

Instead:

1. Contact the maintainer privately through GitHub.
2. Include a clear description of the issue, affected files, and reproduction steps.
3. If possible, suggest a fix or mitigation.

## Scope

This project includes:

- A WordPress plugin bootstrap and source code under `src/`
- AWS infrastructure templates under `infrastructure/`

When reporting a vulnerability, please specify whether it affects:

- WordPress admin access or capability checks
- Secret handling or encryption
- AJAX endpoints or nonce validation
- AWS IAM, EC2, or security group configuration

## Secret Handling Expectations

- Never commit AWS credentials, decrypted passwords, or private keys.
- Treat generated key material under `infrastructure/keypairs/` as sensitive.
- Review the git history and working tree before publishing changes.