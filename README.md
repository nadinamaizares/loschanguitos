# Sistema de Pollería 🐔

Sistema de mostrador para una pollería: venta por peso (kg), control de stock,
y **libreta de fiado** (quién debe, cuánto y qué se llevó).

Hecho en **PHP puro** (sin frameworks) + MySQL/MariaDB de XAMPP, con la lógica
de negocio en **procedimientos almacenados** para garantizar atomicidad.

---

## Cómo arrancarlo

Ya está todo funcionando. Para usarlo:

1. **Encender MySQL y Apache** en el panel de control de XAMPP.
2. Abrir en el navegador:

   **http://localhost/polleria/frontend/**

Eso es todo. Si la base ya está creada (lo está), no hay que hacer nada más.

---

## Cómo está armado

```
polleria/
├── database/            Base de datos (esquema, datos, procedimientos, tests)
│   ├── 01_schema.sql          10 tablas
│   ├── 02_seed_y_vistas.sql   categorías, productos de ejemplo y 3 vistas
│   ├── 03_procedimientos.sql  los 4 SP con la lógica de negocio
│   ├── test_flujo.sql         prueba: cargar stock -> vender fiado
│   └── test_pagos.sql         prueba: pagos parciales de la libreta
│
├── backend/             API en PHP puro (devuelve JSON)
│   ├── config.php             credenciales de la base (acá se cambia)
│   ├── public/index.php       único punto de entrada de la API
│   └── src/
│       ├── bootstrap.php      autoloader + manejo de errores
│       ├── Db.php             conexión PDO y atajos (select, call, etc.)
│       ├── Router.php         router con parámetros en la URL
│       ├── Response.php       respuestas JSON uniformes
│       ├── Input.php          lectura y validación de lo que llega
│       ├── routes/api.php     todas las rutas, en un mapa legible
│       └── services/          la lógica de cada módulo
│           ├── VentaService.php     ventas (contado y fiado)
│           ├── StockService.php     cargar mercadería, ajustes, mermas
│           ├── FiadoService.php     la libreta
│           ├── CatalogoService.php  productos, categorías, clientes
│           └── CajaService.php      cierre de caja y reportes
│
└── frontend/            Pantallas (HTML + CSS + JS vanilla)
    ├── index.php              VENDER (pantalla principal)
    ├── stock.php              stock, carga de mercadería y ajustes
    ├── libreta.php            LA LIBRETA (deudas por cliente)
    ├── ventas.php             historial y detalle de ventas
    ├── cierre.php             cierre de caja del día
    ├── productos.php          catálogo, alta de productos y clientes
    ├── paginas/               layout y pie comunes
    └── assets/                estilos.css y el JS de cada pantalla
```

### Las pantallas

| Pantalla | Para qué sirve |
|---|---|
| **Vender** | Tocar un producto, poner el peso, cobrar. Contado o fiado. |
| **Stock** | Cargar mercadería, ajustar por conteo real, registrar mermas. |
| **Libreta** | Ver quién debe, cuánto y **qué se llevó**. Registrar pagos. |
| **Ventas** | Historial con el detalle de cada venta. |
| **Cierre de caja** | Cuánto debería haber en la caja y cuánto se fue fiado. |
| **Productos** | Catálogo, y dar de alta productos y clientes nuevos. |

---

## La libreta de fiado

Es el módulo más importante para el día a día. Funciona así:

1. En la pantalla de venta elegís **"Fiado"** en vez de "Contado".
2. Elegís el cliente (o creás uno nuevo al toque).
3. La venta queda registrada **dos veces**: como venta y como deuda en la libreta.
4. Cuando el cliente trae plata, vas a **Libreta → Ver → Cobrar**.
5. Podés cobrar **parcial** (deja $5.000 de una deuda de $12.000) o todo.
6. Si intentás cobrar más de lo que debe, el sistema lo rechaza.

Cada fiado guarda **qué se llevó** (ej: "2.500 kg Pechuga + 1.000 kg Alitas"),
no solo el monto. Eso es lo que después te permite decir "mirá, esto te llevaste".

---

## La API

Todas las respuestas tienen la misma forma:

```json
{ "ok": true,  "data": ... }
{ "ok": false, "error": "mensaje" }
```

### Rutas

| Método | Ruta | Qué hace |
|---|---|---|
| GET | `/api/ping` | Chequear que la API responde |
| GET | `/api/productos` | Catálogo con precio y stock |
| POST | `/api/productos` | Alta de producto |
| GET | `/api/categorias` | Categorías |
| GET | `/api/clientes` | Clientes con su saldo deudor |
| POST | `/api/clientes` | Alta de cliente |
| GET | `/api/stock` | Stock actual de todo |
| GET | `/api/stock/alertas` | Solo lo que está bajo o agotado |
| GET | `/api/stock/movimientos` | Historial de entradas y salidas |
| POST | `/api/stock/cargar` | Cargar mercadería |
| POST | `/api/stock/ajustar` | Ajuste manual o merma |
| POST | `/api/ventas` | **Registrar una venta** |
| GET | `/api/ventas` | Últimas ventas |
| GET | `/api/ventas/{id}` | Detalle de una venta |
| GET | `/api/libreta` | La libreta completa |
| GET | `/api/libreta/{clienteId}` | Fiados y pagos de un cliente |
| POST | `/api/fiado/pagar` | Registrar un pago |
| GET | `/api/caja/resumen` | Cierre de caja del día |
| GET | `/api/caja/ventas` | Ventas de un día |

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

La lógica que **no puede fallar a medias** vive en la base, dentro de
transacciones. Si algo sale mal en el medio, no queda stock descontado sin venta
registrada.

| Procedimiento | Qué garantiza |
|---|---|
| `sp_registrar_venta` | Verifica stock, calcula totales, descuenta y (si es fiado) genera la deuda. Todo o nada. |
| `sp_cargar_stock` | Suma mercadería y deja el movimiento registrado |
| `sp_pagar_fiado` | Registra el pago, actualiza el estado y **rechaza pagar de más** |
| `sp_ajustar_stock` | Corrige por conteo real o registra una merma |

### ⚠️ Importante: el formato de los items

El MySQL de XAMPP es **MariaDB 10.1**, que **no soporta el tipo JSON** ni las
funciones `JSON_EXTRACT` / `JSON_LENGTH`. Por eso los items de una venta se
pasan como texto simple:

```
'2:2.5;4:1'   →  2.5 kg del producto 2 + 1 kg del producto 4
```

Si algún día actualizás MySQL/MariaDB a 10.2 o superior, se podría volver a usar
JSON, pero no hay necesidad: el formato actual funciona y se entiende.

---

## Probar la base de datos

Los dos scripts de prueba sirven para verificar que todo funciona. Se corren
desde la línea de comandos:

```powershell
# Flujo completo: cargar stock -> vender fiado -> ver la libreta
Get-Content database\test_flujo.sql -Raw | C:\xampp\mysql\bin\mysql.exe -u root --table polleria

# Pagos: parcial -> salda -> intento de pagar de más (debe fallar)
Get-Content database\test_pagos.sql -Raw | C:\xampp\mysql\bin\mysql.exe -u root --table polleria
```

---

## Recrear la base desde cero

Si algún día hay que rehacerla (por ejemplo, en otra máquina):

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
> `DELIMITER` y falla. Guardarlo siempre **sin BOM** y con saltos de línea LF.

---

## Usuario

Por ahora **no hay login**: las ventas se registran a nombre del usuario
administrador (id 1), que crea el seed. Cuando haga falta, se agrega autenticación
sin tocar el resto: las tablas de usuarios y roles ya existen (`001_usuarios`).

---

## Qué falta (ideas para más adelante)

- Login con usuarios y roles (admin / vendedor) — las tablas ya están.
- Impresión de tickets.
- Ranking de productos más vendidos.
- Exportar el cierre de caja a Excel/PDF.
- Estadísticas por período (semana, mes).