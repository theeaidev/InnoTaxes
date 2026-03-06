# AGENTS.md

## Proposito
Este repositorio debe evolucionar con este stack objetivo:

- Laravel
- Blade
- Livewire 3
- Alpine.js
- CoreUI 5
- Bootstrap 5
- Vite
- Reverb + Laravel Echo

Usa este archivo como la referencia principal para decidir arquitectura, UI, interactividad y tiempo real. Si el estado actual del repo no coincide al 100% con este stack, prioriza estas reglas para todo trabajo nuevo y para refactors incrementales.

## Estado actual del repositorio
Lo que hay hoy en la base:

- Laravel 12 sobre PHP 8.2.
- Vistas Blade en `resources/views`.
- Rutas web y auth tradicionales en `routes/web.php` y `routes/auth.php`.
- Controladores HTTP clasicos de Breeze para auth y perfil.
- Vite con entradas en `resources/css/app.css` y `resources/js/app.js`.
- Alpine.js cargado en `resources/js/app.js`.
- Bootstrap y CoreUI instalados por NPM y cargados en JS.
- Tailwind/Breeze todavia presentes en muchas vistas y en `resources/css/app.css`.
- `livewire/livewire` instalado actualmente como `^4.2`, aunque la convencion objetivo del proyecto es Livewire 3.
- No hay integracion completa de Reverb/Echo todavia: faltan configuracion y cliente dedicados.
- Testing con PHPUnit clasico en `tests/Feature` y `tests/Unit`.

## Regla de oro
Cuando haya conflicto entre el scaffold actual y el stack objetivo, no profundices la deuda del scaffold. Trabaja en direccion a Blade + Livewire 3 + CoreUI/Bootstrap + Alpine + Reverb/Echo.

## Stack y decisiones obligatorias

### 1. Renderizado y arquitectura de UI
- Blade es la capa de vistas principal.
- Livewire 3 es la opcion por defecto para pantallas interactivas, formularios ricos, tablas, filtros, modales y widgets que dependan del servidor.
- Alpine.js se usa solo para microinteracciones locales del DOM.
- No introducir React, Vue, Inertia, jQuery ni una SPA paralela salvo instruccion explicita.

### 2. Sistema visual
- CoreUI 5 + Bootstrap 5 son el sistema de UI oficial.
- Para layouts, navegacion, sidebar, header, cards, tablas, formularios, offcanvas y modales, prioriza patrones de CoreUI.
- Para grid, espaciado y utilidades, prioriza Bootstrap.
- No anadir nuevas pantallas basadas en Tailwind como primera opcion.
- Si tocas una vista vieja de Breeze/Tailwind, intenta migrarla gradualmente a Bootstrap/CoreUI en vez de extender mas clases Tailwind.

### 3. Livewire
- Escribe componentes nuevos siguiendo la organizacion y mentalidad de Livewire 3.
- Ubica las clases, salvo razon fuerte en contra, en `app/Livewire` y sus vistas en `resources/views/livewire`.
- Evita usar APIs exclusivas de Livewire 4 mientras la base no se alinee oficialmente a esa version.
- Si una tarea depende de una diferencia entre Livewire 3 y 4, deja constancia del mismatch antes de apoyarte en una API version-especifica.
- La logica de estado del componente debe vivir en Livewire, no duplicarse innecesariamente en Alpine.
- Usa eventos, propiedades y validacion de Livewire para formularios interactivos antes que soluciones manuales con fetch o JS ad hoc.

### 4. Alpine.js
- Reservalo para toggles, dropdowns, tabs simples, colapso de sidebar, estados efimeros de UI y comportamiento decorativo.
- No lo uses como sustituto de Livewire para estado de negocio o formularios conectados a Eloquent.
- Si Livewire controla el estado principal, Alpine solo debe complementar.

### 5. Tiempo real
- El estandar del proyecto para realtime es Laravel Reverb en backend y Laravel Echo en frontend.
- No introducir Pusher ni soluciones websocket alternativas salvo instruccion explicita.
- Si implementas realtime, revisa y crea lo necesario de forma consistente:
  - eventos `ShouldBroadcast`
  - canales de broadcasting
  - `routes/channels.php` cuando haya canales privados o de presencia
  - configuracion en `config/broadcasting.php` y, si aplica, `config/reverb.php`
  - cliente Echo en frontend, idealmente en un modulo dedicado como `resources/js/echo.js`
  - variables de entorno relacionadas con broadcasting/reverb/echo
- No asumas que Reverb/Echo ya esta cableado: verificalo primero.

### 6. Assets y frontend build
- Vite es el unico pipeline de assets.
- Toda carga de CSS/JS debe entrar por `@vite(...)`.
- Si hace falta JS adicional, importalo desde `resources/js/app.js` o desde modulos llamados por ese entrypoint.
- Si CoreUI o Bootstrap requieren CSS/SCSS adicional, canalizalo por Vite; no metas dependencias por CDN salvo necesidad muy concreta.
- Al tocar estilos globales, empuja `resources/css/app.css` hacia Bootstrap/CoreUI y evita seguir ampliando el uso de Tailwind.

## Convenciones de implementacion

### Backend Laravel
- Mantener controladores delgados.
- Validacion en Form Requests cuando tenga sentido.
- Mover logica de negocio no trivial a acciones, servicios o clases dedicadas si empieza a crecer.
- Mantener rutas con nombres claros y consistentes.
- Preferir Eloquent y relaciones expresivas antes que consultas duplicadas o logica SQL dispersa.

### Blade
- Organiza vistas por feature.
- Reutiliza componentes Blade cuando aporten claridad real.
- Evita componentes excesivamente abstractos para markup pequeno.
- Para shells de aplicacion autenticada, prioriza una estructura CoreUI de sidebar/header/content por encima del layout Breeze actual cuando se refactorice UI.

### Livewire + Blade
- Un componente Livewire debe mapear a una unidad clara de UI o flujo.
- Mantener las vistas de Livewire enfocadas en markup y delegar comportamiento al componente PHP.
- Usar loading states, empty states y validacion inline cuando mejore la experiencia.
- Si una pagina es mayoritariamente interactiva, prefiere una page component Livewire antes que mezclar demasiada logica en un controller + Blade.

### Bootstrap y CoreUI
- Usa clases y componentes del framework antes de escribir CSS custom.
- El CSS custom debe ser pequeno, intencional y preferentemente encapsulado por feature.
- No mezclar sin necesidad tres sistemas visuales para la misma pantalla.
- Si una pantalla nueva usa Bootstrap/CoreUI, no mezcles utilidades Tailwind salvo casos de transicion muy justificados.

## Testing
- El proyecto usa PHPUnit, no Pest.
- Para cambios backend o de flujo, anade o actualiza tests en `tests/Feature` o `tests/Unit`.
- Para componentes Livewire, usa tests de Livewire cuando aplique.
- Usa `RefreshDatabase` en tests que toquen persistencia.

## Archivos y zonas relevantes
- `app/Http` para controladores y requests.
- `app/Models` para modelos Eloquent.
- `app/Providers` para bootstrapping global.
- `resources/views` para Blade.
- `resources/js` para Bootstrap, CoreUI, Alpine y Echo.
- `resources/css` para estilos cargados por Vite.
- `routes` para rutas web, auth y consola.
- `database/migrations` para schema.
- `tests` para cobertura automatizada.

## Zonas que no se deben tratar como fuente de verdad
- No editar manualmente `vendor/`.
- No editar manualmente `node_modules/`.
- No tomar `public/build/` como codigo fuente.
- No tomar `storage/framework/views/` como fuente editable.

## Guia practica para cambios nuevos
- Si la tarea es una pagina CRUD o un dashboard interactivo: Blade + Livewire 3 + Bootstrap/CoreUI.
- Si la tarea es un toggle, dropdown o detalle visual local: Blade + Alpine.
- Si la tarea necesita actualizacion en vivo: Laravel events + Reverb + Echo.
- Si el codigo existente esta en Breeze/Tailwind: migrar con criterio, sin reescribir mas de lo necesario, pero evitando reforzar ese camino.

## Comandos habituales
- `composer test`
- `php artisan test`
- `npm run dev`
- `npm run build`

## Nota sobre el mismatch actual
Ahora mismo hay restos claros del scaffold inicial y una dependencia `livewire/livewire:^4.2`. Aun asi, la conviccion arquitectonica de este repositorio debe ser:

- Blade como base
- Livewire 3 como patron de interactividad server-driven
- Alpine para micro-UI
- CoreUI 5 + Bootstrap 5 como sistema visual
- Vite como pipeline
- Reverb/Echo para realtime

Si una tarea futura requiere alinear dependencias para cumplir estas reglas, proponlo y ejecutalo de forma explicita.
