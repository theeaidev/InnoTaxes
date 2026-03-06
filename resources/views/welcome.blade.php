<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Innotaxes | Spanish Tax Automation</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="landing-home">
        <section class="home-landing">
            <div class="container">
                <div class="brand-panel">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-5">
                        <div class="d-flex align-items-center flex-wrap gap-3">
                            <div class="brand-logo-box" aria-label="Innotaxes">
                                <span class="brand-wordmark">innota<span class="brand-wordmark__accent">x</span>es</span>
                            </div>

                            <span class="hero-badge">Spanish tax automation</span>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            @auth
                                <a href="{{ route('dashboard') }}" class="btn btn-brand">Go to dashboard</a>
                            @else
                                @if (Route::has('login'))
                                    <a href="{{ route('login') }}" class="btn btn-brand">Log in</a>
                                @endif

                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="btn btn-outline-brand">Create account</a>
                                @endif
                            @endauth
                        </div>
                    </div>

                    <div class="row align-items-center g-4 g-xl-5">
                        <div class="col-lg-7">
                            <p class="section-kicker">IRPF, SII and Veri*factu in one operating flow</p>

                            <h1 class="hero-headline">
                                Fintech platform designed to automate
                                <span class="headline-accent headline-accent--teal">IRPF</span>
                                (Spanish Personal Income Tax) and fiscal compliance
                                <span class="headline-accent headline-accent--amber">(SII / Veri*factu)</span>.
                            </h1>

                            <p class="hero-copy">
                                A single surface for Spanish tax operations, built to keep calculations, reporting,
                                and compliance streams aligned from the start.
                            </p>

                            <div class="d-flex flex-wrap gap-2 mt-4">
                                <span class="home-pill">IRPF automation</span>
                                <span class="home-pill">SII-ready reporting</span>
                                <span class="home-pill">Veri*factu compliance</span>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="insight-shell">
                                <p class="insight-label">Core fiscal flows</p>

                                <article class="signal-card signal-card--teal">
                                    <div class="signal-index">01</div>
                                    <div>
                                        <h2>IRPF</h2>
                                        <p>Automate personal income tax operations with cleaner, more reliable workflows.</p>
                                    </div>
                                </article>

                                <article class="signal-card signal-card--amber">
                                    <div class="signal-index">02</div>
                                    <div>
                                        <h2>SII</h2>
                                        <p>Keep immediate VAT reporting structured and ready for submission.</p>
                                    </div>
                                </article>

                                <article class="signal-card">
                                    <div class="signal-index">03</div>
                                    <div>
                                        <h2>Veri*factu</h2>
                                        <p>Prepare invoicing compliance with a platform shaped for Spanish fiscal controls.</p>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </body>
</html>
