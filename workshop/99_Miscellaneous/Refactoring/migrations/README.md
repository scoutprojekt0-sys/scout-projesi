# Migrations

Apply in order:

1. `001_create_core_tables.sql`
2. `002_add_unique_users_username.sql`

Example:

```sql
SOURCE migrations/001_create_core_tables.sql;
SOURCE migrations/002_add_unique_users_username.sql;
```
