<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Residentes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f0f2f5; }
        .login-container { min-height: 100vh; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row login-container align-items-center justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold">Portal de Residentes</h3>
                            <p class="text-muted">Ingresa para ver tu estado de cuenta.</p>
                        </div>

                        <form method="POST" action="{{ route('portal.login.submit') }}">
                            @csrf
                            <div class="form-floating mb-3">
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" id="email" placeholder="tu@email.com" value="{{ old('email') }}" required autofocus>
                                <label for="email"><i class="bi bi-person-fill me-2"></i>Correo Electrónico</label>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-floating mb-3">
                                <input type="password" name="password" class="form-control" id="password" placeholder="Contraseña" required>
                                <label for="password"><i class="bi bi-key-fill me-2"></i>Contraseña</label>
                            </div>
                            <div class="d-grid">
                                <button class="btn btn-success btn-lg" type="submit">Entrar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>