# Sistema de PollerÃ­a ðŸ”

Sistema de mostrador para una pollerÃ­a: venta por peso (kg), control de stock,
y **libreta de fiado** (quiÃ©n debe, cuÃ¡nto y quÃ© se llevÃ³).

Hecho en **PHP puro** (sin frameworks) + MySQL/MariaDB de XAMPP, con la lÃ³gica
de negocio en **procedimientos almacenados** para garantizar atomicidad.

---

## CÃ³mo arrancarlo

Ya estÃ¡ todo funcionando. Para usarlo:

1. **Encender MySQL y Apache** en el panel de control de XAMPP.
2. Abrir en el navegador:

   **http://localhost/polleria/frontend/**

Eso es todo. Si la base ya estÃ¡ creada (lo estÃ¡), no hay que hacer nada mÃ¡s.

---

## CÃ³mo estÃ¡ armado

```
polleria/
â”œâ”€â”€ database/            Base de datos (esquema, datos, procedimientos, tests)
â”‚   â”œâ”€â”€ 01_schema.sql          10 tablas
â”‚   â”œâ”€â”€ 02_seed_y_vistas.sql   categorÃ­as, productos de ejemplo y 3 vistas
â”‚   â”œâ”€â”€ 03_procedimientos.sql  los 4 SP con la lÃ³gica de negocio
â”‚   â”œâ”€â”€ test_flujo.sql         prueba: cargar stock -> vender fiado
â”‚   â””â”€â”€ test_pagos.sql         prueba: pagos parciales de la libreta
â”‚
â”œâ”€â”€ backend/             API en PHP puro (devuelve JSON)
â”‚   â”œâ”€â”€ config.php             credenciales de la base (acÃ¡ se cambia)
â”‚   â”œâ”€â”€ public/index.php       Ãºnico punto de entrada de la API
â”‚   â””â”€â”€ src/
â”‚       â”œâ”€â”€ bootstrap.php      autoloader + manejo de errores
â”‚       â”œâ”€â”€ Db.php             conexiÃ³n PDO y atajos (select, call, etc.)
â”‚       â”œâ”€â”€ Router.php         router con parÃ¡metros en la URL
â”‚       â”œâ”€â”€ Response.php       respuestas JSON uniformes
â”‚       â”œâ”€â”€ Input.php          lectura y validaciÃ³n de lo que llega
â”‚       â”œâ”€â”€ routes/api.php     todas las rutas, en un mapa legible
â”‚       â””â”€â”€ services/          la lÃ³gica de cada mÃ³dulo
â”‚           â”œâ”€â”€ VentaService.php     ventas (contado y fiado)
â”‚           â”œâ”€â”€ StockService.php     cargar mercaderÃ­a, ajustes, mermas
â”‚           â”œâ”€â”€ FiadoService.php     la libreta
â”‚           â”œâ”€â”€ CatalogoService.php  productos, categorÃ­as, clientes
â”‚           â””â”€â”€ CajaService.php      cierre de caja y reportes
â”‚
â””â”€â”€ frontend/            Pantallas (HTML + CSS + JS vanilla)
    â”œâ”€â”€ index.php              VENDER (pantalla principal)
    â”œâ”€â”€ stock.php              stock, carga de mercaderÃ­a y ajustes
    â”œâ”€â”€ libreta.php            LA LIBRETA (deudas por cliente)
    â”œâ”€â”€ ventas.php             historial y detalle de ventas
    â”œâ”€â”€ cierre.php             cierre de caja del dÃ­a
    â”œâ”€â”€ productos.php          catÃ¡logo, alta de productos y clientes
    â”œâ”€â”€ paginas/               layout y pie comunes
    â””â”€â”€ assets/                estilos.css y el JS de cada pantalla
```

### Las pantallas

| Pantalla | Para quÃ© sirve |
|---|---|
| **Vender** | Tocar un producto, poner el peso, cobrar. Contado o fiado. |
| **Stock** | Cargar mercaderÃ­a, ajustar por conteo real, registrar mermas. |
| **Libreta** | Ver quiÃ©n debe, cuÃ¡nto y **quÃ© se llevÃ³**. Registrar pagos. |
| **Ventas** | Historial con el detalle de cada venta. |
| **Cierre de caja** | CuÃ¡nto deberÃ­a haber en la caja y cuÃ¡nto se fue fiado. |
| **Productos** | CatÃ¡logo, y dar de alta productos y clientes nuevos. |

---

## Formas de pago
Al cobrar en el mostrador hay **4 formas de pago**:
| Forma | Que significa | Como se guarda |
|---|---|---|
| **Contado** | Efectivo (paga al momento) | tipo=contado, medio=efectivo |
| **Virtual** | Transferencia / QR / Mercado Pago | tipo=contado, medio=virtual |
| **Tarjeta** | Debito / credito con posnet | tipo=contado, medio=tarjeta |
| **Fiado** | Va a la libreta del cliente | tipo=fiado |
En la base, la venta siempre es `contado` o `fiado` (columna `c005_tipo`). Cuando
es de contado, una columna aparte (`c005_medio_pago`) dice como se pago:
`efectivo`, `virtual` o `tarjeta`. Asi el **cierre de caja** puede separar cuanto
entro en efectivo (lo que tiene que haber en la caja) de lo cobrado por
transferencia y por posnet.
---## La libreta de fiado

Es el mÃ³dulo mÃ¡s importante para el dÃ­a a dÃ­a. Funciona asÃ­:

1. En la pantalla de venta elegÃ­s **"Fiado"** en vez de "Contado".
2. ElegÃ­s el cliente (o creÃ¡s uno nuevo al toque).
3. La venta queda registrada **dos veces**: como venta y como deuda en la libreta.
4. Cuando el cliente trae plata, vas a **Libreta â†’ Ver â†’ Cobrar**.
5. PodÃ©s cobrar **parcial** (deja $5.000 de una deuda de $12.000) o todo.
6. Si intentÃ¡s cobrar mÃ¡s de lo que debe, el sistema lo rechaza.

Cada fiado guarda **quÃ© se llevÃ³** (ej: "2.500 kg Pechuga + 1.000 kg Alitas"),
no solo el monto. Eso es lo que despuÃ©s te permite decir "mirÃ¡, esto te llevaste".

---

## La API

Todas las respuestas tienen la misma forma:

```json
{ "ok": true,  "data": ... }
{ "ok": false, "error": "mensaje" }
```

### Rutas

| MÃ©todo | Ruta | QuÃ© hace |
|---|---|---|
| GET | `/api/ping` | Chequear que la API responde |
| POST | `/api/login` | **Ingresar** (usuario + contrasena) |
| POST | `/api/logout` | Cerrar sesion |
| GET | `/api/sesion` | Quien esta logueado (o null) |
| GET | `/api/productos` | CatÃ¡logo con precio y stock |
| POST | `/api/productos` | Alta de producto |
| GET | `/api/categorias` | CategorÃ­as |
| GET | `/api/clientes` | Clientes con su saldo deudor |
| POST | `/api/clientes` | Alta de cliente |
| GET | `/api/stock` | Stock actual de todo |
| GET | `/api/stock/alertas` | Solo lo que estÃ¡ bajo o agotado |
| GET | `/api/stock/movimientos` | Historial de entradas y salidas |
| POST | `/api/stock/cargar` | Cargar mercaderÃ­a |
| POST | `/api/stock/ajustar` | Ajuste manual o merma |
| POST | `/api/ventas` | **Registrar una venta** |
| GET | `/api/ventas` | Ãšltimas ventas |
| GET | `/api/ventas/{id}` | Detalle de una venta |
| GET | `/api/libreta` | La libreta completa |
| GET | `/api/libreta/{clienteId}` | Fiados y pagos de un cliente |
| POST | `/api/fiado/pagar` | Registrar un pago |
| GET | `/api/caja/resumen` | Cierre de caja del dÃ­a |
| GET | `/api/caja/ventas` | Ventas de un dÃ­a |

Ejemplo de venta fiada:

```bash
curl -X POST http://localhost/polleria/backend/public/api/ventas \
  -H "Content-Type: application/json" \
  -d '{
        "tipo": "fiado",
        "cliente_id": 1,
        "items": [
          {"producto_id": 2, "kilos": 2.5},
          {"producto_id": 4, "kilos": 1}
        ]
      }'
```

---

## Los procedimientos almacenados

La lÃ³gica que **no puede fallar a medias** vive en la base, dentro de
transacciones. Si algo sale mal en el medio, no queda stock descontado sin venta
registrada.

| Procedimiento | QuÃ© garantiza |
|---|---|
| `sp_registrar_venta` | Verifica stock, calcula totales, descuenta y (si es fiado) genera la deuda. Todo o nada. |
| `sp_cargar_stock` | Suma mercaderÃ­a y deja el movimiento registrado |
| `sp_pagar_fiado` | Registra el pago, actualiza el estado y **rechaza pagar de mÃ¡s** |
| `sp_ajustar_stock` | Corrige por conteo real o registra una merma |

### âš ï¸ Importante: el formato de los items

El MySQL de XAMPP es **MariaDB 10.1**, que **no soporta el tipo JSON** ni las
funciones `JSON_EXTRACT` / `JSON_LENGTH`. Por eso los items de una venta se
pasan como texto simple:

```
'2:2.5;4:1'   â†’  2.5 kg del producto 2 + 1 kg del producto 4
```

Si algÃºn dÃ­a actualizÃ¡s MySQL/MariaDB a 10.2 o superior, se podrÃ­a volver a usar
JSON, pero no hay necesidad: el formato actual funciona y se entiende.

---

## Probar la base de datos

Los dos scripts de prueba sirven para verificar que todo funciona. Se corren
desde la lÃ­nea de comandos:

```powershell
# Flujo completo: cargar stock -> vender fiado -> ver la libreta
Get-Content database\test_flujo.sql -Raw | C:\xampp\mysql\bin\mysql.exe -u root --table polleria

# Pagos: parcial -> salda -> intento de pagar de mÃ¡s (debe fallar)
Get-Content database\test_pagos.sql -Raw | C:\xampp\mysql\bin\mysql.exe -u root --table polleria
```

---

## Recrear la base desde cero

Si algÃºn dÃ­a hay que rehacerla (por ejemplo, en otra mÃ¡quina):

```powershell
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
$db = 'database'

& $mysql -u root -e "DROP DATABASE IF EXISTS polleria; CREATE DATABASE polleria CHARACTER SET utf8mb4;"
Get-Content "$db\01_schema.sql" -Raw | & $mysql -u root --default-character-set=utf8mb4 polleria
Get-Content "$db\02_seed_y_vistas.sql" -Raw | & $mysql -u root --default-character-set=utf8mb4 polleria
Get-Content "$db\03_procedimientos.sql" -Raw | & $mysql -u root --default-character-set=utf8mb4 polleria
```

> **Ojo con el BOM:** `03_procedimientos.sql` empieza con `DELIMITER //`. Si el
> archivo se guarda con BOM (marca UTF-8), el cliente de MySQL no reconoce el
> `DELIMITER` y falla. Guardarlo siempre **sin BOM** y con saltos de lÃ­nea LF.

---

## Usuario y acceso (login)
El sistema **si tiene login**. Para entrar, abrir:
**http://localhost/polleria/frontend/login.php**
Usuarios que crea el seed:
| Usuario | Contrasena | Rol |
|---|---|---|
| `admin` | `polleria2024` | admin |
| `vendedor` | `vendedor2024` | vendedor |
> Cambiar estas contrasenas apenas se ponga en produccion.
Como funciona:
- El backend valida contra `001_usuarios` con `password_hash()` / `password_verify()`
  (nunca texto plano) y guarda la sesion en una cookie HttpOnly (SameSite=Strict).
- **Todas las rutas de la API** (salvo `login`, `logout`, `sesion` y `ping`)
  exigen sesion: sin login responden `401`.
- Cada venta y cada pago quedan a nombre del **usuario logueado**, no de un id fijo.
- Si la sesion se vence, el frontend te manda solo al login.
Para dar de alta mas usuarios hoy se puede hacer por SQL; la pantalla de alta de
usuarios queda como pendiente (las tablas y el servicio `AuthService::crear()` ya
estan listos).

### Permisos por rol
| Accion | admin | vendedor |
|---|---|---|
| Vender (contado y **fiado**) | si | si |
| Ver clientes y libreta | si | si |
| Registrar pago de fiado (cobrar) | si | si |
| Cargar stock, ajustes y mermas | si | si |
| Ver stock / ventas / cierre de caja | si | si |
| **Alta de productos** | si | **no** |
| **Alta de clientes** | si | **no** |
| **Alta y listado de usuarios** | si | **no** |
En criollo: el vendedor **vende, fia y cobra** (todo el mostrador). Lo unico que
no puede es **dar de alta** cosas nuevas (productos, clientes, usuarios).
La regla se valida **en el backend** (403 si el rol no alcanza), no solo en la
pantalla: aunque un vendedor llame la API directo, la operacion se rechaza.
En el frontend, el vendedor no ve la pestana "Productos" ni los formularios de alta.
Los usuarios se crean desde **Productos -> Usuario nuevo** (solo admin).---
## Que falta (ideas para mÃ¡s adelante)

- ImpresiÃ³n de tickets.
- Ranking de productos mÃ¡s vendidos.
- Exportar el cierre de caja a Excel/PDF.
- EstadÃ­sticas por perÃ­odo (semana, mes).