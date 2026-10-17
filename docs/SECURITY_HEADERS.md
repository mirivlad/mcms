# HTTP response security headers

mCMS's Docker nginx server emits these baseline headers for **all** paths,
including static files and errors:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'`

This limited CSP **does not** attempt to restrict application scripts, style
nonces or dynamic module/theme assets. Those need a separate compatibility and
browser-regression review before enforcing a stronger policy.

## HTTPS/HSTS and reverse proxies

The bundled web container listens on **HTTP** behind the site owner's
TLS-terminating reverse proxy. Do **not** emit HSTS blindly from a plain-HTTP
internal backend. Configure the external **HTTPS** server/reverse proxy instead:

```nginx
# Only after confirming the site's HTTPS configuration works reliably:
add_header Strict-Transport-Security "max-age=31536000" always;
```

Only add `includeSubDomains` after auditing HTTPS on **all** subdomains, and
never enable `preload` casually. Keep TLS, proxy-trust settings and HTTP→HTTPS
redirects consistent. Proxies may override or drop backend headers: verify the
**public** response with `curl -I https://your-site.example/`, including
error and static-resource responses.

For standalone/shared hosting, apply the same baseline headers in Apache,
Nginx, Caddy or LiteSpeed's applicable vhost configuration; do not assume the
bundled Docker nginx configuration governs that installation mode.
See [server examples](guide/ru/webservers.md). Enabling CodeIgniter's stock
`secureheaders` filter globally without regression testing is not a substitute
for a reviewed application-specific CSP.
