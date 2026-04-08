# Resumen - Proyecto Hackatón Toka (Mini Apuestas + Gamificación)

## Contexto del Proyecto

**Toka** es una Super App (tarjeta de débito) donde los pagos generan **Toka Coins** (cashback). El hackatón consiste en crear un módulo de mini-apuestas dentro de Toka.

---

## 3 Juegos a Implementar

| Juego | Apuesta | Premio Mínimo | Premio Máximo |
|-------|---------|---------------|---------------|
| **Rasca y Gana** | 10-100 Coins | 25 Coins (0.25x) | 500 Coins (5x) |
| **Plinko** | 10-100 Coins | 25 Coins (0.25x) | 500 Coins (5x) |
| **Ruleta** | 10-100 Coins | 25 Coins | 1000 Coins (10x) |

---

## Arquitectura

### Frontend
- **Tecnología**: Mini Program Alipay (AXML, ACSS, JavaScript)
- **Entorno**: Mini Program Studio
- **Archivos actuales**: `pages/rasca/`, `pages/plinko/`, `pages/ruleta/`

### Backend (Laravel)
- **API REST** para lógica de juegos
- **Servidor**: Gestiona saldo, resultados, spline de Plinko, historial, racha

### Flujo de Datos
```
Frontend (tap "Jugar") → POST /api/{game}/play → Laravel (calcula resultado)
                                                 ↓
Frontend (animación) ← { result, multiplier, spline? } ← Laravel
```

---

## Puntos Clave

1. **Premio definido por servidor** (no RNG local en frontend)
2. **Premio mínimo = 25 Coins** (todos los juegos)
3. **Siempre valores enteros** (no decimales en UI)
4. **Mostrar multiplicador** en cada juego (ej: "25x", "0.25x")
5. **Plinko con spline**: Servidor calcula física → envía spline → cliente reproduce animación
6. **Sistema de racha**: Multiplicador por uso de app (1.0 → 1.05 → ... → 1.5x), reset después de 24h inactivo

---

## Pendiente por Definir

| Tema | Estado |
|------|--------|
| Endpoints Laravel | Por definir |
| Estructura del proyecto Laravel | Por confirmar |
| Autenticación (JWT/Token) | Por definir |
| Base de datos (tablas) | Por definir |
| Integración con saldo Toka | Simulado (pendiente) |

---

## Scope Hackatón (Entregable)

### MVP
- [x] 3 juegos funcionales
- [x] Premio desde servidor
- [x] Multiplicador visible
- [x] Validación apuesta (10-100)
- [ ] Sistema de recompensas
- [ ] Integración Laravel

### Sin Implementar (este hackatón)
- Cashback de pagos
- Sistema de recompensas

---

## Próximos Pasos

1. Crear `services/api.js` para conectar con Laravel
2. Modificar `app.js` para estado global (balance, racha)
3. Modificar cada juego para usar API
4. Implementar Plinko con reproducción de spline
5. Probar en Mini Program Studio
6. Integrar con Laravel cuando esté listo

---

## Endpoints Laravel Sugeridos

```javascript
GET  /api/balance          // Obtener saldo del usuario
POST /api/rasca/play       // Jugar Rasca y Gana {bet}
POST /api/plinko/play     // Jugar Plinko {bet}
POST /api/ruleta/play     // Jugar Ruleta {bet}
GET  /api/streak          // Obtener racha actual
GET  /api/history         // Historial de partidas
```

---

## Sistema de Racha (Propuesto)

```javascript
const STREAK_CONFIG = {
  BASE_MULTIPLIER: 1.0,    // Sin racha
  MAX_MULTIPLIER: 1.5,     // Racha máxima (1.5x)
  INCREMENT: 0.05,        // +0.05 por cada uso de la app
  RESET_ON_INACTIVE: '24h' // Se resetea después de 24h inactivo
};
```

**Lógica**: Cada vez que el usuario juega, su multiplicador de recompensas aumenta en 0.05 (hasta 1.5x). Si no juega en 24h, vuelve a 1.0x.

---

## Estado Actual

🟡 **Modo Planificación** - Listo para implementar cuando el usuario confirme.
