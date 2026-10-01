# 📊 SCGI — Auditoría Técnica y Matriz de Control de Avance
> **Proyecto:** Sistema de Control y Gestión de Importaciones (SCGI)  
> **Stack Objetivo:** Laravel 11 / 12 + PostgreSQL (`scgi_db`, user: `postgres`, pass: `Leo123.l`) + FilamentPHP  
> **Fecha de Actualización:** Octubre 2026  
> **Rol Líder:** Lead Fullstack Developer & Arquitecto de Software  
> **Estado Global Estimado:** **84% de Avance Real Operativo** (Sprint 1.1, 1.2, 1.3, 2.1 y 2.2 Validados con 48 Tests)

---

## 🎯 1. Resumen Ejecutivo del Estado del Sistema

El sistema SCGI implementa fielmente el flujo real de las operaciones portuarias y logísticas:

1. **Infraestructura y Seguridad RBAC (Sprint 1.1 - COMPLETADO):**
   * Migración completa y validada a **PostgreSQL** (`scgi_db`).
   * Matriz de **7 Roles Corporativos** (`UserRole` Enum: `Director`, `Aduana`, `PVP_Tecnico`, `Despacho_Puerto`, `Logistica_Chofer`, `Cajero_Regional`, `Auditor`).
   * `RequireRoleMiddleware` protegiendo cada ruta según el principio de menor privilegio.
   * `RbacService` controlando la matriz de transiciones de estados del flujo de importación.
   * Campos de persistencia crítica en `equipments` (`duty_paid_abroad`, `delivery_paid_abroad`, `customs_entry_at`, `customs_cleared_at`, `pvp_certified_at`).

2. **El Núcleo Financiero y Control de Cajas (Sprint 1.2 - COMPLETADO):**
   * Ecosistema completo de Cajas Regionales (`CashRegister`), Turnos y Arqueos multimoneda (`CashShift`), y Libro Contable de Pagos (`Payment`).
   * Soporte multimoneda estricto **USD y CUP** con tasa de cambio oficial configurable (`ExchangeRate`).
   * **Regla de Negocio de Prepago Total en Origen:** Identificación inequívoca de productos prepagados en el exterior (`duty_paid_abroad` + `delivery_paid_abroad` o exento de domicilio), exonerando al cliente final de cualquier cobro en Cuba y habilitando su entrega inmediata.
   * **Bloqueo Estricto de Entrega por Deuda:** Si un equipo tiene aranceles o fletes pendientes, el sistema prohíbe su entrega (`canBeDelivered() === false`) hasta que el cajero registre el pago correspondiente en ventanilla o pasarela.
   * `PaymentService` y `CashierController` con transacciones de base de datos atómicas (`DB::transaction`).

3. **Inteligencia de Aranceles y Exención Fiscal (Sprint 1.3 - COMPLETADO):**
   * Motor arancelario [TariffService](file:///d:/XAMPP/htdocs/mi-web/app/Services/TariffService.php) con bifurcación estricta B2B vs B2C:
     - **B2C:** Tasa arancelaria del 10% USD sobre el valor declarado + $50 USD flete a domicilio (si no fue prepagado en origen).
     - **B2B:** 0% arancelario condicionado a la validación estricta de certificado aduanal, número de declaración y partida arancelaria (Capítulos 84, 85, 87, 90) vía XML o documentación de soporte. En caso de ausencia o certificado vencido (>1 año), aplica fallback 10% B2C con log de advertencia.
     - **Manifiestos XML:** Función de parseo `parseCustomsXmlForExemption()` para procesar manifiestos aduanales de SolveCargo.
     - **Integración Eloquent:** Método `calculateFromEquipment()`.

4. **Módulo de Aduanas y Semáforos Portuarios 48h / 72h (Sprint 2.1 - COMPLETADO):**
   * Creación de `CustomsInspectionService` para gobernar el flujo de arribo a puerto Mariel, inicio de inspección física y libramiento aduanal (`customs_cleared_at`).
   * Motor de Semáforos de Permanencia Portuaria basado en `customs_entry_at`:
     - 🟢 **Verde (<48h):** Operación en tiempo estándar.
     - 🟡 **Amarillo (48h - 72h):** Alerta preventiva de congestión portuaria.
     - 🔴 **Rojo (>72h):** Alerta crítica de retención con notificación prioritaria a Dirección.
   * Controlador `CustomsController` y vistas operativas con RBAC `UserRole::ADUANA` y `UserRole::DIRECTOR`.

5. **Escáner de Despacho y Cotejo con Manifiesto en Puerto Mariel (Sprint 2.2 - COMPLETADO):**
   * **Regla de Negocio de Despacho sin Ensamblaje:** En el área de despacho los productos **no se ensamblan**. Pasan únicamente por el escáner del código de barras, PIN o VIN para cotejar los datos del paquete físico con el manifiesto de carga (SolveCargo / BL).
   * **Cotejo y Pase Directo:** Al confirmar la coincidencia (`MATCH_CONFIRMED`), el equipo transiciona directamente a `READY_FOR_DISPATCH` (Listo para Despacho Regional / Destino Provincial).
   * Lógica implementada en `DispatchReceptionService`: `lookupManifestForScan()`, `verifyAndClearForDispatch()`, y `reportManifestDiscrepancy()`.
   * Interfaz operativa en `DispatchScannerController` y vista `resources/views/dispatch/scanner.blade.php` con previsualización en tiempo real del manifiesto, indicadores arancelarios (Prepagado $0.00 vs Cobro en Cuba), reporte de discrepancias y disparo automático de WhatsApp.

---

## 📋 2. Matriz de Implementación por Módulos

| # | Funcionalidad del Negocio | Estado | Archivo(s) Involucrado(s) | Diagnóstico y Alcance Actual |
|---|---|---|---|---|
| **1** | **Infraestructura PostgreSQL** | **Completado (100%)** | `.env`, `config/database.php`, Migraciones | Base de datos `scgi_db` en PostgreSQL con soporte nativo para UUIDs, JSONB y tipado estricto. |
| **2** | **Seguridad: RBAC 7 Perfiles** | **Completado (100%)** | `UserRole.php`, `RbacService.php`, `RequireRoleMiddleware.php`, `User.php` | Control de acceso por rol jerárquico, sucursal regional y matriz de transiciones de estado. |
| **3** | **Persistencia de Equipos y Aduana** | **Completado (100%)** | `equipments` table, `Equipment.php`, `EquipmentStatus.php` | 11 estados de flujo, campos `duty_paid_abroad`, `delivery_paid_abroad`, timestamps de aduana y PVP. |
| **4** | **Cajas Regionales y Turnos (USD/CUP)** | **Completado (100%)** | `CashRegister.php`, `CashShift.php`, `PaymentService.php`, `CashierController.php` | Apertura de turno con fondo inicial, cobros multimoneda y cierre con arqueo físico y detección de faltantes/sobrantes. |
| **5** | **Libro de Pagos y Liquidación** | **Completado (100%)** | `Payment.php`, `PaymentService.php`, `Equipment.php` | Métodos `cash_usd`, `cash_cup`, `transfer_cup`, `online_usd`, generación de número de recibo único y liquidación de equipos. |
| **6** | **Regla de Prepago Total vs Cobro en Cuba** | **Completado (100%)** | `Equipment.php`, `PaymentService.php`, `TariffService.php` | Diferenciación automática: $0 USD para prepagos desde origen vs cobro obligatorio previo a entrega para aranceles pendientes. |
| **7** | **Tracking Público por PIN / VIN** | **Completado (95%)** | `PublicTrackingController.php`, vistas Blade públicas | Búsqueda pública por PIN único de 8 caracteres, VIN y SolveCargo ID con historial cronológico inmutable. |
| **8** | **Inteligencia de Aranceles (B2B vs B2C)** | **Completado (100%)** | `TariffService.php`, `Sprint13TariffIntelligenceTest.php` | Exención fiscal validada por XML/declaración en B2B (0%) vs 10% USD para B2C, y parsing de manifiestos XML. |
| **9** | **Módulo de Aduana: Alertas 48h / 72h** | **Completado (100%)** | `CustomsInspectionService.php`, `CustomsController.php`, vistas | Semáforo de permanencia aduanal en puerto basado en `customs_entry_at`, libramiento y retención. |
| **10** | **Despacho: Escaneo y Cotejo con Manifiesto** | **Completado (100%)** | `DispatchReceptionService.php`, `DispatchScannerController.php`, vistas | Escaneo de código de bulto, cotejo instantáneo con manifiesto SolveCargo, pase directo a Despacho sin ensamblaje. |
| **11** | **Hojas de Ruta y Logística Regional** | **Próximo Sprint (Sprint 2.3)** | `RegionalDispatchRoute.php`, `RegionalLogisticsService.php` | Asignación de choferes, camiones, despacho y recepción en destino provincial. |
| **12** | **Conexión Real WhatsApp Business API** | **Pendiente (Fase 2)** | `WhatsAppNotificationService.php` | Sustituir stub por Meta Cloud API / Proveedor Gateway con manejo de Webhooks. |
| **13** | **Integración SolveCargo: API dayready/v1** | **Pendiente (Fase 3)** | `SolveCargoApiService.php` | Sincronización asíncrona de despachos vía Cron/Scheduler. |
| **14** | **Parser XML de Manifiestos Aduanales** | **Pendiente (Fase 3)** | `ManifestXmlParserService.php` | Lectura automática de partidas arancelarias y exenciones fiscales B2B. |

---

## 📈 3. Registro de Estado y Tareas (Checklist Vivo)

- [x] **Sprint 1.1:** Configuración de PostgreSQL (`scgi_db`), corrección de `equipments` y despliegue del sistema RBAC (7 roles) con Middlewares.
- [x] **Sprint 1.2:** Creación del ecosistema de Cajas Regionales, Turnos y tabla de Pagos multimoneda (USD/CUP) con regla estricta de productos prepagados.
- [x] **Sprint 1.3:** Refactorización total de `TariffService` para manejar la diferencia real entre B2B (XML de exención) y B2C (+10% USD).
- [x] **Sprint 2.1:** Módulo de Aduanas con alertas de semáforo 48h / 72h basadas en `customs_entry_at`.
- [x] **Sprint 2.2:** Escáner de Despacho y Cotejo con Manifiesto en Puerto Mariel (Sin ensamblaje; cotejo estricto de código/bulto y pase directo a Listo para Despacho Regional / Destino).
- [🔄] **Sprint 2.3 (EN DESARROLLO ACTIVO):** Hojas de Ruta y Logística Regional (Creación de hoja de ruta, asignación de chofer/camión, escáner de carga, despacho en convoy y recepción en almacén provincial).
- [ ] **Sprint 2.4:** Conexión Gateway real de WhatsApp Business API.
- [ ] **Sprint 3.1:** Cliente HTTP SolveCargo API (`dayready/v1`).
- [ ] **Sprint 3.2:** Parser de Manifiestos XML y precarga automática de partidas arancelarias.
- [ ] **Sprint 3.3:** Recursos FilamentPHP y reportes PDF/Excel para cierres de caja y auditoría.

---
*Bitácora técnica actualizada por el Arquitecto de Software al iniciar el Sprint 2.3.*
