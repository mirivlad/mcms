# Security policy

## Reporting vulnerabilities

Please **do not open a public issue** containing an exploit, unpatched
vulnerability or credentials. Use GitHub's private security reporting/
repository Security Advisories when available, or contact the maintainer
privately through the channels on the project profile/site. Provide affected
versions, reproduction steps, impact and a minimal non-sensitive example.
Allow time for a fix before public disclosure. No guaranteed response SLA is
currently published.

## Support and threat model

The latest tagged release and current `main` are the primary maintenance
targets; older releases may not receive individual backports.
mCMS runs administrator-installed PHP modules and themes with application
privileges. ZIP validation is **not a PHP sandbox**: use trusted publishers only.

Protect private code and data outside DocumentRoot. Deploy public sites behind
correctly configured TLS termination/proxy, keep PHP/dependencies updated, back
up the database and persistent data *off-host*, and test restoration.
Never include secrets or personal data in diagnostic reports.

For deployment and recovery consult
[the operations guides](docs/guide/en/README.md) and
[the storage/security contracts](docs/STORAGE.md).
