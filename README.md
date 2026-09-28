# Zimbermanne Company Limited website (PHP + PostgreSQL)

Public site: home, product catalogue with search, quotation list, building-contract enquiry form, English/Swahili.
Staff area at /admin: dashboard, requests (status, editable prices, printable quotation, WhatsApp reply), product management, CSV export.

## Railway variables (web service)
- DATABASE_URL  = ${{Postgres.DATABASE_URL}}   (add a PostgreSQL service and reference it)
- ADMIN_PASSWORD = a long password (required, admin is disabled without it)
- ADMIN_USER     = optional, defaults to "admin"

Tables and sample products are created automatically on first visit.
