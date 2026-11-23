# Integración SSO con WorkColbeef

Inventario de Lenguas conserva su inicio de sesión local y añade dos interfaces:

- `GET /auth/sso/workcolbeef?sso_code=...`: canjea un código de un solo uso y crea la sesión local.
- `POST /api/internal/workcolbeef/users/sync`: vincula o aprovisiona usuarios desde WorkColbeef.

## Seguridad

- El SSO está deshabilitado por defecto.
- El código se canjea de servidor a servidor en un máximo de cinco segundos.
- El secreto del cliente nunca se expone al navegador.
- El aprovisionamiento exige HMAC-SHA256 sobre `timestamp + "." + cuerpo HTTP exacto`.
- Las firmas vencen después de 300 segundos.
- `workcolbeef_user_id` es único y evita vincular una identidad central con dos cuentas locales.
- Una coincidencia ambigua por usuario o correo se rechaza; no se seleccionan cuentas por heurística.
- El rol `super_admin` existente se conserva y no puede asignarse desde el portal.

## Configuración

```dotenv
WORKCOLBEEF_SSO_ENABLED=true
WORKCOLBEEF_BASE_URL=http://127.0.0.1:8200
WORKCOLBEEF_SSO_EXCHANGE_PATH=/api/sso/exchange
WORKCOLBEEF_SSO_CLIENT_ID=lenguas
WORKCOLBEEF_SSO_CLIENT_SECRET=<secreto-del-cliente>
WORKCOLBEEF_SSO_AUDIENCE=lenguas
WORKCOLBEEF_SSO_TIMEOUT_SECONDS=5
WORKCOLBEEF_PROVISIONING_SECRET=<secreto-hmac-de-32-o-mas-caracteres>
```

Después de desplegar:

```bash
php artisan migrate --force
php artisan optimize:clear
```

## Contrato de aprovisionamiento

El cuerpo JSON debe contener exactamente:

```json
{
  "workcolbeef_user_id": "42",
  "username": "persona@colbeef.com",
  "name": "Nombre de la persona",
  "email": "persona@colbeef.com",
  "role": "logistico",
  "permissions": {},
  "active": true
}
```

Roles asignables: `admin`, `supervisor`, `gerencia`, `logistico`, `auxiliar`,
`planillaje`, `calidad`, `operador` y `user`.

## Reversión

Desactive `WORKCOLBEEF_SSO_ENABLED`, limpie la configuración y conserve el login
local. La migración puede revertirse con `php artisan migrate:rollback`; no la
revierta mientras existan vínculos que deban auditarse.
