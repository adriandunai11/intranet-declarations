# Felhasználói kapcsolás integrációja

Az `integration/app` mappa a központi intranet alkalmazás két módosított fájlját tartalmazza:

- `Controllers/Users.php`
- `Views/admin/users/view.php`

A felhasználói adatlapon megjelenő **Nyilatkozati kapcsolat** fül a nyilatkozati modul
`IntranetUserLinkService` szolgáltatását használja. A megtekintéshez `users_view`, a kézi
kapcsoláshoz `users_edit` jogosultság szükséges; külön nyilatkozati jogosultság nem kell.

Új vagy módosított aktív intranet felhasználónál a rendszer automatikusan kapcsol, ha pontosan
egy, azonos ANTRA-azonosítójú nyilatkozati személy található. E-mail-egyezés alapján csak kézi
kapcsolás végezhető.

Ha a központi alkalmazásban az automatikus controller-routing ki van kapcsolva, az alábbi POST
route-ot külön fel kell venni:

```php
$routes->post('users/linkDeclarationPerson/(:num)', 'Users::linkDeclarationPerson/$1');
```
