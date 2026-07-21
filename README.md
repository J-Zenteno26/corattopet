# Coratto Pet

Estructura inicial de Coratto Pet y conexión segura a PostgreSQL alojado en Neon.

- PHP 8.1 o superior.
- Extensiones de PHP `pdo` y `pdo_pgsql` habilitadas.
- Una base de datos PostgreSQL en Neon.

## Configuración

1. Copia `.env.example` como `.env`.
2. Completa en `.env` las variables de la aplicación y las credenciales entregadas por Neon.
3. Mantén `DB_SSLMODE=require`; la aplicación rechaza configuraciones sin SSL obligatorio.
4. Sirve el proyecto con PHP, por ejemplo:

   ```bash
   php -S localhost:8000
